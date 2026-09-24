<?php

declare(strict_types=1);

use Darvis\ApiX\Exceptions\XException;
use Darvis\ApiX\Models\XPost;
use Darvis\ApiX\XClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->useXCredentials();
});

it('stores a sent post with its link', function () {
    Http::fake([
        'api.x.com/2/media/upload' => Http::response(['data' => ['id' => '5']]),
        'api.x.com/2/tweets' => Http::response(['data' => ['id' => '6']], 201),
    ]);

    app(XClient::class)->post('Hello', $this->fixture('pixel.png'));

    $post = XPost::sole();

    expect($post->status)->toBe(XPost::STATUS_SENT)
        ->and($post->text)->toBe('Hello')
        ->and($post->x_id)->toBe('6')
        ->and($post->media_id)->toBe('5')
        ->and($post->image)->toEndWith('pixel.png')
        ->and($post->url())->toBe('https://x.com/i/web/status/6');
});

it('stores a post X refused, with the reason', function () {
    Http::fake([
        'api.x.com/2/tweets' => Http::response(['detail' => 'Duplicate content.'], 403),
    ]);

    expect(fn () => app(XClient::class)->post('Hello'))->toThrow(XException::class);

    expect(XPost::sole())
        ->status->toBe(XPost::STATUS_FAILED)
        ->error->toContain('Duplicate content.')
        ->x_id->toBeNull();
});

it('turns a failed connection into an XException and stores it', function () {
    Http::fake(fn () => throw new ConnectionException('timed out'));

    expect(fn () => app(XClient::class)->post('Hello'))->toThrow(XException::class, 'The request to X failed: timed out');

    expect(XPost::sole()->status)->toBe(XPost::STATUS_FAILED);
});

it('stores neither dry runs nor posts refused before calling X', function () {
    Http::fake();

    app(XClient::class)->post('Just checking', dryRun: true);
    expect(fn () => app(XClient::class)->post(str_repeat('a', 300)))->toThrow(XException::class);

    expect(XPost::count())->toBe(0);
});

it('still posts without the table and without history', function () {
    Http::fake(['api.x.com/2/tweets' => Http::response(['data' => ['id' => '7']], 201)]);

    Schema::drop('x_posts');
    expect(app(XClient::class)->post('No table')->id)->toBe('7');

    (require __DIR__.'/../database/migrations/2026_09_24_000000_create_x_posts_table.php')->up();
    config(['api_x.history.enabled' => false]);
    app(XClient::class)->post('No history');

    expect(XPost::count())->toBe(0);
});
