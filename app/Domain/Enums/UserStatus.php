<?php

namespace App\Domain\Enums;

use App\Domain\Enums\Concerns\HasValues;

enum UserStatus: string
{
    use HasValues;

    case Active = 'active';
    case Invited = 'invited';
    case Suspended = 'suspended';

    /** An invited user has no password yet; a suspended one is refused at login. */
    public function canAuthenticate(): bool
    {
        return $this === self::Active;
    }
}
