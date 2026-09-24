---
title: "Home"
nav_order: 1
description: "darvis/api-x for Laravel posts text and images to X through the X API v2, from PHP, an artisan command or a local MCP server for AI agents."
permalink: /
---

# darvis/api-x

`darvis/api-x` is a Laravel package that posts short updates, with one optional image, to **X** (formerly Twitter). You call it from PHP, from the command line with `php artisan x:post`, or let an AI agent such as Claude do it through a local MCP server.

This is an unofficial, independent open-source package: it is not an official X product and is not affiliated with X Corp.

## Who it is for

A developer who wants to share what they are building without leaving the editor: release notes of a package, a screenshot of a new screen, a short "working on" update written by the agent you already work with.

## What it does

- Uploads one JPEG, PNG, GIF or WebP (local path or URL, at most 5 MB) and attaches it to the post
- Checks the text the way X counts it, before anything is sent or billed
- Refuses links unless you allow them, because X bills posts with a link at a much higher rate
- Caps the number of posts per day and offers a dry run
- Registers a local MCP server with one tool, when `laravel/mcp` is installed

## What it does not do

- It posts as **one account**: the one the keys belong to. There is no connect flow for other users.
- No threads, polls, video or scheduling. Schedule with Laravel's scheduler if you need it.

## Pages

- [Installation & configuration](installation.md): the X app, the keys and every config value
- [Quick start](quickstart.md): your first post in five minutes
- [Posting](posting.md): text, images, links, the daily limit and dry runs
- [MCP server](mcp-server.md): let Claude or another agent post for you
- [Testing](testing.md): test code that posts, without calling X
- [Troubleshooting](troubleshooting.md): every message the package gives, with the fix
- [FAQ](faq.md)
