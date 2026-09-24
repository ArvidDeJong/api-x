<?php

declare(strict_types=1);

use Darvis\ApiX\XServiceProvider;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->useXCredentials();
});

it('posts from the command line', function () {
    Http::fake([
        'api.x.com/2/tweets' => Http::response(['data' => ['id' => '7']], 201),
    ]);

    $this->artisan('x:post', ['text' => 'Released darvis/api-x 1.0.0'])
        ->expectsOutputToContain('Posted: https://x.com/i/web/status/7')
        ->assertSuccessful();
});

it('does a dry run with an image', function () {
    Http::fake();

    $this->artisan('x:post', ['text' => 'Checking', '--image' => __DIR__.'/fixtures/pixel.png', '--dry-run' => true])
        ->expectsOutputToContain('Dry run')
        ->assertSuccessful();

    Http::assertNothingSent();
});

it('fails with the reason', function () {
    $this->artisan('x:post', ['text' => str_repeat('a', 300)])
        ->expectsOutputToContain('counts 300 characters')
        ->assertFailed();
});

it('publishes the config under its tag', function () {
    expect(XServiceProvider::pathsToPublish(XServiceProvider::class, 'api-x-config'))
        ->toHaveCount(1)
        ->and(array_values(XServiceProvider::pathsToPublish(XServiceProvider::class, 'api-x-config'))[0])
        ->toEndWith('api_x.php');
});
