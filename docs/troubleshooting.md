---
title: "Troubleshooting"
description: "Every message darvis/api-x gives when a post to X is refused, quoted literally, with the cause and the fix, plus the HTTP errors X itself returns most often."
nav_order: 8
---

# Troubleshooting

Every failure is a `Darvis\ApiX\Exceptions\XException`. The MCP tool returns the same message as a tool error, and `x:post` prints it.

## Messages from the package

| Message | Cause | Fix |
| --- | --- | --- |
| `The post text is empty.` | The text is empty after trimming. | Send a text. |
| `The post text counts 312 characters on X; the maximum is 280. With an X subscription, set X_SUBSCRIPTION to allow up to 25,000.` | Too long as X counts it, for an account without an X subscription. | Shorten the text. Emoji and CJK count as 2, a link as 23. With a paid X subscription, set `X_SUBSCRIPTION`. |
| `The post text counts 25312 characters on X; the maximum is 25000.` | Too long even with an X subscription. | Shorten the text. |
| `The post contains a link. X bills posts with a link at a much higher rate; set X_ALLOW_LINKS=true to allow them.` | The text holds a URL, `www.` or a bare domain. | Leave the link out, or allow links. |
| `The daily limit of 10 posts is reached. Raise X_DAILY_LIMIT or try again tomorrow.` | The cap for today is used. | Wait, or raise the limit. |
| `The daily limit needs a working cache, and the cache store failed. With CACHE_STORE=database run php artisan migrate, or set X_CACHE_STORE=file.` | The cache store that counts posts fails, usually a missing `cache` table. | Run `php artisan migrate`, or count in another store with `X_CACHE_STORE=file`. |
| `X credentials are missing. Set X_CONSUMER_KEY, X_CONSUMER_SECRET, X_ACCESS_TOKEN and X_ACCESS_TOKEN_SECRET.` | One of the four keys is empty. | Fill in all four and clear a cached config with `php artisan config:clear`. |
| `The image could not be read: <path or URL> (HTTP 429)` | The file does not exist, or the URL did not answer with an image; the reason in brackets says why. GitHub's release cards (opengraph.githubassets.com) answer 429 Too Many Requests now and then. | Use an absolute path, or wait and try the URL again. |
| `The image type text/plain is not supported; use JPEG, PNG, GIF or WebP.` | The file is not an image X accepts. | Convert it. |
| `The image is 7.2 MB; X accepts at most 5 MB.` | The image is too large. | Resize or compress it. |

## The page answers 403

Only visitors the `postToX` gate allows get in; without a gate of your own that is the local environment only. Define the gate, see [Page](page.md).

## Messages from X

`The request to X failed: <reason>` means X could not be reached, for example a timeout or no network.

The others start with `X refused to create the post`, `X refused to upload the image` or, from the setup wizard, `X refused to check the keys`, followed by the HTTP status and the reason X gave.

| Status | Usual cause |
| --- | --- |
| 401 | A key is wrong: often the Consumer Key and Secret were pasted into the Access Token fields too, or the Consumer Key was regenerated after the Access Token (that makes the token invalid; generate it again). Less often the system clock is far off, because OAuth 1.0a signs a timestamp. |
| 402 | The account has no API credit left. |
| 403 | The app has no Write permission, or the access token was generated before it had. Generate a new access token. A duplicate post is refused with 403 as well. |
| 429 | Too many requests. Wait and try again. |

## The MCP server does not show up

- Check that `laravel/mcp` is installed: `composer show laravel/mcp`.
- Check that `X_MCP_ENABLED` is not `false`.
- Run `php artisan mcp:start x` yourself; it should wait for input without an error.
- A desktop client needs the absolute path to `artisan`.
