<?php

declare(strict_types=1);

use Darvis\ApiX\Exceptions\XException;
use Darvis\ApiX\XClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function fakeX(): void
{
    Http::fake([
        'api.x.com/2/media/upload' => Http::response(['data' => ['id' => '1880028106020515840', 'media_key' => '3_1880028106020515840']]),
        'api.x.com/2/tweets' => Http::response(['data' => ['id' => '1445880548472328192', 'text' => 'ok']], 201),
    ]);
}

beforeEach(function () {
    $this->useXCredentials();
});

it('posts a text with a signed request', function () {
    fakeX();

    $result = app(XClient::class)->post('  Working on darvis/api-x today  ');

    expect($result->dryRun)->toBeFalse()
        ->and($result->id)->toBe('1445880548472328192')
        ->and($result->text)->toBe('Working on darvis/api-x today')
        ->and($result->url())->toBe('https://x.com/i/web/status/1445880548472328192')
        ->and($result->mediaId)->toBeNull();

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.x.com/2/tweets'
        && $request->method() === 'POST'
        && $request->data() === ['text' => 'Working on darvis/api-x today']
        && str_starts_with($request->header('Authorization')[0], 'OAuth ')
        && str_contains($request->header('Authorization')[0], 'oauth_consumer_key="consumer-key"'));
});

it('uploads an image first and attaches its media id', function () {
    fakeX();

    $result = app(XClient::class)->post('New release', $this->fixture('pixel.png'));

    expect($result->mediaId)->toBe('1880028106020515840');

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.x.com/2/media/upload'
        && $request->isMultipart()
        && collect($request->data())->contains(fn ($part) => $part['name'] === 'media_category' && $part['contents'] === 'tweet_image')
        && collect($request->data())->contains(fn ($part) => $part['name'] === 'media' && str_starts_with($part['contents'], "\x89PNG")));

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.x.com/2/tweets'
        && $request['media'] === ['media_ids' => ['1880028106020515840']]);
});

it('downloads an image given as a URL', function () {
    Http::fake([
        'opengraph.githubassets.com/*' => Http::response((string) file_get_contents($this->fixture('pixel.png'))),
        'api.x.com/2/media/upload' => Http::response(['data' => ['id' => '42']]),
        'api.x.com/2/tweets' => Http::response(['data' => ['id' => '43']], 201),
    ]);

    $result = app(XClient::class)->post('Release card', 'https://opengraph.githubassets.com/1/ArvidDeJong/api-x/releases/tag/v1.0.0');

    expect($result->mediaId)->toBe('42')->and($result->id)->toBe('43');
});

it('checks everything but sends nothing in a dry run', function () {
    Http::fake();

    $result = app(XClient::class)->post('Just checking', $this->fixture('pixel.png'), dryRun: true);

    expect($result->dryRun)->toBeTrue()->and($result->id)->toBeNull()->and($result->url())->toBeNull();
    Http::assertNothingSent();
});

it('honours dry run from the config', function () {
    Http::fake();
    config(['api_x.dry_run' => true]);

    expect(app(XClient::class)->post('Just checking')->dryRun)->toBeTrue();
    Http::assertNothingSent();
});

it('refuses a post without credentials before calling X', function () {
    Http::fake();
    config(['api_x.credentials.access_token' => null]);

    app(XClient::class)->post('Hello');
})->throws(XException::class, 'X credentials are missing');

it('refuses an empty text', function () {
    app(XClient::class)->post('   ');
})->throws(XException::class, 'The post text is empty.');

it('refuses a text that is too long for X', function () {
    app(XClient::class)->post(str_repeat('a', 281));
})->throws(XException::class, 'counts 281 characters');

it('refuses a link unless links are allowed', function () {
    Http::fake();

    expect(fn () => app(XClient::class)->post('Read more on arvid.nl'))
        ->toThrow(XException::class, 'X_ALLOW_LINKS');

    config(['api_x.allow_links' => true]);

    expect(app(XClient::class)->post('Read more on arvid.nl', dryRun: true)->dryRun)->toBeTrue();
});

it('stops at the daily limit and counts only real posts', function () {
    fakeX();
    config(['api_x.daily_limit' => 2]);
    $client = app(XClient::class);

    $client->post('dry', dryRun: true);
    expect($client->remainingToday())->toBe(2);

    expect($client->post('one')->remainingToday)->toBe(1);
    expect($client->post('two')->remainingToday)->toBe(0);

    expect(fn () => $client->post('three'))->toThrow(XException::class, 'daily limit of 2 posts');
    Http::assertSentCount(2);
});

it('has no limit when the daily limit is 0', function () {
    fakeX();
    config(['api_x.daily_limit' => 0]);

    expect(app(XClient::class)->post('free')->remainingToday)->toBeNull()
        ->and(app(XClient::class)->remainingToday())->toBeNull();
});

it('refuses a missing file, a file that is not an image and an image over 5 MB', function () {
    Http::fake();
    $client = app(XClient::class);

    expect(fn () => $client->post('x', '/does/not/exist.png'))->toThrow(XException::class, 'could not be read');
    expect(fn () => $client->post('x', $this->fixture('not-an-image.txt')))->toThrow(XException::class, 'text/plain is not supported');

    $large = tempnam(sys_get_temp_dir(), 'apix');
    file_put_contents($large, file_get_contents($this->fixture('pixel.png')).str_repeat("\0", XClient::MAX_IMAGE_BYTES));

    expect(fn () => $client->post('x', $large))->toThrow(XException::class, 'X accepts at most 5 MB');

    unlink($large);
    Http::assertNothingSent();
});

it('passes the reason X gives when it refuses', function () {
    Http::fake([
        'api.x.com/2/tweets' => Http::response(['title' => 'Forbidden', 'detail' => 'You are not permitted to perform this action.'], 403),
    ]);

    app(XClient::class)->post('Hello');
})->throws(XException::class, 'X refused to create the post (HTTP 403): You are not permitted to perform this action.');

it('does not post when the image upload fails', function () {
    Http::fake([
        'api.x.com/2/media/upload' => Http::response(['errors' => [['message' => 'Bad image']]], 400),
        'api.x.com/2/tweets' => Http::response(['data' => ['id' => '1']], 201),
    ]);

    expect(fn () => app(XClient::class)->post('Hello', $this->fixture('pixel.png')))
        ->toThrow(XException::class, 'X refused to upload the image (HTTP 400): Bad image');

    Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/2/tweets'));
});

it('tells which account the keys belong to', function () {
    Http::fake([
        'api.x.com/2/users/me' => Http::response(['data' => ['id' => '12', 'name' => 'Arvid', 'username' => 'arviddejong']]),
    ]);

    expect(app(XClient::class)->account())->toBe(['id' => '12', 'name' => 'Arvid', 'username' => 'arviddejong']);

    Http::assertSent(fn (Request $request) => $request->method() === 'GET'
        && str_starts_with($request->header('Authorization')[0], 'OAuth '));
});

it('passes the reason when X refuses the keys', function () {
    Http::fake([
        'api.x.com/2/users/me' => Http::response(['title' => 'Unauthorized', 'detail' => 'Unauthorized'], 401),
    ]);

    app(XClient::class)->account();
})->throws(XException::class, 'X refused to check the keys (HTTP 401): Unauthorized');

it('stops with a clear message when the cache that counts posts fails', function () {
    Http::fake();
    config(['api_x.cache_store' => 'missing']);

    expect(fn () => app(XClient::class)->post('Hello', dryRun: true))
        ->toThrow(XException::class, 'The daily limit needs a working cache');

    Http::assertNothingSent();
});

it('counts in the configured cache store', function () {
    fakeX();
    config(['api_x.cache_store' => 'file']);
    cache()->store('file')->forget('api_x:posts:'.now()->toDateString());

    app(XClient::class)->post('one');

    expect(cache()->store('file')->get('api_x:posts:'.now()->toDateString()))->toBe(1)
        ->and(cache()->store('array')->get('api_x:posts:'.now()->toDateString()))->toBeNull();

    cache()->store('file')->forget('api_x:posts:'.now()->toDateString());
});

it('says why an image URL could not be read', function () {
    Http::fake(['opengraph.githubassets.com/*' => Http::response('Too many requests, please try again later.', 429)]);

    app(XClient::class)->post('Card', 'https://opengraph.githubassets.com/1/ArvidDeJong/api-x', dryRun: true);
})->throws(XException::class, 'The image could not be read: https://opengraph.githubassets.com/1/ArvidDeJong/api-x (HTTP 429)');
