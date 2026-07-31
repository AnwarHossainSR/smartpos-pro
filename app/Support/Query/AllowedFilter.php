<?php

namespace App\Support\Query;

use BackedEnum;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

/**
 * One declared, named filter on a list endpoint.
 *
 * A filter owns the query keys it consumes (`names()`) and how it narrows the
 * query (`apply()`). Declaring them means {@see QueryPipeline} can reject a
 * query string key nothing handles, instead of silently returning the whole
 * unfiltered table — which is how a typo'd `?catgory_id=3` used to look
 * identical to "this category has every product in it".
 */
final class AllowedFilter
{
    /**
     * @param  array<int, string>  $names  query-string keys this filter consumes
     * @param  Closure(Builder<*>, Request): void  $apply
     */
    private function __construct(
        public readonly array $names,
        private readonly Closure $apply,
    ) {}

    /** `?status=completed` → `where('status', 'completed')`. */
    public static function exact(string $name, ?string $column = null): self
    {
        $column ??= $name;

        return new self([$name], function (Builder $query, Request $request) use ($name, $column): void {
            if ($request->filled($name)) {
                $query->where($column, $request->input($name));
            }
        });
    }

    /** `?name=jo` → `where('name', 'like', '%jo%')`. */
    public static function partial(string $name, ?string $column = null): self
    {
        $column ??= $name;

        return new self([$name], function (Builder $query, Request $request) use ($name, $column): void {
            if ($request->filled($name)) {
                $query->where($column, 'like', '%'.$request->string($name)->value().'%');
            }
        });
    }

    /** `?is_active=false` → `where('is_active', false)`. Absent means "don't filter". */
    public static function boolean(string $name, ?string $column = null): self
    {
        $column ??= $name;

        return new self([$name], function (Builder $query, Request $request) use ($name, $column): void {
            if ($request->filled($name)) {
                $query->where($column, $request->boolean($name));
            }
        });
    }

    /** `?status=draft,sent` → `whereIn('status', ['draft', 'sent'])`. */
    public static function in(string $name, ?string $column = null): self
    {
        $column ??= $name;

        return new self([$name], function (Builder $query, Request $request) use ($name, $column): void {
            if (! $request->filled($name)) {
                return;
            }

            $values = array_filter(array_map('trim', explode(',', (string) $request->input($name))), 'strlen');

            if ($values !== []) {
                $query->whereIn($column, $values);
            }
        });
    }

    /**
     * Like {@see exact}, but validates the value against a backed enum first so an
     * unknown status is a 422 rather than an empty result set the caller has to
     * guess the cause of.
     *
     * @param  class-string<BackedEnum>  $enum
     */
    public static function enum(string $name, string $enum, ?string $column = null): self
    {
        $column ??= $name;

        return new self([$name], function (Builder $query, Request $request) use ($name, $column, $enum): void {
            if (! $request->filled($name)) {
                return;
            }

            $request->validate([$name => [new Enum($enum)]]);

            $query->where($column, $request->input($name));
        });
    }

    /**
     * A switch rather than a value: `?low_stock=1` runs the callback, anything
     * falsy leaves the query alone.
     *
     * @param  Closure(Builder<*>): void  $apply
     */
    public static function flag(string $name, Closure $apply): self
    {
        return new self([$name], function (Builder $query, Request $request) use ($name, $apply): void {
            if ($request->boolean($name)) {
                $apply($query);
            }
        });
    }

    /**
     * Anything the shorthands don't cover. The callback receives the raw value.
     *
     * @param  Closure(Builder<*>, mixed): void  $apply
     */
    public static function callback(string $name, Closure $apply): self
    {
        return new self([$name], function (Builder $query, Request $request) use ($name, $apply): void {
            if ($request->filled($name)) {
                $apply($query, $request->input($name));
            }
        });
    }

    /**
     * The conventional inclusive `from`/`to` day filter.
     *
     * Uses half-open bounds (`>= from 00:00`, `< to+1day 00:00`) rather than
     * `whereDate()`: wrapping the column in SQL's `date()` makes the predicate
     * non-sargable, so the index goes unused and every filtered list degrades to
     * a sequential scan as the table grows.
     */
    public static function dateRange(string $column, string $fromKey = 'from', string $toKey = 'to'): self
    {
        return new self([$fromKey, $toKey], function (Builder $query, Request $request) use ($column, $fromKey, $toKey): void {
            DateRange::apply($query, $request, $column, $fromKey, $toKey);
        });
    }

    /** Search across several columns as one OR group, so it can't leak out of an AND chain. */
    public static function search(string $name, array $columns): self
    {
        return new self([$name], function (Builder $query, Request $request) use ($name, $columns): void {
            if (! $request->filled($name)) {
                return;
            }

            $term = '%'.$request->string($name)->value().'%';

            $query->where(function (Builder $group) use ($columns, $term): void {
                foreach ($columns as $column) {
                    $group->orWhere($column, 'like', $term);
                }
            });
        });
    }

    /**
     * Search across a relation's columns — `?search=widget` matching
     * `product.name` from an inventory-balance list.
     */
    public static function searchRelation(string $name, string $relation, array $columns): self
    {
        return new self([$name], function (Builder $query, Request $request) use ($name, $relation, $columns): void {
            if (! $request->filled($name)) {
                return;
            }

            $term = '%'.$request->string($name)->value().'%';

            $query->whereHas($relation, function (Builder $related) use ($columns, $term): void {
                $related->where(function (Builder $group) use ($columns, $term): void {
                    foreach ($columns as $column) {
                        $group->orWhere($column, 'like', $term);
                    }
                });
            });
        });
    }

    /** @param  Builder<*>  $query */
    public function apply(Builder $query, Request $request): void
    {
        ($this->apply)($query, $request);
    }
}
