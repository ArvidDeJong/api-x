---
title: "Testing"
description: "Test code that posts to X or reads mentions with darvis/api-x without calling X: fake the endpoints with Http::fake(), use dry runs and test the MCP tools."
nav_order: 8
---

# Testing

The package talks to X through Laravel's HTTP client only, so `Http::fake()` covers every call.

## Fake the post endpoints

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

## Fake the mentions

Reading mentions calls two endpoints: `GET /2/users/me` once for the id of the account, then the mentions timeline of that id. The wildcard covers the query string.

```php
Http::fake([
    'api.x.com/2/users/me' => Http::response(['data' => ['id' => '10', 'name' => 'You', 'username' => 'you']]),
    'api.x.com/2/users/10/mentions*' => Http::response([
        'data' => [
            ['id' => '300', 'text' => '@you Nice!', 'author_id' => '20', 'created_at' => '2026-09-28T09:12:00.000Z'],
        ],
        'includes' => ['users' => [['id' => '20', 'name' => 'Jane Doe', 'username' => 'jane']]],
    ]),
    'api.x.com/2/tweets' => Http::response(['data' => ['id' => '400']], 201),
]);

$mention = app(XClient::class)->mentions()[0];
app(XClient::class)->post('Thanks!', replyTo: $mention->id);

Http::assertSent(fn (Request $request) => $request['reply'] === ['in_reply_to_tweet_id' => '300']);
```

The account id is kept in the cache, so with the `array` store every test looks it up again.

## Dry runs

`config(['api_x.dry_run' => true])` makes every post a dry run: every check runs and nothing is sent. Assert with `Http::assertNothingSent()`.

## The daily limit

The limit is counted in the default cache store. Use the `array` store in tests so every test starts at zero.

## The MCP tools

```php
use Darvis\ApiX\Mcp\Tools\ListMentions;
use Darvis\ApiX\Mcp\Tools\PostUpdate;
use Darvis\ApiX\Mcp\XServer;

XServer::tool(PostUpdate::class, ['text' => 'Hello', 'dry_run' => true])
    ->assertOk()
    ->assertSee('Dry run');

XServer::tool(ListMentions::class, ['limit' => 5])
    ->assertOk()
    ->assertSee('Newest id: 300');
```
