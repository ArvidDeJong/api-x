<?php

declare(strict_types=1);

namespace Darvis\ApiX\Support;

/**
 * The X subscription of the account that posts, set with X_SUBSCRIPTION. It decides what the
 * API accepts from that account; today that is the post length. Every paid tier (Basic,
 * Premium and Premium+) may post up to 25,000 characters, without one X stops at 280.
 */
enum Subscription: string
{
    case None = 'none';
    case Basic = 'basic';
    case Premium = 'premium';
    case PremiumPlus = 'premium_plus';

    /**
     * The longest post X accepts from this account, counted the way X counts.
     */
    public function maxLength(): int
    {
        return $this === self::None ? PostText::MAX_LENGTH : PostText::LONG_MAX_LENGTH;
    }

    /**
     * The name X uses for the tier.
     */
    public function label(): string
    {
        return match ($this) {
            self::None => 'No subscription',
            self::Basic => 'Basic',
            self::Premium => 'Premium',
            self::PremiumPlus => 'Premium+',
        };
    }
}
