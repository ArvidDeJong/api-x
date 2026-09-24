<?php

declare(strict_types=1);

namespace Darvis\ApiX;

use Darvis\ApiX\Console\Commands\XInstallCommand;
use Darvis\ApiX\Console\Commands\XPostCommand;
use Darvis\ApiX\Mcp\XServer;
use Darvis\ApiX\Support\XConfig;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Server\Registrar;

class XServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/api_x.php', 'api_x');

        $this->app->singleton(XClient::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/api_x.php' => config_path('api_x.php'),
            ], 'api-x-config');

            $this->commands([XInstallCommand::class, XPostCommand::class]);
        }

        $this->registerMcpServer();
    }

    /**
     * Register the local MCP server when laravel/mcp is installed. It stays optional so the
     * package installs on every Laravel version without it.
     */
    protected function registerMcpServer(): void
    {
        if (! XConfig::mcpEnabled() || ! class_exists(Registrar::class)) {
            return;
        }

        $this->callAfterResolving(Registrar::class, function (Registrar $registrar): void {
            $registrar->local(XConfig::mcpHandle(), XServer::class);
        });
    }
}
