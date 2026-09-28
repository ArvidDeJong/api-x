<?php

declare(strict_types=1);

use Darvis\ApiX\Exceptions\XException;
use Darvis\ApiX\XClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->useXCredentials();

    Http::fake([
        'api.x.com/2/tweets' => Http::response(['data' => ['id' => '400']], 201),
    ]);
});

it('posts a reply to the post with that id', function () {
    $result = app(XClient::class)->post('Yes, Laravel 11 to 13.', replyTo: '300');

    expect($result->replyTo)->toBe('300')->and($result->url())->toBe('https://x.com/i/web/status/400');

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.x.com/2/tweets'
        && $request->data() === ['text' => 'Yes, Laravel 11 to 13.', 'reply' => ['in_reply_to_tweet_id' => '300']]);
});

it('takes a link to the post as well', function (string $link) {
    expect(app(XClient::class)->post('Thanks!', replyTo: $link)->replyTo)->toBe('300');
})->with([
    'x.com' => 'https://x.com/jane/status/300',
    'with a query string' => 'https://x.com/jane/status/300?s=20',
    'twitter.com' => 'https://twitter.com/jane/status/300',
    'web link' => 'https://x.com/i/web/status/300',
]);

it('refuses a reply target that is not a post, also in a dry run', function () {
    app(XClient::class)->post('Thanks!', dryRun: true, replyTo: 'https://example.com/300');
})->throws(XException::class, 'The post to reply to must be a post id or a link to a post on X: https://example.com/300');

it('checks a reply in a dry run without sending it', function () {
    $result = app(XClient::class)->post('Thanks!', dryRun: true, replyTo: '300');

    expect($result->dryRun)->toBeTrue()->and($result->replyTo)->toBe('300');
    Http::assertNothingSent();
});

it('counts a reply against the daily limit', function () {
    config(['api_x.daily_limit' => 1]);

    app(XClient::class)->post('First', replyTo: '300');
    app(XClient::class)->post('Second', replyTo: '300');
})->throws(XException::class, 'The daily limit of 1 posts is reached.');

it('treats an empty reply target as a normal post', function () {
    expect(app(XClient::class)->post('Hello', replyTo: '  ')->replyTo)->toBeNull();

    Http::assertSent(fn (Request $request) => $request->data() === ['text' => 'Hello']);
});
