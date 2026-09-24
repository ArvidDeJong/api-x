# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository. The conventions shared by every darvis package (language, releases, CI, docs site, Boost guidelines, public API policy) are in [../CLAUDE.md](../CLAUDE.md); this file only holds what is specific to this package.

## What this is

`darvis/api-x` is a Laravel package that posts text with one optional image to X through the X API v2, from PHP, from `php artisan x:post` and from a local MCP server.

- Namespace: `Darvis\ApiX\` → `src/`
- Service provider auto-registered via `extra.laravel.providers` in [composer.json](composer.json)
- Config key: `api_x` (file `config/api_x.php`), read only through [XConfig](src/Support/XConfig.php)
- `XClient` is a container singleton

**No external HTTP dependency, by design.** Everything goes through `Illuminate\Support\Facades\Http`. Do not add an X SDK or an OAuth library; [OAuth1](src/Support/OAuth1.php) signs requests and is tested against the example in the X documentation.

## Architecture

- [XClient](src/XClient.php) is the one entry point. The command and the MCP tool call `post()` and add nothing but output. Keep every rule in `XClient`, so the three stay in step.
- `post()` checks text, links, daily limit and image before the dry run check, so a dry run fails where a real post would. Credentials are checked after the dry run check, so a dry run works without keys.
- Only the query string is signed. Both requests use a body that OAuth 1.0a does not sign (multipart for the upload, JSON for the post); never switch one to form encoding without signing the fields.
- [PostText](src/Support/PostText.php) counts length and finds links. The link pattern decides what gets refused while `allow_links` is off; a false negative costs money, a false positive only a refused post, so err towards refusing.
- `laravel/mcp` is a dev dependency and a suggestion, not a requirement: `laravel/mcp` needs `illuminate/json-schema` 12.41 or higher, and a hard requirement would lock out Laravel 11 hosts. The provider registers the server only when the `Registrar` class exists.
- [XInstallCommand](src/Console/Commands/XInstallCommand.php) is the setup wizard, built like `mailtrap:install`: one method per step, every answer written to `.env` at once through [EnvironmentFile](src/Support/EnvironmentFile.php) and applied to the running config, a summary and one star question at the end of the interactive run only. Without interaction it touches only what the options name. It never posts for real; the last step is a dry run.
- [XPage](src/Livewire/XPage.php) is the page, built like the Mailtrap inbox: registered only when Livewire is bound, the `postToX` gate in [AuthorizeXPage](src/Http/Middleware/AuthorizeXPage.php) as persistent middleware and again in `boot()`, `render()` and `post()`. The default gate allows `local` only; never make it more permissive.
- `XClient::record()` writes the history after X answered. It reports a database failure and carries on: the history never decides whether a post goes out.
- The daily count is `api_x:posts:<date>` in the default cache store. Only a successful post counts.

## Conventions specific to this package

- Every message a user can meet is quoted in `docs/troubleshooting.md`; `tests/DocsSiteTest.php` checks the list.
- The index, the README and the FAQ say the package is unofficial; don't use X's logo.
- The MCP tool returns an `XException` as `Response::error()` with the same message, so an agent can correct itself. Don't catch other exceptions there.
