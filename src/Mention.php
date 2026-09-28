<?php

declare(strict_types=1);

namespace Darvis\ApiX;

/**
 * A post that mentions the account: a reply to one of its posts, or a post that names it.
 */
final class Mention
{
    public function __construct(
        public readonly string $id,
        public readonly string $text,
        public readonly string $authorId,
        public readonly ?string $authorUsername = null,
        public readonly ?string $authorName = null,
        public readonly ?string $createdAt = null,
        public readonly ?string $conversationId = null,
        public readonly ?string $inReplyToId = null,
        public readonly ?string $inReplyToText = null,
    ) {}

    /**
     * Link to the post on X.
     */
    public function url(): string
    {
        return $this->authorUsername === null
            ? 'https://x.com/i/web/status/'.$this->id
            : 'https://x.com/'.$this->authorUsername.'/status/'.$this->id;
    }
}
