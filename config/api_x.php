<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Links
    |--------------------------------------------------------------------------
    |
    | X bills a post that contains a link at a much higher rate than a plain
    | post. Posts with a link are refused unless this is switched on.
    |
    */

    'allow_links' => (bool) env('X_ALLOW_LINKS', false),

    /*
    |--------------------------------------------------------------------------
    | API
    |--------------------------------------------------------------------------
    */

    'api_url' => env('X_API_URL', 'https://api.x.com'),

    /*
    |--------------------------------------------------------------------------
    | Cache store
    |--------------------------------------------------------------------------
    |
    | Where the posts per day are counted. Null uses the default store. With
    | CACHE_STORE=database the cache table must exist (php artisan migrate).
    |
    */

    'cache_store' => env('X_CACHE_STORE'),

    /*
    |--------------------------------------------------------------------------
    | Credentials
    |--------------------------------------------------------------------------
    |
    | OAuth 1.0a user context keys of your own X account, from Keys & Tokens,
    | section "OAuth 1.0 Keys" in the X developer console. Generate the access
    | token after giving the app Read and write permissions. They don't expire.
    |
    */

    'credentials' => [
        'access_token' => env('X_ACCESS_TOKEN'),
        'access_token_secret' => env('X_ACCESS_TOKEN_SECRET'),
        'consumer_key' => env('X_CONSUMER_KEY'),
        'consumer_secret' => env('X_CONSUMER_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Safety
    |--------------------------------------------------------------------------
    |
    | daily_limit: the most posts per day, counted in the cache. 0 switches
    | the limit off. dry_run: validate everything but never call X.
    |
    */

    'daily_limit' => (int) env('X_DAILY_LIMIT', 10),

    'dry_run' => (bool) env('X_DRY_RUN', false),

    /*
    |--------------------------------------------------------------------------
    | MCP server
    |--------------------------------------------------------------------------
    |
    | Registers a local (stdio) MCP server when laravel/mcp is installed.
    | Start it with: php artisan mcp:start <handle>
    |
    */

    'mcp' => [
        'enabled' => (bool) env('X_MCP_ENABLED', true),
        'handle' => env('X_MCP_HANDLE', 'x'),
    ],

    'timeout' => (int) env('X_TIMEOUT', 30),

];
