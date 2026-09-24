---
title: "Page"
description: "The Livewire page of darvis/api-x: post to X from the browser with a live counter and an image, and see every post with its status in the history."
nav_order: 5
---

# Page

With Livewire and Flux installed, the package registers a page at `/x`. It has a form to post, with a counter that counts the way X does, an image upload or URL and a dry run switch. Below the form is the history: every post that went to X, also the ones from `x:post` and the MCP tool, with its status, the reason when X refused it and a link to the post.

## Requirements

- `livewire/livewire` 3.7.4 or 4 and `livewire/flux` 2.11 or higher. The free edition of Flux is enough.
- The `x_posts` table for the history: run `php artisan migrate`. Without the table the page still posts and asks for the migration.

- Tailwind has to scan the view of the package. With Tailwind 4 add this line to `resources/css/app.css` and build the assets again:

  ```css
  @source '../../vendor/darvis/api-x/resources/views';
  ```

Without Livewire the page is simply not registered; everything else keeps working.

## Who may open it

The page posts publicly as the account the keys belong to, so a login is not enough. Only visitors the `postToX` gate allows get in, and everyone else gets a 403. Without a gate of your own that is the local environment only, the way Horizon and Telescope work.

To open it in production, define the gate in your `AppServiceProvider`:

```php
use App\Models\User;
use Illuminate\Support\Facades\Gate;

Gate::define('postToX', fn (?User $user) => $user?->is_admin === true);
```

The gate is checked on the page load and on every Livewire request, so taking a role away works at once.

## Configuration

| Key | .env | Default | What it does |
| --- | --- | --- | --- |
| `ui.enabled` | `X_UI_ENABLED` | `true` | Register the page. |
| `ui.layout` | `X_UI_LAYOUT` | `layouts::app` | Blade layout the page is rendered in; the default is the one of the Laravel Livewire starter kit. |
| `ui.middleware` | `X_UI_MIDDLEWARE` | `web` | Comma separated middleware before the gate, for example `web,auth`. |
| `ui.per_page` | `X_UI_PER_PAGE` | `25` | Rows per page in the history. |
| `ui.route` | `X_UI_ROUTE` | `x` | Path of the page. |
| `history.enabled` | `X_HISTORY_ENABLED` | `true` | Store posts in `x_posts`. |

The route is named `api-x.page`, so a menu item is `route('api-x.page')`.

## The history

A row is stored for every post that went to X: status `sent` with the id of the post, or `failed` with the reason X gave. Dry runs and posts the package refused before calling X, such as a text that is too long, are not stored. The model is `Darvis\ApiX\Models\XPost`.

Writing the history never stops a post: when the table is missing or the database fails, the error is reported and the post goes out anyway.

## Changing the page

Publish the view with `php artisan vendor:publish --tag=api-x-views`; it lands in `resources/views/vendor/api-x`.
