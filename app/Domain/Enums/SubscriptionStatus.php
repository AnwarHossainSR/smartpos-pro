<?php

namespace App\Domain\Enums;

use App\Domain\Enums\Concerns\HasValues;

enum SubscriptionStatus: string
{
    use HasValues;

    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Cancelled = 'cancelled';

    public function isLive(): bool
    {
        return in_array($this, [self::Trialing, self::Active], true);
    }
}
