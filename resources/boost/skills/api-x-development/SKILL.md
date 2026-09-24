---
name: api-x-development
description: Work with darvis/api-x. Use it to post a text with an image to X from Laravel code, the x:post command or the post-update MCP tool, to write a release announcement, to handle a refused post and to test all of it without calling X.
---

# darvis/api-x development

## When to use this skill

Use this skill when code or an agent posts to X in an application that has `darvis/api-x` installed, when a post is refused with an `XException`, when you announce a release on X, or when you write tests around any of this.

## Setting it up

Run `php artisan x:install`. It asks for the four keys, checks them with `XClient::account()` (one billed read), sets `X_DAILY_LIMIT`, `X_ALLOW_LINKS` and `X_DRY_RUN`, adds the server to `.mcp.json` and ends with a dry run. In a script: `php artisan x:install --consumer-key=... --consumer-secret=... --access-token=... --access-token-secret=... --verify --mcp --no-interaction`.

## How a post runs

`XClient::post($text, $image = null, $dryRun = false)` runs these steps in order and stops at the first failure:

1. Trim the text; an empty text throws `The post text is empty.`
2. Count it the way X does (most Latin characters 1, emoji and CJK 2, a link 23). Above 280 it throws `The post text counts N characters on X; the maximum is 280.`
3. Without `X_ALLOW_LINKS=true`, a URL, `www.` or a bare domain such as `arvid.nl` throws the link message.
4. When the daily limit is reached it throws `The daily limit of N posts is reached.`
5. Read and check the image: it must exist, be a JPEG, PNG, GIF or WebP and be at most 5 MB.
6. In a dry run, return a `PostResult` with `dryRun` true and no id.
7. Without all four keys it throws `X credentials are missing.`
8. Upload the image to `/2/media/upload` (multipart, `media_category` `tweet_image`), then create the post at `/2/tweets` with the media id.

## Writing a good post

- Stay well under 280 characters; one or two sentences and at most three short points.
- Leave links out. The image and the package name already say what it is about.
- Write in the voice of the account owner, in the language the owner uses on X.

## Announcing a release

After a tag is pushed, build the post from the new section of `CHANGELOG.md` and attach the release card GitHub makes for every release:

@verbatim
<code-snippet name="Release announcement" lang="php">
use Darvis\ApiX\XClient;

app(XClient::class)->post(
    "darvis/api-x 1.1.0 is out.\n\n• Posts with a GIF\n• Clearer error for a read only token",
    'https://opengraph.githubassets.com/1/ArvidDeJong/api-x/releases/tag/v1.1.0',
);
</code-snippet>
@endverbatim

Through MCP the same post is the `post-update` tool with `text` and `image`.

## Handling a refusal

Every failure is an `XException`. When X itself refuses, the message starts with `X refused to create the post` or `X refused to upload the image` and holds the HTTP status and X's reason. A 403 usually means the app has no Write permission or the access token predates it; 402 means no API credit; 429 means wait. Don't retry a 4xx in a loop.

## Testing

@verbatim
<code-snippet name="Fake X in a test" lang="php">
use Illuminate\Support\Facades\Http;

Http::fake([
    'api.x.com/2/media/upload' => Http::response(['data' => ['id' => '10']]),
    'api.x.com/2/tweets' => Http::response(['data' => ['id' => '20']], 201),
]);
</code-snippet>
@endverbatim

Use the `array` cache store so the daily limit starts at zero, and `Http::assertNothingSent()` after a dry run.
