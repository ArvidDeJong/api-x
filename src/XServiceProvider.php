<?php

declare(strict_types=1);

namespace Darvis\ApiX;

use Darvis\ApiX\Console\Commands\XInstallCommand;
use Darvis\ApiX\Console\Commands\XPostCommand;
use Darvis\ApiX\Http\Middleware\AuthorizeXPage;
use Darvis\ApiX\Livewire\XPage;
use Darvis\ApiX\Mcp\XServer;
use Darvis\ApiX\Support\XConfig;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Server\Registrar;
use Livewire\Livewire;

class XServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/api_x.php', 'api_x');

        $this->app->singleton(XClient::class);
    }

    public function boot(): void
    {
        // Load the migration automatically, so publishing it is optional.
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'api-x');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/api_x.php' => config_path('api_x.php'),
            ], 'api-x-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'api-x-migrations');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/api-x'),
            ], 'api-x-views');

            $this->commands([XInstallCommand::class, XPostCommand::class]);
        }

        $this->registerMcpServer();
        $this->registerPage();
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

    /**
     * Register the Livewire page and its route. Optional: only when the page is enabled and
     * Livewire is loaded. Checking the container rather than class_exists() also covers an app
     * that has Livewire installed but its provider excluded from discovery.
     */
    protected function registerPage(): void
    {
        if (! XConfig::uiEnabled() || ! class_exists(Livewire::class) || ! $this->app->bound('livewire')) {
            return;
        }

        Livewire::component('api-x-page', XPage::class);

        // Livewire update requests do not run the route middleware again unless it is
        // persistent. Without this the gate would only guard the page load.
        Livewire::addPersistentMiddleware([AuthorizeXPage::class]);

        // After every provider has booted, so Route::livewire() exists and a gate from the
        // host's AppServiceProvider is already defined.
        $this->app->booted(function (): void {
            $this->definePageGate();

            $route = Route::hasMacro('livewire')
                ? Route::livewire(XConfig::uiPath(), XPage::class)
                : Route::get(XConfig::uiPath(), XPage::class);

            // The gate comes last, so it sees the user the configured middleware logged in.
            $route->middleware([...XConfig::uiMiddleware(), AuthorizeXPage::class])->name('api-x.page');
        });
    }

    /**
     * Define the `postToX` gate unless the host application has its own. The default only
     * allows the local environment. The user is nullable, otherwise Laravel never calls the
     * gate for a guest.
     */
    protected function definePageGate(): void
    {
        if (Gate::has(AuthorizeXPage::ABILITY)) {
            return;
        }

        Gate::define(
            AuthorizeXPage::ABILITY,
            fn (?Authenticatable $user = null): bool => $this->app->environment('local'),
        );
    }
}
