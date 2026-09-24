---
title: "MCP server"
description: "Let Claude or another AI agent post to X through the local MCP server of darvis/api-x: the handle, the post-update tool and how to add it to your agent."
nav_order: 5
---

# MCP server

When `laravel/mcp` is installed, the package registers a local (stdio) MCP server. Laravel Boost already requires `laravel/mcp`, so an app with Boost has it. Without `laravel/mcp` the server is simply not registered; the class and the command keep working.

## Start it

```bash
php artisan mcp:start x
```

The handle `x` comes from `X_MCP_HANDLE`. Set `X_MCP_ENABLED=false` to leave the server out.

## Add it to your agent

`php artisan x:install` adds the server to `.mcp.json` for you and prints the command for every other project. By hand:

For Claude Code, add it to `.mcp.json` in the root of the Laravel app:

```json
{
    "mcpServers": {
        "x": {
            "command": "php",
            "args": ["artisan", "mcp:start", "x"]
        }
    }
}
```

For the Claude desktop app and other clients, use the same command with the absolute path to `artisan`, for example `/Users/you/Sites/my-app/artisan`.

## The tool

The server has one tool, `post-update`:

| Argument | Type | Required | What it does |
| --- | --- | --- | --- |
| `text` | string | yes | The post text, at most 280 characters as X counts them. |
| `image` | string | no | An absolute local path or an http(s) URL of a JPEG, PNG, GIF or WebP up to 5 MB. |
| `dry_run` | boolean | no | Check the post without sending it. |

On success the tool answers `Posted: <link>` and the number of posts left today. A refused post comes back as a tool error with the same message the exception carries, so the agent can fix the text and try again.

## Keep the agent in check

The post goes out immediately and publicly. Use `X_DAILY_LIMIT` to cap how often an agent can post, and `X_DRY_RUN=true` while you try things out. Links stay refused unless you allow them.

## Posting after a release

A workflow that fits the tool well: after tagging a release, let the agent write a post from the new section of `CHANGELOG.md` and attach the release card GitHub makes, `https://opengraph.githubassets.com/1/<owner>/<repo>/releases/tag/<tag>`.
