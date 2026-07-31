<?php

namespace App\Domain\Enums;

use App\Domain\Enums\Concerns\HasValues;

enum TenantStatus: string
{
    use HasValues;

    case Active = 'active';
    case Trialing = 'trialing';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';

    /** Suspended and cancelled tenants are refused at the tenant middleware. */
    public function hasApiAccess(): bool
    {
        return in_array($this, [self::Active, self::Trialing], true);
    }
}
