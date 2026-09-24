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
2. The text must fit in 280 characters as X counts them.
3. Without `allow_links`, the text must not contain a link.
4. The daily limit must not be reached.
5. The image, when given, is read and checked.
6. In a dry run the method stops here and returns a result without an id.
7. The credentials must be complete.
8. The image is uploaded, then the post is created with its media id.

Every check runs before X is called, also in a dry run, so a dry run fails exactly where a real post would.

## Text length

X counts most Latin, Greek and Cyrillic characters as 1, other characters such as CJK and emoji as 2, and every link as 23, whatever its length. The package counts an emoji made of several code points per code point, so it may refuse a text with such an emoji slightly before X would.

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
