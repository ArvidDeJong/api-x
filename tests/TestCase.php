<?php

namespace Darvis\ApiX\Tests;

use Darvis\ApiX\XServiceProvider;
use Flux\FluxServiceProvider;
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

    protected function fixture(string $name): string
    {
        return __DIR__.'/fixtures/'.$name;
    }
}
