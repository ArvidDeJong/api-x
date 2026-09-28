<?php

declare(strict_types=1);

namespace Darvis\ApiX;

use Darvis\ApiX\Exceptions\XException;
use Darvis\ApiX\Models\XPost;
use Darvis\ApiX\Support\OAuth1;
use Darvis\ApiX\Support\PostText;
use Darvis\ApiX\Support\XConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Posts text, optionally with one image or as a reply, to the X account the credentials belong
 * to, and reads the posts that mention that account.
 *
 * Every check (text length, links, reply target, daily limit, image) runs before anything is
 * sent, also in a dry run, so a dry run fails exactly where a real post would.
 */
class XClient
{
    /**
     * X accepts images up to 5 MB.
     */
    public const MAX_IMAGE_BYTES = 5 * 1024 * 1024;

    private const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    /**
     * The mentions timeline returns at least 5 and at most 100 posts per request.
     */
    public const MENTIONS_MIN = 5;

    public const MENTIONS_MAX = 100;

    /**
     * Post a text, with an optional image given as a local path or an http(s) URL. With
     * $replyTo (a post id or a link to a post) the post is a reply to that post.
     *
     * @throws XException
     */
    public function post(string $text, ?string $image = null, bool $dryRun = false, ?string $replyTo = null): PostResult
    {
        $text = trim($text);

        $this->validateText($text);

        $replyTo = $replyTo === null || trim($replyTo) === '' ? null : $this->postId(trim($replyTo));

        $remaining = $this->remainingToday();
        if ($remaining === 0) {
            throw XException::dailyLimitReached(XConfig::dailyLimit());
        }

        $media = $image === null || trim($image) === '' ? null : $this->loadImage(trim($image));

        if ($dryRun || XConfig::dryRun()) {
            return new PostResult($text, dryRun: true, remainingToday: $remaining, replyTo: $replyTo);
        }

        $oauth = $this->oauth();
        $mediaId = null;

        try {
            $mediaId = $media === null ? null : $this->uploadMedia($oauth, $media);
            $id = $this->createPost($oauth, $text, $mediaId, $replyTo);
        } catch (Throwable $exception) {
            // A timeout or a refused connection arrives here as well; store it like a refusal.
            $failure = $exception instanceof XException ? $exception : XException::requestError($exception);

            $this->record($text, $image, XPost::STATUS_FAILED, mediaId: $mediaId, error: $failure->getMessage());

            throw $failure;
        }

        $this->countPost();
        $this->record($text, $image, XPost::STATUS_SENT, xId: $id, mediaId: $mediaId);

        return new PostResult(
            $text,
            dryRun: false,
            id: $id,
            mediaId: $mediaId,
            remainingToday: $remaining === null ? null : $remaining - 1,
            replyTo: $replyTo,
        );
    }

    /**
     * The newest posts that mention the account, newest first: replies to its posts and posts
     * that name it. The account's own posts are left out. X bills every post returned as a
     * read, so pass the id of the newest mention you already have as $sinceId.
     *
     * @return list<Mention>
     *
     * @throws XException
     */
    public function mentions(?string $sinceId = null, int $limit = 10): array
    {
        $sinceId = $sinceId === null || trim($sinceId) === '' ? null : trim($sinceId);
        if ($sinceId !== null && preg_match('/^\d+$/', $sinceId) !== 1) {
            throw XException::invalidSinceId($sinceId);
        }

        $oauth = $this->oauth();
        $userId = $this->userId();

        $url = XConfig::apiUrl().'/2/users/'.$userId.'/mentions';
        $query = [
            'expansions' => 'author_id',
            'max_results' => (string) max(self::MENTIONS_MIN, min(self::MENTIONS_MAX, $limit)),
            'tweet.fields' => 'author_id,conversation_id,created_at,referenced_tweets',
        ];
        if ($sinceId !== null) {
            $query['since_id'] = $sinceId;
        }

        try {
            $response = Http::timeout(XConfig::timeout())
                ->withHeaders(['Authorization' => $oauth->header('GET', $url, $query)])
                ->get($url, $query);
        } catch (Throwable $exception) {
            throw XException::requestError($exception);
        }

        if (! $response->successful()) {
            throw XException::requestFailed('read the mentions', $response);
        }

        $users = [];
        foreach ((array) $response->json('includes.users', []) as $user) {
            if (is_array($user) && isset($user['id'])) {
                $users[(string) $user['id']] = $user;
            }
        }

        $mentions = [];
        foreach ((array) $response->json('data', []) as $post) {
            if (! is_array($post) || ! isset($post['id']) || ($post['author_id'] ?? null) === $userId) {
                continue;
            }

            $authorId = (string) ($post['author_id'] ?? '');
            $inReplyTo = null;
            foreach ((array) ($post['referenced_tweets'] ?? []) as $reference) {
                if (is_array($reference) && ($reference['type'] ?? null) === 'replied_to') {
                    $inReplyTo = (string) ($reference['id'] ?? '');
                }
            }

            $mentions[] = [
                'id' => (string) $post['id'],
                'text' => (string) ($post['text'] ?? ''),
                'authorId' => $authorId,
                'authorUsername' => isset($users[$authorId]['username']) ? (string) $users[$authorId]['username'] : null,
                'authorName' => isset($users[$authorId]['name']) ? (string) $users[$authorId]['name'] : null,
                'createdAt' => isset($post['created_at']) ? (string) $post['created_at'] : null,
                'conversationId' => isset($post['conversation_id']) ? (string) $post['conversation_id'] : null,
                'inReplyToId' => $inReplyTo === '' ? null : $inReplyTo,
            ];
        }

        $ownPosts = $this->ownPostTexts(array_values(array_filter(array_column($mentions, 'inReplyToId'))));

        return array_map(fn (array $mention): Mention => new Mention(
            ...$mention,
            inReplyToText: $mention['inReplyToId'] === null ? null : ($ownPosts[$mention['inReplyToId']] ?? null),
        ), $mentions);
    }

    /**
     * The account the credentials belong to, from GET /2/users/me. Proves the keys work
     * without posting anything; X bills it as one read.
     *
     * @return array{id: string, name: string, username: string}
     *
     * @throws XException
     */
    public function account(): array
    {
        $url = XConfig::apiUrl().'/2/users/me';

        $authorization = $this->oauth()->header('GET', $url);

        try {
            $response = Http::timeout(XConfig::timeout())
                ->withHeaders(['Authorization' => $authorization])
                ->get($url);
        } catch (Throwable $exception) {
            throw XException::requestError($exception);
        }

        $data = $response->json('data');

        if (! $response->successful() || ! is_array($data) || ! is_string($data['username'] ?? null)) {
            throw XException::requestFailed('check the keys', $response);
        }

        return [
            'id' => (string) ($data['id'] ?? ''),
            'name' => (string) ($data['name'] ?? ''),
            'username' => $data['username'],
        ];
    }

    /**
     * Posts left today, or null when there is no daily limit.
     */
    public function remainingToday(): ?int
    {
        $limit = XConfig::dailyLimit();

        if ($limit === 0) {
            return null;
        }

        try {
            $posted = (int) Cache::store(XConfig::cacheStore())->get($this->counterKey(), 0);
        } catch (Throwable $exception) {
            // Fail closed: without a working counter the limit cannot be kept.
            throw XException::cacheUnavailable($exception);
        }

        return max(0, $limit - $posted);
    }

    /**
     * @throws XException
     */
    protected function validateText(string $text): void
    {
        if ($text === '') {
            throw XException::emptyText();
        }

        $length = PostText::weightedLength($text);
        $max = XConfig::subscription()->maxLength();
        if ($length > $max) {
            throw XException::textTooLong($length, $max, $max < PostText::LONG_MAX_LENGTH && $length <= PostText::LONG_MAX_LENGTH);
        }

        if (! XConfig::allowLinks() && PostText::containsLink($text)) {
            throw XException::linkNotAllowed();
        }
    }

    /**
     * Read the image and check it against what X accepts.
     *
     * @return array{contents: string, mime: string, name: string}
     *
     * @throws XException
     */
    protected function loadImage(string $image): array
    {
        if (preg_match('~^https?://~i', $image) === 1) {
            try {
                $response = Http::timeout(XConfig::timeout())->get($image);
            } catch (Throwable $exception) {
                throw XException::imageNotFound($image, $exception->getMessage());
            }

            if (! $response->successful()) {
                throw XException::imageNotFound($image, 'HTTP '.$response->status());
            }

            $contents = $response->body();
        } else {
            $contents = is_file($image) && is_readable($image) ? (string) file_get_contents($image) : '';
        }

        if ($contents === '') {
            throw XException::imageNotFound($image);
        }

        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents);
        if (! in_array($mime, self::IMAGE_TYPES, true)) {
            throw XException::unsupportedImage($mime);
        }

        if (strlen($contents) > self::MAX_IMAGE_BYTES) {
            throw XException::imageTooLarge(strlen($contents), self::MAX_IMAGE_BYTES);
        }

        return ['contents' => $contents, 'mime' => $mime, 'name' => 'image.'.explode('/', $mime)[1]];
    }

    /**
     * Upload the image in one request and return its media id.
     *
     * @param  array{contents: string, mime: string, name: string}  $media
     *
     * @throws XException
     */
    protected function uploadMedia(OAuth1 $oauth, array $media): string
    {
        $url = XConfig::apiUrl().'/2/media/upload';

        $response = Http::timeout(XConfig::timeout())
            ->withHeaders(['Authorization' => $oauth->header('POST', $url)])
            ->attach('media', $media['contents'], $media['name'], ['Content-Type' => $media['mime']])
            ->post($url, ['media_category' => 'tweet_image']);

        $id = $response->json('data.id');

        if (! $response->successful() || ! is_string($id)) {
            throw XException::requestFailed('upload the image', $response);
        }

        return $id;
    }

    /**
     * Create the post and return its id.
     *
     * @throws XException
     */
    protected function createPost(OAuth1 $oauth, string $text, ?string $mediaId, ?string $replyTo = null): string
    {
        $payload = ['text' => $text];
        if ($mediaId !== null) {
            $payload['media'] = ['media_ids' => [$mediaId]];
        }
        if ($replyTo !== null) {
            $payload['reply'] = ['in_reply_to_tweet_id' => $replyTo];
        }

        $url = XConfig::apiUrl().'/2/tweets';
        $response = Http::timeout(XConfig::timeout())
            ->withHeaders(['Authorization' => $oauth->header('POST', $url)])
            ->asJson()
            ->post($url, $payload);

        $id = $response->json('data.id');

        if (! $response->successful() || ! is_string($id)) {
            throw XException::requestFailed('create the post', $response);
        }

        return $id;
    }

    /**
     * Store a post that went to X. A missing table or any other database problem is reported
     * and never decides whether a post goes out.
     */
    protected function record(string $text, ?string $image, string $status, ?string $xId = null, ?string $mediaId = null, ?string $error = null): void
    {
        if (! XConfig::historyEnabled()) {
            return;
        }

        try {
            XPost::create([
                'text' => $text,
                'image' => $image === null || trim($image) === '' ? null : mb_substr(trim($image), 0, 255),
                'status' => $status,
                'x_id' => $xId,
                'media_id' => $mediaId,
                'error' => $error,
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * The id of a post, from the id itself or from a link to it on x.com or twitter.com.
     *
     * @throws XException
     */
    protected function postId(string $value): string
    {
        if (preg_match('/^\d+$/', $value) === 1) {
            return $value;
        }

        if (preg_match('~^https?://(?:www\.|mobile\.)?(?:x|twitter)\.com/(?:[A-Za-z0-9_]+|i/web)/status(?:es)?/(\d+)~i', $value, $match) === 1) {
            return $match[1];
        }

        throw XException::invalidReplyTo($value);
    }

    /**
     * The id of the account the keys belong to. Looked up once with GET /2/users/me (one billed
     * read) and then kept in the cache, per access token.
     *
     * @throws XException
     */
    protected function userId(): string
    {
        $key = 'api_x:user_id:'.sha1((string) (XConfig::credentials()['access_token'] ?? ''));

        try {
            $cached = Cache::store(XConfig::cacheStore())->get($key);
        } catch (Throwable $exception) {
            // Without a cache the lookup simply runs every time.
            report($exception);
            $cached = null;
        }

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $id = $this->account()['id'];

        try {
            Cache::store(XConfig::cacheStore())->forever($key, $id);
        } catch (Throwable $exception) {
            report($exception);
        }

        return $id;
    }

    /**
     * The texts of the account's own posts with these ids, from the history. Gives a mention
     * its context without another billed read; an id that is not in the history is left out.
     *
     * @param  list<string>  $ids
     * @return array<string, string>
     */
    protected function ownPostTexts(array $ids): array
    {
        if ($ids === [] || ! XConfig::historyEnabled()) {
            return [];
        }

        try {
            return XPost::query()
                ->whereIn('x_id', array_values(array_unique($ids)))
                ->pluck('text', 'x_id')
                ->map(fn ($text): string => (string) $text)
                ->all();
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }
    }

    /**
     * @throws XException
     */
    protected function oauth(): OAuth1
    {
        return new OAuth1(XConfig::credentials() ?? throw XException::missingCredentials());
    }

    protected function countPost(): void
    {
        if (XConfig::dailyLimit() === 0) {
            return;
        }

        $key = $this->counterKey();

        try {
            $cache = Cache::store(XConfig::cacheStore());
            $cache->add($key, 0, now()->endOfDay());
            $cache->increment($key);
        } catch (Throwable $exception) {
            // The post is already out; report the counter problem instead of claiming it failed.
            report($exception);
        }
    }

    private function counterKey(): string
    {
        return 'api_x:posts:'.now()->toDateString();
    }
}
