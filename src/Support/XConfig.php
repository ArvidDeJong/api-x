<?php

declare(strict_types=1);

namespace Darvis\ApiX\Support;

/**
 * The one place that reads the package config. Callers ask this class, so a default is written
 * once and a caller cannot quietly disagree with the config file about what it is.
 */
final class XConfig
{
    /**
     * Whether a post may contain a link. X bills those at a much higher rate.
     */
    public static function allowLinks(): bool
    {
        return (bool) config('api_x.allow_links', false);
    }

    /**
     * Base URL of the X API, without a trailing slash.
     */
    public static function apiUrl(): string
    {
        return rtrim((string) config('api_x.api_url', 'https://api.x.com'), '/');
    }

    /**
     * Cache store that counts the posts per day, or null for the default store.
     */
    public static function cacheStore(): ?string
    {
        $store = config('api_x.cache_store');

        return is_string($store) && $store !== '' ? $store : null;
    }

    /**
     * The four OAuth 1.0a keys, or null when one of them is missing.
     *
     * @return array{access_token: string, access_token_secret: string, consumer_key: string, consumer_secret: string}|null
     */
    public static function credentials(): ?array
    {
        $credentials = [];

        foreach (['access_token', 'access_token_secret', 'consumer_key', 'consumer_secret'] as $key) {
            $value = config('api_x.credentials.'.$key);

            if (! is_string($value) || $value === '') {
                return null;
            }

            $credentials[$key] = $value;
        }

        return $credentials;
    }

    /**
     * The most posts per day. 0 switches the limit off.
     */
    public static function dailyLimit(): int
    {
        return max(0, (int) config('api_x.daily_limit', 10));
    }

    /**
     * Whether posts are validated but never sent.
     */
    public static function dryRun(): bool
    {
        return (bool) config('api_x.dry_run', false);
    }

    /**
     * Whether posts that went to X are stored in the x_posts table.
     */
    public static function historyEnabled(): bool
    {
        return (bool) config('api_x.history.enabled', true);
    }

    /**
     * Whether the local MCP server is registered (only when laravel/mcp is installed).
     */
    public static function mcpEnabled(): bool
    {
        return (bool) config('api_x.mcp.enabled', true);
    }

    /**
     * The handle for php artisan mcp:start.
     */
    public static function mcpHandle(): string
    {
        return (string) config('api_x.mcp.handle', 'x');
    }

    /**
     * The X subscription of the posting account. An unknown value counts as none, so a typo
     * never lets a post through that X would refuse.
     */
    public static function subscription(): Subscription
    {
        return Subscription::tryFrom(strtolower(trim((string) config('api_x.subscription', 'none'))))
            ?? Subscription::None;
    }

    /**
     * Timeout in seconds for a call to the X API.
     */
    public static function timeout(): int
    {
        return (int) config('api_x.timeout', 30);
    }

    /**
     * Whether the page is registered (only when Livewire is loaded).
     */
    public static function uiEnabled(): bool
    {
        return (bool) config('api_x.ui.enabled', true);
    }

    /**
     * Blade layout the page is rendered in.
     */
    public static function uiLayout(): string
    {
        return (string) config('api_x.ui.layout', 'layouts::app');
    }

    /**
     * Middleware the page route runs through, before the gate.
     *
     * @return array<int, string>
     */
    public static function uiMiddleware(): array
    {
        $middleware = config('api_x.ui.middleware', ['web']);

        return array_values(array_filter(array_map('strval', (array) $middleware)));
    }

    /**
     * Rows per page in the history.
     */
    public static function uiPerPage(): int
    {
        return max(1, (int) config('api_x.ui.per_page', 25));
    }

    /**
     * The page as a path with a leading slash.
     */
    public static function uiPath(): string
    {
        return '/'.ltrim((string) config('api_x.ui.route', 'x'), '/');
    }
}
