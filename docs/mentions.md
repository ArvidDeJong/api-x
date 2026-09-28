---
title: "Mentions and replies"
description: "Read the posts that mention your X account and reply to them with darvis/api-x: from PHP, x:mentions, x:post --reply-to or the MCP tools, and what X bills."
nav_order: 5
---

# Mentions and replies

The package reads the posts that mention the account the keys belong to, and posts a reply to one of them. Together that is enough to answer the replies under your own posts, or to let an agent draft the answers for you.

## Read the mentions

```php
use Darvis\ApiX\XClient;

$mentions = app(XClient::class)->mentions(sinceId: '1840000000000000000', limit: 10);

foreach ($mentions as $mention) {
    $mention->id;              // '1840000000000000301'
    $mention->text;            // '@you Does it work with Laravel 13?'
    $mention->authorUsername;  // 'jane'
    $mention->authorName;      // 'Jane Doe'
    $mention->createdAt;       // '2026-09-28T09:12:00.000Z'
    $mention->inReplyToId;     // the post it answers, when it is a reply
    $mention->inReplyToText;   // your own post it answers, from the history
    $mention->url();           // 'https://x.com/jane/status/1840000000000000301'
}
```

- A mention is a reply to one of your posts or a post that names your account. The newest comes first. Your own posts are left out.
- `sinceId` returns only posts newer than that id. Keep the id of the newest mention and pass it next time, so you only read what is new.
- `limit` is 5 to 100; X accepts nothing outside that range, so the package keeps it inside. The default is 10.
- `inReplyToText` comes from the [history](page.md) of posts the package sent, not from X, so it costs nothing extra. It is null for a post that is not in the history.

From the command line:

```bash
php artisan x:mentions --since=1840000000000000000 --limit=20
```

## Reply

Pass the post to answer as `replyTo`: its id, or a link to it.

```php
app(XClient::class)->post('Yes, Laravel 11 to 13.', replyTo: 'https://x.com/jane/status/1840000000000000301');
```

```bash
php artisan x:post "Yes, Laravel 11 to 13." --reply-to=1840000000000000301
```

A reply is a post like any other: the same length limit, the same link rule, it counts towards `X_DAILY_LIMIT` and a dry run checks it without sending. The `PostResult` has the id you replied to in `replyTo`.

X only lets an app on a self-serve plan reply to a post whose author mentioned your account or quoted one of your posts. The posts in the mentions timeline qualify. A reply to any other post is refused with HTTP 403.

## Through the MCP server

The [MCP server](mcp-server.md) has a `list-mentions` tool (`since_id`, `limit`) and the `reply_to` argument on `post-update`. A workflow that works well:

1. The agent calls `list-mentions` with the newest id of last time.
2. It drafts an answer to each mention that needs one, in your voice, and shows you the drafts.
3. You approve or change them, and the agent sends each one with `post-update` and `reply_to`.

A reply goes out publicly under your name. The server tells the agent to show you a reply before sending it, unless you told it otherwise.

## What X bills

- Reading: X bills every post the mentions timeline returns. It lists this endpoint under owned reads, its cheapest kind of read, and does not bill the same post twice on one UTC day. Pass `since_id` to keep it small.
- The first read looks up the id of your account with `GET /2/users/me`, one billed read. The package keeps that id in the cache per access token, so it happens once.
- Replying: a reply costs the same as a post, and a reply with a link costs much more, like any post.

Check the current prices in the X developer console; they change.
