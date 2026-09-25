# Changelog

All notable changes to `darvis/api-x` are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.3.0] - 2026-09-25

### Added

- `X_SUBSCRIPTION` (`none`, `basic`, `premium` or `premium_plus`) sets the X subscription of the posting account. With any paid tier a post may count up to 25,000 characters instead of 280, in `XClient::post()`, `x:post`, the MCP tool and the page counter. Read it with `XConfig::subscription()`, which returns the new `Darvis\ApiX\Support\Subscription` enum.
- `x:install` asks for the subscription in a new step, and takes `--subscription=` without interaction.

### Changed

- A text above 280 characters on an account without a subscription now ends with a hint: `With an X subscription, set X_SUBSCRIPTION to allow up to 25,000.`
- The MCP tool description states the length limit of the configured account instead of a fixed 280.

## [1.2.0] - 2026-09-24

### Changed

- An image URL that does not answer now says why in the message, for example `The image could not be read: https://… (HTTP 429)`, and a connection error there is an `XException` too.

## [1.1.0] - 2026-09-24

### Added

- A Livewire page at `/x` to post from the browser, with a counter that counts the way X does, an image upload or URL and a dry run switch. Registered when Livewire and Flux are installed; only visitors the `postToX` gate allows get in, by default the local environment only.
- History: every post that went to X is stored in the new `x_posts` table (`Darvis\ApiX\Models\XPost`), also from `x:post` and the MCP tool, and listed on the page. Run `php artisan migrate`. `X_HISTORY_ENABLED=false` switches it off.

### Changed

- A timeout or connection error while posting is now an `XException` ("The request to X failed: …") instead of a Laravel `ConnectionException`.

## [1.0.0] - 2026-09-24

First release.

### Added

- `XClient::post($text, $image, $dryRun)` posts a text with one optional image (local path or URL, JPEG, PNG, GIF or WebP up to 5 MB) through the X API v2, signed with OAuth 1.0a user context keys.
- Checks before anything is sent: the weighted length X uses, links (refused unless `X_ALLOW_LINKS=true`), a daily limit (`X_DAILY_LIMIT`, default 10) and the image.
- `X_CACHE_STORE` picks the cache store that counts the posts per day. A failing store stops a post with a clear message instead of a database error.
- Dry runs through the argument, `--dry-run` or `X_DRY_RUN=true`.
- `php artisan x:install`, a setup wizard in six steps (X app, keys, a check with X, safety settings, MCP server, dry run) that writes every answer to `.env`, with options and `--no-interaction` for scripts.
- The wizard names every key the way the X developer console does (Consumer Key, Consumer Secret, Access Token, Access Token Secret) and says where to find it.
- The wizard refuses a key pasted twice and an Access Token that does not start with the account id, before X answers with a bare 401.
- `XClient::account()` returns the account the keys belong to (`GET /2/users/me`).
- `php artisan x:post {text} --image= --dry-run`.
- A local MCP server with the `post-update` tool, registered as `php artisan mcp:start x` when `laravel/mcp` is installed.
- Laravel Boost guideline and `api-x-development` skill.

[Unreleased]: https://github.com/ArvidDeJong/api-x/compare/v1.3.0...HEAD
[1.3.0]: https://github.com/ArvidDeJong/api-x/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/ArvidDeJong/api-x/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/ArvidDeJong/api-x/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/ArvidDeJong/api-x/releases/tag/v1.0.0
