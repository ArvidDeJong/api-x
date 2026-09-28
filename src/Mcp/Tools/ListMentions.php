<?php

declare(strict_types=1);

namespace Darvis\ApiX\Mcp\Tools;

use Darvis\ApiX\Exceptions\XException;
use Darvis\ApiX\Mention;
use Darvis\ApiX\XClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Read the newest posts that mention this X account, newest first: replies to its posts and posts that name it. X bills every post returned as a read, so pass since_id to get only what is new. Answer one with post-update and reply_to.')]
#[IsReadOnly]
#[IsOpenWorld]
class ListMentions extends Tool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'since_id' => $schema->string()
                ->description('Only return posts newer than this post id, usually the newest id of the previous call.'),
            'limit' => $schema->integer()
                ->description('How many posts to read, '.XClient::MENTIONS_MIN.' to '.XClient::MENTIONS_MAX.'. Defaults to 10.'),
        ];
    }

    public function handle(Request $request, XClient $client): Response
    {
        $validated = $request->validate([
            'since_id' => ['nullable', 'string'],
            'limit' => ['nullable', 'integer', 'min:'.XClient::MENTIONS_MIN, 'max:'.XClient::MENTIONS_MAX],
        ]);

        try {
            $mentions = $client->mentions($validated['since_id'] ?? null, (int) ($validated['limit'] ?? 10));
        } catch (XException $exception) {
            return Response::error($exception->getMessage());
        }

        if ($mentions === []) {
            return Response::text('No mentions'.(isset($validated['since_id']) ? ' newer than '.$validated['since_id'] : '').'.');
        }

        $blocks = array_map(fn (Mention $mention): string => $this->describe($mention), $mentions);

        return Response::text(implode("\n\n", $blocks)
            ."\n\nNewest id: {$mentions[0]->id}. Pass it as since_id next time to read only newer posts.");
    }

    private function describe(Mention $mention): string
    {
        $author = $mention->authorUsername === null
            ? 'user '.$mention->authorId
            : '@'.$mention->authorUsername.($mention->authorName === null ? '' : " ({$mention->authorName})");

        $context = match (true) {
            $mention->inReplyToText !== null => "\nIn reply to your post {$mention->inReplyToId}: ".str($mention->inReplyToText)->squish()->limit(120),
            $mention->inReplyToId !== null => "\nIn reply to post {$mention->inReplyToId}.",
            default => '',
        };

        return "Post {$mention->id} by {$author}".($mention->createdAt === null ? '' : ", {$mention->createdAt}")
            ."\n{$mention->url()}"
            .$context
            ."\n{$mention->text}";
    }
}
