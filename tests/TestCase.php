<?php

namespace Darvis\ApiX\Tests;

use Darvis\ApiX\XServiceProvider;
use Flux\FluxServiceProvider;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Server\McpServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        // Load laravel/mcp, Livewire and Flux like a host app with the MCP server and the page would.
        return [
            LivewireServiceProvider::class,
            FluxServiceProvider::class,
            McpServiceProvider::class,
            XServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Livewire encrypts component snapshots.
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));

        // The default layout belongs to the host app; the test app has its own.
        $app['view']->addLocation(__DIR__.'/fixtures/views');
        $app['config']->set('api_x.ui.layout', 'layouts.x-test');
    }

    protected function setUp(): void
    {
        parent::setUp();

        (require __DIR__.'/../database/migrations/2026_09_24_000000_create_x_posts_table.php')->up();
    }

    /**
     * Give the package a complete set of OAuth 1.0a keys.
     */
    protected function useXCredentials(): void
    {
        config([
            'api_x.credentials.access_token' => 'access-token',
            'api_x.credentials.access_token_secret' => 'access-secret',
            'api_x.credentials.consumer_key' => 'consumer-key',
            'api_x.credentials.consumer_secret' => 'consumer-secret',
        ]);
    }

    /**
     * Fake X for reading mentions: the account is user 10, @ArvidDeJong. Without a body the
     * timeline holds a reply to post 100, a post of the account itself and a plain mention.
     *
     * @param  array<string, mixed>|null  $mentions
     * @param  array<string, mixed>  $more  Extra fakes, for example the tweets endpoint.
     */
    protected function fakeMentions(?array $mentions = null, int $status = 200, array $more = []): void
    {
        Http::fake($more + [
            'api.x.com/2/users/me' => Http::response(['data' => ['id' => '10', 'name' => 'Arvid', 'username' => 'ArvidDeJong']]),
            'api.x.com/2/users/10/mentions*' => Http::response($mentions ?? [
                'data' => [
                    [
                        'id' => '300',
                        'text' => '@ArvidDeJong Looks great, does it work with Laravel 13?',
                        'author_id' => '20',
                        'conversation_id' => '100',
                        'created_at' => '2026-09-28T09:12:00.000Z',
                        'referenced_tweets' => [['type' => 'replied_to', 'id' => '100']],
                    ],
                    [
                        'id' => '299',
                        'text' => 'More in the thread, @ArvidDeJong',
                        'author_id' => '10',
                        'conversation_id' => '100',
                        'created_at' => '2026-09-28T09:00:00.000Z',
                    ],
                    [
                        'id' => '298',
                        'text' => 'Nice package by @ArvidDeJong',
                        'author_id' => '21',
                        'conversation_id' => '298',
                        'created_at' => '2026-09-28T08:45:00.000Z',
                    ],
                ],
                'includes' => ['users' => [
                    ['id' => '20', 'name' => 'Jane Doe', 'username' => 'jane'],
                    ['id' => '21', 'name' => 'Sam', 'username' => 'sam'],
                    ['id' => '10', 'name' => 'Arvid', 'username' => 'ArvidDeJong'],
                ]],
                'meta' => ['newest_id' => '300', 'oldest_id' => '298', 'result_count' => 3],
            ], $status),
        ]);
    }

    protected function fixture(string $name): string
    {
        return __DIR__.'/fixtures/'.$name;
    }
}
