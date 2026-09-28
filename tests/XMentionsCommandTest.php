<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->useXCredentials();
});

it('prints the mentions and the newest id', function () {
    $this->fakeMentions();

    $this->artisan('x:mentions', ['--since' => '250', '--limit' => '20'])
        ->expectsOutputToContain('@jane')
        ->expectsOutputToContain('Looks great, does it work with Laravel 13?')
        ->expectsOutputToContain('https://x.com/jane/status/300')
        ->expectsOutputToContain('Newest id, for --since next time')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/mentions?')
        && ($request->data()['since_id'] ?? null) === '250' && ($request->data()['max_results'] ?? null) === '20');
});

it('says so when there are none', function () {
    $this->fakeMentions(['meta' => ['result_count' => 0]]);

    $this->artisan('x:mentions')
        ->expectsOutputToContain('No mentions.')
        ->assertSuccessful();
});

it('fails with the reason', function () {
    $this->artisan('x:mentions', ['--since' => 'today'])
        ->expectsOutputToContain('since_id must be the id of a post')
        ->assertFailed();
});
