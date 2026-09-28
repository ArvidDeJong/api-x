<?php

declare(strict_types=1);

use Darvis\ApiX\Mcp\Tools\ListMentions;
use Darvis\ApiX\Mcp\Tools\PostUpdate;
use Darvis\ApiX\Mcp\XServer;
use Darvis\ApiX\Models\XPost;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Server\Registrar;

beforeEach(function () {
    $this->useXCredentials();
});

it('registers the local server under the configured handle', function () {
    expect(app(Registrar::class)->getLocalServer('x'))->not->toBeNull();
});

it('posts through the tool and returns the link', function () {
    Http::fake([
        'api.x.com/2/tweets' => Http::response(['data' => ['id' => '99']], 201),
    ]);

    XServer::tool(PostUpdate::class, ['text' => 'Hello from the MCP server'])
        ->assertOk()
        ->assertSee('Posted: https://x.com/i/web/status/99')
        ->assertSee('Posts left today: 9.');
});

it('does a dry run through the tool', function () {
    Http::fake();

    XServer::tool(PostUpdate::class, ['text' => 'Checking', 'image' => __DIR__.'/fixtures/pixel.png', 'dry_run' => true])
        ->assertOk()
        ->assertSee('Dry run');

    Http::assertNothingSent();
});

it('returns a refused post as a tool error', function () {
    Http::fake();

    XServer::tool(PostUpdate::class, ['text' => 'See https://arvid.nl'])
        ->assertHasErrors(['X_ALLOW_LINKS']);

    Http::assertNothingSent();
});

it('reads the mentions through the tool', function () {
    $this->fakeMentions();
    XPost::create(['text' => 'Released darvis/api-x 1.4.0', 'status' => 'sent', 'x_id' => '100']);

    XServer::tool(ListMentions::class, [])
        ->assertOk()
        ->assertSee('Post 300 by @jane (Jane Doe), 2026-09-28T09:12:00.000Z')
        ->assertSee('https://x.com/jane/status/300')
        ->assertSee('In reply to your post 100: Released darvis/api-x 1.4.0')
        ->assertSee('Post 298 by @sam (Sam)')
        ->assertSee('Newest id: 300. Pass it as since_id next time');
});

it('says so when there are no new mentions', function () {
    $this->fakeMentions(['meta' => ['result_count' => 0]]);

    XServer::tool(ListMentions::class, ['since_id' => '400'])
        ->assertOk()
        ->assertSee('No mentions newer than 400.');
});

it('refuses a limit outside what X accepts', function () {
    Http::fake();

    XServer::tool(ListMentions::class, ['limit' => 2])->assertHasErrors(['limit']);

    Http::assertNothingSent();
});

it('replies through the tool', function () {
    Http::fake([
        'api.x.com/2/tweets' => Http::response(['data' => ['id' => '99']], 201),
    ]);

    XServer::tool(PostUpdate::class, ['text' => 'Yes, it does.', 'reply_to' => 'https://x.com/jane/status/300'])
        ->assertOk()
        ->assertSee('Replied: https://x.com/i/web/status/99');

    Http::assertSent(fn (Request $request) => $request['reply'] === ['in_reply_to_tweet_id' => '300']);
});
