<?php

namespace App\Support\Query;

use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * The inclusive `from`/`to` day filter, shared by the query pipeline and the
 * report endpoints that build their queries by hand.
 */
final class DateRange
{
    /** @param  EloquentBuilder<*>|QueryBuilder  $query */
    public static function apply(
        EloquentBuilder|QueryBuilder $query,
        Request $request,
        string $column,
        string $fromKey = 'from',
        string $toKey = 'to',
    ): void {
        if ($from = self::parse($request->input($fromKey), $fromKey)) {
            $query->where($column, '>=', $from->startOfDay());
        }

        if ($to = self::parse($request->input($toKey), $toKey)) {
            $query->where($column, '<', $to->startOfDay()->addDay());
        }
    }

    /**
     * A malformed date is rejected rather than dropped: silently returning the
     * unfiltered set would show a user every period when they asked for one.
     */
    public static function parse(mixed $value, string $field): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (InvalidFormatException) {
            throw ValidationException::withMessages([
                $field => ["The {$field} field must be a valid date."],
            ]);
        }
    }
}
