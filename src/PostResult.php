<?php

declare(strict_types=1);

namespace Darvis\ApiX;

/**
 * What happened to a post: sent (with an id) or only checked in a dry run.
 */
final class PostResult
{
    public function __construct(
        public readonly string $text,
        public readonly bool $dryRun,
        public readonly ?string $id = null,
        public readonly ?string $mediaId = null,
        public readonly ?int $remainingToday = null,
    ) {}

    /**
     * Link to the post on X, or null for a dry run.
     */
    public function url(): ?string
    {
        return $this->id === null ? null : 'https://x.com/i/web/status/'.$this->id;
    }
}
