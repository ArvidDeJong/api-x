---
title: "Posting"
description: "How darvis/api-x posts to X: the text length as X counts it, images, why links are refused by default, the daily limit, dry runs and the PostResult."
nav_order: 4
---

# Posting

Everything goes through one method:

```php
use Darvis\ApiX\XClient;

$result = app(XClient::class)->post(string $text, ?string $image = null, bool $dryRun = false);
```

The command `x:post` and the MCP tool call the same method, so the rules below apply to all three.

## The order of checks

1. The text is trimmed and must not be empty.
2. The text must fit in 280 characters as X counts them, or 25,000 when the account has an X subscription (see below).
3. Without `allow_links`, the text must not contain a link.
4. The daily limit must not be reached.
5. The image, when given, is read and checked.
6. In a dry run the method stops here and returns a result without an id.
7. The credentials must be complete.
8. The image is uploaded, then the post is created with its media id.

Every check runs before X is called, also in a dry run, so a dry run fails exactly where a real post would.

## Text length

X counts most Latin, Greek and Cyrillic characters as 1, other characters such as CJK and emoji as 2, and every link as 23, whatever its length. The package counts an emoji made of several code points per code point, so it may refuse a text with such an emoji slightly before X would.

## X subscription

An account with a paid X subscription (Basic, Premium or Premium+) may post up to 25,000 characters through the API; without one X stops at 280. X accepts nothing else extra through this package: every tier gets the same posting features, the differences are in verification, ads and monetisation. Tell the package which subscription the posting account has:

```dotenv
X_SUBSCRIPTION=premium
```

The values are `none` (default), `basic`, `premium` and `premium_plus`. An unknown value counts as `none`. The limit follows everywhere: `XClient::post()`, `x:post`, the MCP tool description and the counter on the page. Read it in your own code with `XConfig::subscription()->maxLength()`.

Set the subscription the account really has. With a paid tier configured on an account without one, the package lets a long post through and X refuses it.

### What each tier offers

From the X help centre, [About X Premium](https://help.x.com/en/using-x/x-premium). Only the post length matters to this package.

| | None | Basic | Premium | Premium+ |
| --- | --- | --- | --- | --- |
| Post length | 280 | 25,000 | 25,000 | 25,000 |
| Long posts through the API | no | yes | yes | yes |
| Edit a post | no | within 1 hour | within 1 hour | within 1 hour |
| Video length | about 2 min 20 s | about 3 hours, 8 GB, 1080p | about 3 hours, 8 GB, 1080p | about 3 hours, 8 GB, 1080p |
| Text formatting, bookmark folders, app icon | no | yes | yes | yes |
| Reply prioritisation | no | small | larger | largest |
| Blue checkmark and ID verification | no | no | yes | yes |
| Ads | normal | normal | about 50% fewer | none, some sponsored content |
| Creator Subscriptions, Original Content Rewards, Media Studio | no | no | yes | yes |
| SuperGrok, Grok Bot, Radar Search, Articles | no | no | no | yes |

The package posts text and one image, so video and editing do not apply. Articles cannot be posted through the API. Prices differ per country and change often; check them on X.

## Images

One image per post: JPEG, PNG, GIF or WebP, at most 5 MB. Pass a local path or an http(s) URL. The type is read from the file contents, not from the name.

## Links

X bills a post with a link at a much higher rate than a plain post. The package therefore refuses a text with a link unless `X_ALLOW_LINKS=true`. It treats as a link what X links as well: a URL with `http://` or `https://`, anything starting with `www.` and a bare domain on a common top level domain such as `arvid.nl` or `github.com/ArvidDeJong`. Package names like `darvis/api-x` and file names like `CHANGELOG.md` are not links.

## Daily limit

`X_DAILY_LIMIT` (default 10) caps the posts per day. The count lives in your default cache store under `api_x:posts:<date>` and only real posts count, dry runs do not. Set it to `0` to switch the limit off. `app(XClient::class)->remainingToday()` returns what is left, or `null` without a limit.

## Dry runs

Pass `dryRun: true`, use `--dry-run` on the command, `dry_run` on the MCP tool, or set `X_DRY_RUN=true` to make every post a dry run.

## The result

`post()` returns a `Darvis\ApiX\PostResult`:

| Property or method | Value |
| --- | --- |
| `text` | The text as it was sent, trimmed. |
| `dryRun` | `true` when nothing was sent. |
| `id` | The id of the post, `null` in a dry run. |
| `mediaId` | The id of the uploaded image, or `null`. |
| `remainingToday` | Posts left today after this one, `null` without a limit. |
| `url()` | `https://x.com/i/web/status/<id>`, or `null` in a dry run. |

## Failures

Every failure is a `Darvis\ApiX\Exceptions\XException` with a message that says what to change. When X refuses a request, the message holds the HTTP status and the reason X gave. See [Troubleshooting](troubleshooting.md).
