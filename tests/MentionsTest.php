<?php

declare(strict_types=1);

use Darvis\ApiX\Exceptions\XException;
use Darvis\ApiX\Mention;
use Darvis\ApiX\Models\XPost;
use Darvis\ApiX\XClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->useXCredentials();
});

it('reads the mentions with a signed request and leaves out the account itself', function () {
    $this->fakeMentions();

    $mentions = app(XClient::class)->mentions();

    expect($mentions)->toHaveCount(2)
        ->and($mentions[0])->toBeInstanceOf(Mention::class)
        ->and($mentions[0]->id)->toBe('300')
        ->and($mentions[0]->text)->toBe('@ArvidDeJong Looks great, does it work with Laravel 13?')
        ->and($mentions[0]->authorId)->toBe('20')
        ->and($mentions[0]->authorUsername)->toBe('jane')
        ->and($mentions[0]->authorName)->toBe('Jane Doe')
        ->and($mentions[0]->createdAt)->toBe('2026-09-28T09:12:00.000Z')
        ->and($mentions[0]->conversationId)->toBe('100')
        ->and($mentions[0]->inReplyToId)->toBe('100')
        ->and($mentions[0]->url())->toBe('https://x.com/jane/status/300')
        ->and($mentions[1]->id)->toBe('298')
        ->and($mentions[1]->inReplyToId)->toBeNull();

    Http::assertSent(fn (Request $request) => $request->method() === 'GET'
        && str_starts_with($request->url(), 'https://api.x.com/2/users/10/mentions?')
        && ($request->data()['max_results'] ?? null) === '10'
        && $request['expansions'] === 'author_id'
        && $request['tweet.fields'] === 'author_id,conversation_id,created_at,referenced_tweets'
        && str_starts_with($request->header('Authorization')[0], 'OAuth ')
        && str_contains($request->header('Authorization')[0], 'oauth_consumer_key="consumer-key"'));
});

it('looks the account id up once and keeps it', function () {
    $this->fakeMentions();

    app(XClient::class)->mentions();
    app(XClient::class)->mentions();

    Http::assertSentCount(3);
    expect(collect(Http::recorded())->filter(fn (array $pair) => $pair[0]->url() === 'https://api.x.com/2/users/me'))->toHaveCount(1);
});

it('passes since_id and keeps the limit between 5 and 100', function () {
    $this->fakeMentions(['meta' => ['result_count' => 0]]);

    expect(app(XClient::class)->mentions('290', 500))->toBe([]);
    app(XClient::class)->mentions(null, 1);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/mentions?')
        && ($request->data()['since_id'] ?? null) === '290' && ($request->data()['max_results'] ?? null) === '100');
    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/mentions?')
        && ! isset($request->data()['since_id']) && ($request->data()['max_results'] ?? null) === '5');
});

it('refuses a since_id that is not a post id before calling X', function () {
    Http::fake();

    app(XClient::class)->mentions('yesterday');
})->throws(XException::class, 'since_id must be the id of a post, only digits: yesterday');

it('gives a reply to one of the own posts its text from the history', function () {
    $this->fakeMentions();
    XPost::create(['text' => 'Released darvis/api-x 1.4.0', 'status' => XPost::STATUS_SENT, 'x_id' => '100']);

    $mentions = app(XClient::class)->mentions();

    expect($mentions[0]->inReplyToText)->toBe('Released darvis/api-x 1.4.0')
        ->and($mentions[1]->inReplyToText)->toBeNull();
});

it('says why X refused to read the mentions', function () {
    $this->fakeMentions(['title' => 'Unauthorized', 'detail' => 'Unauthorized'], 401);

    app(XClient::class)->mentions();
})->throws(XException::class, 'X refused to read the mentions (HTTP 401): Unauthorized');

it('needs the keys to read the mentions', function () {
    Http::fake();
    config(['api_x.credentials.access_token' => null]);

    app(XClient::class)->mentions();
})->throws(XException::class, 'X credentials are missing.');
