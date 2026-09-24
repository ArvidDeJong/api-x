---
title: "Quick start"
description: "Post your first update with an image to X from Laravel in five minutes: install darvis/api-x, set the keys, run a dry run and then post for real."
nav_order: 3
---

# Quick start

This assumes the keys are in `.env`. The quickest way there is `php artisan x:install`; see [Installation & configuration](installation.md).

## From the command line

```bash
php artisan x:post "Working on a new Laravel package today" --image=storage/app/screenshot.png --dry-run
php artisan x:post "Working on a new Laravel package today" --image=storage/app/screenshot.png
```

The second command prints the link to the post and how many posts are left today.

## From PHP

```php
use Darvis\ApiX\Exceptions\XException;
use Darvis\ApiX\XClient;

try {
    $result = app(XClient::class)->post(
        'darvis/api-x 1.0.0 is out: post to X from Laravel and from your AI agent.',
        'https://opengraph.githubassets.com/1/ArvidDeJong/api-x/releases/tag/v1.0.0',
    );

    logger()->info('Posted on X', ['url' => $result->url()]);
} catch (XException $exception) {
    report($exception);
}
```

The image URL above is the release card GitHub makes for every release. Any JPEG, PNG, GIF or WebP up to 5 MB works, as a local path or a URL.

## From your AI agent

With `laravel/mcp` installed, point your agent at `php artisan mcp:start x` and ask it to post. See [MCP server](mcp-server.md).
