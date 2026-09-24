# darvis/api-x

[![Latest Version](https://img.shields.io/packagist/v/darvis/api-x.svg)](https://packagist.org/packages/darvis/api-x)
[![Tests](https://github.com/ArvidDeJong/api-x/actions/workflows/tests.yml/badge.svg)](https://github.com/ArvidDeJong/api-x/actions/workflows/tests.yml)
[![PHP](https://img.shields.io/packagist/dependency-v/darvis/api-x/php.svg)](composer.json)
[![License](https://img.shields.io/packagist/l/darvis/api-x.svg)](LICENSE)

A Laravel package that posts short updates, with one optional image, to **X** (formerly
Twitter) through the X API v2. Call it from PHP, from the command line, or let an AI agent
such as Claude post for you through a local MCP server. No SDK: every call goes through
Laravel's built-in HTTP client.

Unofficial and independent: this is not an official X product and is not affiliated with X Corp.

## Features

- A setup wizard, `php artisan x:install`, that explains every step and writes `.env`
- One call to post a text with a JPEG, PNG, GIF or WebP image, from a local path or a URL
- `php artisan x:post` for scripts and the terminal
- A local MCP server with a `post-update` tool, registered when `laravel/mcp` is installed
- Checks the text the way X counts it before anything is sent or billed
- Refuses links by default, because X bills posts with a link at a much higher rate
- A daily limit and a dry run mode, so an agent cannot run up a bill
- Ships a Laravel Boost guideline and skill

## Requirements

- PHP 8.2 or higher
- Laravel 11, 12 or 13
- An X developer app with **Read and Write** permissions and API credit
- `laravel/mcp` 1.0 or higher for the MCP server (Laravel Boost installs it)

## Installation

```bash
composer require darvis/api-x
```

Run the setup wizard. It explains how to create the X app, writes the four OAuth 1.0a keys of
your own account to `.env`, checks them with X, sets the safety limits and adds the MCP server:

```bash
php artisan x:install
```

Or put the keys in `.env` yourself:

```dotenv
X_CONSUMER_KEY=your-consumer-key
X_CONSUMER_SECRET=your-consumer-secret
X_ACCESS_TOKEN=your-access-token
X_ACCESS_TOKEN_SECRET=your-access-token-secret
```

[Installation & configuration](https://arviddejong.github.io/api-x/installation.html)
has every step and every config key.

## Quick start

```bash
php artisan x:post "Working on something new" --image=storage/app/screenshot.png --dry-run
```

```php
use Darvis\ApiX\XClient;

$result = app(XClient::class)->post('Working on something new', storage_path('app/screenshot.png'));

$result->url(); // https://x.com/i/web/status/...
```

For an AI agent, add `php artisan mcp:start x` as a stdio MCP server. See the
[MCP server](https://arviddejong.github.io/api-x/mcp-server.html) page.

## Documentation

The full documentation is at [arviddejong.github.io/api-x](https://arviddejong.github.io/api-x/).

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

## Security

See [SECURITY.md](SECURITY.md).

## License

MIT. See [LICENSE](LICENSE).
