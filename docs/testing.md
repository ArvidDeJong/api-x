---
title: "Testing"
description: "Test code that posts to X with darvis/api-x without calling X: fake the two endpoints with Http::fake(), use dry runs and test the MCP tool directly."
nav_order: 7
---

# Testing

The package talks to X through Laravel's HTTP client only, so `Http::fake()` covers every call.

## Fake the two endpoints

```php
use Darvis\ApiX\XClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('announces a release on X', function () {
    config([
        'api_x.credentials.access_token' => 'token',
        'api_x.credentials.access_token_secret' => 'secret',
        'api_x.credentials.consumer_key' => 'key',
        'api_x.credentials.consumer_secret' => 'secret',
    ]);

    Http::fake([
        'api.x.com/2/media/upload' => Http::response(['data' => ['id' => '10']]),
        'api.x.com/2/tweets' => Http::response(['data' => ['id' => '20']], 201),
    ]);

    $result = app(XClient::class)->post('Released 1.0.0', base_path('tests/fixtures/card.png'));

    expect($result->url())->toBe('https://x.com/i/web/status/20');

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.x.com/2/tweets'
        && $request['media'] === ['media_ids' => ['10']]);
});
```

## Dry runs

`config(['api_x.dry_run' => true])` makes every post a dry run: every check runs and nothing is sent. Assert with `Http::assertNothingSent()`.

## The daily limit

The limit is counted in the default cache store. Use the `array` store in tests so every test starts at zero.

## The MCP tool

```php
use Darvis\ApiX\Mcp\Tools\PostUpdate;
use Darvis\ApiX\Mcp\XServer;

XServer::tool(PostUpdate::class, ['text' => 'Hello', 'dry_run' => true])
    ->assertOk()
    ->assertSee('Dry run');
```
