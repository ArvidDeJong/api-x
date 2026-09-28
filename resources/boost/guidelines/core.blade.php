## darvis/api-x

This package posts a text with one optional image to X through the X API v2, reads the posts that mention the account and replies to them. It talks to X through `Illuminate\Support\Facades\Http` only; don't add an X SDK or an OAuth library next to it.

- Config lives under the key `api_x` (file `config/api_x.php`). Read settings through the static accessors on `Darvis\ApiX\Support\XConfig` (`allowLinks()`, `dailyLimit()`, `dryRun()`, `mcpHandle()`, ...) instead of the `config()` helper, so app code and package agree on the defaults.
- Set the package up with `php artisan x:install` (interactive wizard; with `--no-interaction` it only applies the options it gets). Don't write the `X_*` keys to `.env` by hand when the wizard can.
- There is one entry point, `Darvis\ApiX\XClient::post($text, $image = null, $dryRun = false, $replyTo = null)`. It returns a `Darvis\ApiX\PostResult` (`id`, `url()`, `mediaId`, `dryRun`, `remainingToday`, `replyTo`) and throws `Darvis\ApiX\Exceptions\XException` for every failure. The command `x:post` and the MCP tool `post-update` call the same method.
- `XClient::mentions($sinceId = null, $limit = 10)` returns the newest posts that mention the account as `Darvis\ApiX\Mention` objects (`id`, `text`, `authorUsername`, `inReplyToId`, `inReplyToText`, `url()`). X bills every post returned as a read: always pass the newest id you already have as `$sinceId`, and never read mentions in a loop or on every request.
- `$replyTo` is a post id or a link to it. X only allows a reply to a post that mentions or quotes the account, such as a mention; a reply elsewhere is refused with 403. A reply is public under the owner's name: show the text to the owner before sending it.
- The image is a local path or an http(s) URL of a JPEG, PNG, GIF or WebP up to 5 MB.
- A text with a link is refused unless `X_ALLOW_LINKS=true`, because X bills posts with a link at a much higher rate. Don't switch that on to make a post pass; leave the link out.
- The text limit is 280 as X counts it, or 25,000 when `X_SUBSCRIPTION` is `basic`, `premium` or `premium_plus`. Use `XConfig::subscription()->maxLength()` instead of a hard coded 280.
- `X_DAILY_LIMIT` (default 10, 0 is off) caps real posts per day, and `X_DRY_RUN=true` or `dryRun: true` checks a post without sending it.

@verbatim
<code-snippet name="Post with an image and handle a refusal" lang="php">
use Darvis\ApiX\Exceptions\XException;
use Darvis\ApiX\XClient;

try {
    $result = app(XClient::class)->post('Released 1.2.0 of my package', storage_path('app/card.png'));
    $result->url();
} catch (XException $exception) {
    report($exception);
}
</code-snippet>
@endverbatim

- With `laravel/mcp` installed, `php artisan mcp:start x` starts a local MCP server with the `post-update` tool (`text`, `image`, `dry_run`, `reply_to`) and the `list-mentions` tool (`since_id`, `limit`). A post from the tool goes out immediately and publicly; do a dry run first when unsure.
- With Livewire and Flux installed there is a page at `/x` (route `api-x.page`) to post and to see the history in the `x_posts` table (`Darvis\ApiX\Models\XPost`). Access goes through the `postToX` gate, local only by default; define that gate for production instead of loosening the middleware.
- In tests, fake `api.x.com/2/media/upload` and `api.x.com/2/tweets` with `Http::fake()`, and for mentions `api.x.com/2/users/me` and `api.x.com/2/users/<id>/mentions*`. Use the `array` cache store so the daily limit starts at zero.
