<?php

namespace App\Domain\Enums;

use App\Domain\Enums\Concerns\HasValues;

/**
 * How a fractional minor unit is resolved. Jurisdictions differ: most retail VAT
 * regimes mandate half-up, while several accounting standards (and every system
 * that must not drift over millions of lines) use half-even / banker's rounding.
 */
enum RoundingMode: string
{
    use HasValues;

    case HalfUp = 'half_up';
    case HalfDown = 'half_down';
    case HalfEven = 'half_even';
    case Up = 'up';
    case Down = 'down';

    /** Resolve a fractional minor-unit amount to a whole one. */
    public function round(float $value): int
    {
        return match ($this) {
            self::HalfUp => (int) round($value, 0, PHP_ROUND_HALF_UP),
            self::HalfDown => (int) round($value, 0, PHP_ROUND_HALF_DOWN),
            self::HalfEven => (int) round($value, 0, PHP_ROUND_HALF_EVEN),
            self::Up => $value < 0 ? (int) floor($value) : (int) ceil($value),
            self::Down => $value < 0 ? (int) ceil($value) : (int) floor($value),
        };
    }
}
