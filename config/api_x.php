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
    | History
    |--------------------------------------------------------------------------
    |
    | Store every post that went to X (sent or refused by X) in the x_posts
    | table, so the page shows what the command and the MCP tool posted too.
    |
    */

    'history' => [
        'enabled' => (bool) env('X_HISTORY_ENABLED', true),
    ],

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

    /*
    |--------------------------------------------------------------------------
    | Page
    |--------------------------------------------------------------------------
    |
    | A Livewire page to post and to see the history, registered when Livewire
    | and Flux are installed. Only visitors the postToX gate allows get in;
    | without a gate of your own that is the local environment only.
    |
    */

    'ui' => [
        'enabled' => (bool) env('X_UI_ENABLED', true),
        // Blade layout the page is rendered in.
        'layout' => env('X_UI_LAYOUT', 'layouts::app'),
        // Comma separated middleware, for example "web,auth".
        'middleware' => array_values(array_filter(array_map('trim', explode(',', (string) env('X_UI_MIDDLEWARE', 'web'))))),
        'per_page' => (int) env('X_UI_PER_PAGE', 25),
        // Path of the page, for example https://app.test/x
        'route' => env('X_UI_ROUTE', 'x'),
    ],

];
