<?php

declare(strict_types=1);

namespace Darvis\ApiX\Mcp\Tools;

use Darvis\ApiX\Exceptions\XException;
use Darvis\ApiX\XClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Description('Publish a post on X with an optional image. The post goes out immediately and publicly; use dry_run to check it first. Returns the link to the post and how many posts are left today.')]
#[IsOpenWorld]
class PostUpdate extends Tool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'text' => $schema->string()
                ->description('The post text, at most 280 characters as X counts them. No links unless the app allows them.')
                ->required(),
            'image' => $schema->string()
                ->description('Optional image: an absolute local path or an http(s) URL to a JPEG, PNG, GIF or WebP of at most 5 MB.'),
            'dry_run' => $schema->boolean()
                ->description('Check the post without sending it.'),
        ];
    }

    public function handle(Request $request, XClient $client): Response
    {
        $validated = $request->validate([
            'text' => ['required', 'string'],
            'image' => ['nullable', 'string'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        try {
            $result = $client->post(
                $validated['text'],
                $validated['image'] ?? null,
                (bool) ($validated['dry_run'] ?? false),
            );
        } catch (XException $exception) {
            return Response::error($exception->getMessage());
        }

        $remaining = $result->remainingToday === null ? '' : " Posts left today: {$result->remainingToday}.";

        return Response::text($result->dryRun
            ? 'Dry run: the post passed every check and was not sent.'.$remaining
            : 'Posted: '.$result->url().$remaining);
    }
}
