<?php

namespace App\Domain\Enums\Concerns;

use Illuminate\Validation\Rules\Enum as EnumRule;

/**
 * Shared helpers for the backed string enums that replace this codebase's status,
 * method and type literals. Gives every enum the two things a Laravel app always
 * needs from one: the raw values (for `in:` rules, migrations and tests) and a
 * ready-made validation rule.
 */
trait HasValues
{
    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Validation rule accepting any case of this enum. */
    public static function rule(): EnumRule
    {
        return new EnumRule(self::class);
    }

    /**
     * Resolve a value that may already be an enum, its wire string, or absent.
     *
     * Service payloads arrive from both HTTP (strings) and internal callers
     * (enums); this keeps the three-way check out of every call site.
     */
    public static function coerce(mixed $value, ?self $default = null): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        return $value === null || $value === '' ? $default : self::from($value);
    }

    /** Human label for UI/report output — "mobile_banking" becomes "Mobile banking". */
    public function label(): string
    {
        return ucfirst(str_replace('_', ' ', $this->value));
    }
}
