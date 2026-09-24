<?php

declare(strict_types=1);

namespace Darvis\ApiX\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * Every failure of this package, with a message that says what to change.
 */
class XException extends RuntimeException
{
    public static function emptyText(): self
    {
        return new self('The post text is empty.');
    }

    public static function textTooLong(int $length, int $max): self
    {
        return new self("The post text counts {$length} characters on X; the maximum is {$max}.");
    }

    public static function linkNotAllowed(): self
    {
        return new self('The post contains a link. X bills posts with a link at a much higher rate; set X_ALLOW_LINKS=true to allow them.');
    }

    public static function dailyLimitReached(int $limit): self
    {
        return new self("The daily limit of {$limit} posts is reached. Raise X_DAILY_LIMIT or try again tomorrow.");
    }

    public static function cacheUnavailable(\Throwable $previous): self
    {
        return new self('The daily limit needs a working cache, and the cache store failed. With CACHE_STORE=database run php artisan migrate, or set X_CACHE_STORE=file.', previous: $previous);
    }

    public static function missingCredentials(): self
    {
        return new self('X credentials are missing. Set X_CONSUMER_KEY, X_CONSUMER_SECRET, X_ACCESS_TOKEN and X_ACCESS_TOKEN_SECRET.');
    }

    public static function imageNotFound(string $image, ?string $reason = null): self
    {
        return new self("The image could not be read: {$image}".($reason === null ? '' : " ({$reason})"));
    }

    public static function unsupportedImage(string $mime): self
    {
        return new self("The image type {$mime} is not supported; use JPEG, PNG, GIF or WebP.");
    }

    public static function imageTooLarge(int $bytes, int $max): self
    {
        return new self('The image is '.round($bytes / 1048576, 1).' MB; X accepts at most '.round($max / 1048576).' MB.');
    }

    public static function requestError(\Throwable $previous): self
    {
        return new self('The request to X failed: '.$previous->getMessage(), previous: $previous);
    }

    public static function requestFailed(string $action, Response $response): self
    {
        $body = $response->json();
        $detail = is_array($body)
            ? ($body['detail'] ?? $body['title'] ?? $body['errors'][0]['message'] ?? null)
            : null;

        return new self(sprintf(
            'X refused to %s (HTTP %d)%s',
            $action,
            $response->status(),
            is_string($detail) && $detail !== '' ? ': '.$detail : '.',
        ));
    }
}
