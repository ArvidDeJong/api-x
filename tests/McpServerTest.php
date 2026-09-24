<?php

declare(strict_types=1);

use Darvis\ApiX\Mcp\Tools\PostUpdate;
use Darvis\ApiX\Mcp\XServer;
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
