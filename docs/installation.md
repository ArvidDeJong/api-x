---
title: "Installation & configuration"
description: "Install darvis/api-x step by step: create the X app with Read and Write permissions, set the four keys in .env, check it works and look up every config key."
nav_order: 2
---

# Installation & configuration

## Requirements

- PHP 8.2 or higher
- Laravel 11, 12 or 13
- An X developer account with an app and API credit
- For the MCP server: `laravel/mcp` 1.0 or higher (Laravel Boost installs it)

## Step 1. Install the package

```bash
composer require darvis/api-x
```

The service provider registers itself. Publish the config only when you want to change more than the `.env` values:

```bash
php artisan vendor:publish --tag=api-x-config
```

The fastest route from here is the setup wizard. It explains every step, writes your answers to `.env`, checks the keys with X, sets the safety limits, adds the MCP server to `.mcp.json` and ends with a dry run:

```bash
php artisan x:install
```

The steps below do the same by hand.

## Step 2. Create the X app

1. Sign in at the [X developer console](https://console.x.com), open **Apps** and open or create your app.
2. Open **Settings** (Authentication settings):
   - **App permissions:** Read and write.
   - **Type of App:** Web App, Automated App or Bot.
   - **Callback URI** and **Website URL** are required, but this package does not use them. Any URL of your own site will do.
   - Save the changes.
3. Open **Keys & Tokens**. In the section **OAuth 1.0 Keys**:
   - **Consumer Key:** click Regenerate if you don't have the secret any more. The console shows the Consumer Key and the Consumer Secret together, only once.
   - **Access Token:** click Generate on the row that says "For @your-account, Read and write". The console shows the Access Token and the Access Token Secret together, only once.
4. Add credit under **Billing, Credits**.

You don't need the Bearer Token or anything under **OAuth 2.0 Keys**.

Generate the access token after step 2. A token created while the app could only read keeps that permission, and every post is then refused with HTTP 403.

## Step 3. Fill in .env

```dotenv
X_CONSUMER_KEY=your-consumer-key
X_CONSUMER_SECRET=your-consumer-secret
X_ACCESS_TOKEN=your-access-token
X_ACCESS_TOKEN_SECRET=your-access-token-secret
```

These OAuth 1.0a keys do not expire. Keep them out of version control like any other secret.

## Step 4. Check that it works

```bash
php artisan x:post "Testing darvis/api-x" --dry-run
```

A dry run checks the text and the image and tells you how many posts are left today, without calling X. Remove `--dry-run` to post for real.

## Setup wizard

`php artisan x:install` asks everything in seven steps: the X app, the four keys, a check with X, the X subscription of the account, the safety settings, the MCP server and a dry run. Every answer is saved to `.env` right away, so you can stop halfway and run it again later to check or change a setting. An empty answer to a key keeps the current value.

Checking the keys calls `GET /2/users/me`, which X bills as one read request. The wizard asks before it does.

In a script, pass options and `--no-interaction`; the command then only changes what the options name:

| Option | What it does |
| --- | --- |
| `--consumer-key=`, `--consumer-secret=`, `--access-token=`, `--access-token-secret=` | Write the key to `.env`. |
| `--subscription=` | Write `X_SUBSCRIPTION`: `none`, `basic`, `premium` or `premium_plus`. |
| `--daily-limit=` | Write `X_DAILY_LIMIT`. |
| `--live` | Write `X_DRY_RUN=false`. |
| `--verify` | Check the keys with X. |
| `--mcp` | Add the server to `.mcp.json`, keeping the other servers. |
| `--config` | Publish `config/api_x.php`. |

## Configuration

| Key | .env | Default | What it does |
| --- | --- | --- | --- |
| `allow_links` | `X_ALLOW_LINKS` | `false` | Allow posts that contain a link. |
| `api_url` | `X_API_URL` | `https://api.x.com` | Base URL of the X API. |
| `cache_store` | `X_CACHE_STORE` | default store | Cache store that counts the posts per day. With the `database` store the cache table must exist. |
| `credentials.*` | `X_CONSUMER_KEY`, `X_CONSUMER_SECRET`, `X_ACCESS_TOKEN`, `X_ACCESS_TOKEN_SECRET` | none | The four OAuth 1.0a keys. |
| `history.enabled` | `X_HISTORY_ENABLED` | `true` | Store every post that went to X in `x_posts`. |
| `daily_limit` | `X_DAILY_LIMIT` | `10` | Most posts per day, counted in the cache. `0` switches the limit off. |
| `dry_run` | `X_DRY_RUN` | `false` | Check every post but never send one. |
| `mcp.enabled` | `X_MCP_ENABLED` | `true` | Register the MCP server when `laravel/mcp` is installed. |
| `mcp.handle` | `X_MCP_HANDLE` | `x` | The handle for `php artisan mcp:start`. |
| `subscription` | `X_SUBSCRIPTION` | `none` | The X subscription of the posting account: `none`, `basic`, `premium` or `premium_plus`. Any paid tier allows posts up to 25,000 characters instead of 280. See [Posting](posting.md#x-subscription). |
| `timeout` | `X_TIMEOUT` | `30` | Timeout in seconds for a call to X. |
| `ui.*` | `X_UI_*` | see [Page](page.md) | The page to post from the browser. |

Read settings in your own code through `Darvis\ApiX\Support\XConfig`, so your code and the package agree on the defaults.
