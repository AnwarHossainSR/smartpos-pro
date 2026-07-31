<?php

namespace App\Http\Controllers;

use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

abstract class Controller
{
    /**
     * Hard ceiling on any client-supplied page size or row limit.
     *
     * `per_page` / `limit` come straight off the query string, so leaving them
     * uncapped lets any authenticated user pull an entire table into memory with
     * `?per_page=1000000` — a one-request denial of service against a shared,
     * multi-tenant API. High enough that no legitimate UI ever hits it.
     */
    protected const MAX_PAGE_SIZE = 200;

    /** Page size for a paginated list endpoint, clamped to {@see self::MAX_PAGE_SIZE}. */
    protected function perPage(Request $request, int $default = 25): int
    {
        return $this->clampSize($request->integer('per_page'), $default);
    }

    /** Row cap for a non-paginated "top N" endpoint, clamped to {@see self::MAX_PAGE_SIZE}. */
    protected function limit(Request $request, int $default): int
    {
        return $this->clampSize($request->integer('limit'), $default);
    }

    /** A missing, zero or negative value falls back to the endpoint's own default. */
    private function clampSize(int $requested, int $default): int
    {
        return $requested < 1 ? $default : min($requested, self::MAX_PAGE_SIZE);
    }

    /**
     * Apply the conventional inclusive `from`/`to` day filter to a date column.
     *
     * Uses half-open bounds (`>= from 00:00`, `< to+1day 00:00`) instead of
     * `whereDate()`. Wrapping the column in SQL's `date()` makes the predicate
     * non-sargable, so the index on that column goes unused and every filtered
     * list and report degrades to a sequential scan as the table grows.
     *
     * Designed for `->tap()` so it drops into an existing query chain.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<*>|\Illuminate\Database\Query\Builder  $query
     */
    protected function filterByDateRange(EloquentBuilder|QueryBuilder $query, Request $request, string $column): void
    {
        if ($from = $this->parseFilterDate($request->input('from'), 'from')) {
            $query->where($column, '>=', $from->startOfDay());
        }

        if ($to = $this->parseFilterDate($request->input('to'), 'to')) {
            $query->where($column, '<', $to->startOfDay()->addDay());
        }
    }

    /**
     * A malformed date is rejected rather than dropped: silently returning the
     * unfiltered set would show a user every period when they asked for one.
     */
    private function parseFilterDate(mixed $value, string $field): ?Carbon
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
