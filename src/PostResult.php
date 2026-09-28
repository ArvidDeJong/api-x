<?php

declare(strict_types=1);

namespace Darvis\ApiX;

/**
 * What happened to a post: sent (with an id) or only checked in a dry run.
 */
final class PostResult
{
    /**
     * @param  string|null  $replyTo  The id of the post this one replies to.
     */
    public function __construct(
        public readonly string $text,
        public readonly bool $dryRun,
        public readonly ?string $id = null,
        public readonly ?string $mediaId = null,
        public readonly ?int $remainingToday = null,
        public readonly ?string $replyTo = null,
    ) {}

    /**
     * Link to the post on X, or null for a dry run.
     */
    public function url(): ?string
    {
        return $this->id === null ? null : 'https://x.com/i/web/status/'.$this->id;
    }
}
