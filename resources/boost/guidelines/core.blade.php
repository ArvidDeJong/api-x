## darvis/api-x

This package posts a text with one optional image to X through the X API v2. It talks to X through `Illuminate\Support\Facades\Http` only; don't add an X SDK or an OAuth library next to it.

- Config lives under the key `api_x` (file `config/api_x.php`). Read settings through the static accessors on `Darvis\ApiX\Support\XConfig` (`allowLinks()`, `dailyLimit()`, `dryRun()`, `mcpHandle()`, ...) instead of the `config()` helper, so app code and package agree on the defaults.
- Set the package up with `php artisan x:install` (interactive wizard; with `--no-interaction` it only applies the options it gets). Don't write the `X_*` keys to `.env` by hand when the wizard can.
- There is one entry point, `Darvis\ApiX\XClient::post($text, $image = null, $dryRun = false)`. It returns a `Darvis\ApiX\PostResult` (`id`, `url()`, `mediaId`, `dryRun`, `remainingToday`) and throws `Darvis\ApiX\Exceptions\XException` for every failure. The command `x:post` and the MCP tool `post-update` call the same method.
- The image is a local path or an http(s) URL of a JPEG, PNG, GIF or WebP up to 5 MB.
- A text with a link is refused unless `X_ALLOW_LINKS=true`, because X bills posts with a link at a much higher rate. Don't switch that on to make a post pass; leave the link out.
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

- With `laravel/mcp` installed, `php artisan mcp:start x` starts a local MCP server with the `post-update` tool (`text`, `image`, `dry_run`). A post from the tool goes out immediately and publicly; do a dry run first when unsure.
- In tests, fake `api.x.com/2/media/upload` and `api.x.com/2/tweets` with `Http::fake()`, and use the `array` cache store so the daily limit starts at zero.
