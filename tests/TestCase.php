<?php

namespace Darvis\ApiX\Tests;

use Darvis\ApiX\XServiceProvider;
use Laravel\Mcp\Server\McpServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        // Load laravel/mcp like a host app with the MCP server would.
        return [
            McpServiceProvider::class,
            XServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('cache.default', 'array');
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
