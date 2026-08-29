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

    /**
     * Whether an *already authenticated* request may proceed. Deliberately laxer than
     * `canAuthenticate()`: this runs on every request, and an invited user finishing
     * setup must not be locked out of the flow that activates them. Only suspension
     * cuts a live session — see {@see \App\Http\Middleware\EnsureActiveUser}.
     */
    public function hasApiAccess(): bool
    {
        return $this !== self::Suspended;
    }
}
