<?php

namespace App\Support\Query;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Declarative filtering, sorting and pagination for list endpoints.
 *
 * Every `index()` in this app used to hand-roll the same `->when($request->x, …)`
 * chain, which meant three things drifted independently: which columns were
 * sortable, whether the page size was capped, and whether an unrecognised query
 * parameter was an error or silently ignored. A list endpoint now declares what
 * it accepts and this applies it uniformly:
 *
 *   - `sort` is allow-listed, so a client cannot order by an arbitrary column
 *     (an ordering oracle over columns it can't read).
 *   - `per_page` is clamped, so `?per_page=1000000` can't pull an entire table
 *     into memory — a one-request denial of service on a shared API.
 *   - A query key nothing declared is a 422, not a silently unfiltered result.
 *
 * @template TModel of \Illuminate\Database\Eloquent\Model
 */
final class QueryPipeline
{
    /** Hard ceiling on any client-supplied page size. High enough that no legitimate UI hits it. */
    public const MAX_PAGE_SIZE = 200;

    /**
     * Query keys that are part of the list protocol itself rather than a filter,
     * so they never need declaring.
     *
     * Descending order is a `-` prefix on `sort` rather than a separate
     * `direction` key: `direction` is also a real column on `stock_movements`,
     * and a protocol parameter that shadows a filterable column is a collision
     * waiting to happen on the next model that has one.
     */
    private const RESERVED = ['page', 'per_page', 'limit', 'sort'];

    /** @var array<int, AllowedFilter> */
    private array $filters = [];

    /** @var array<int, string> */
    private array $sortable = [];

    private ?string $defaultSort = null;

    private bool $strict = true;

    /** @param  Builder<TModel>  $query */
    private function __construct(
        private readonly Builder $query,
        private readonly Request $request,
    ) {}

    /**
     * @param  Builder<TModel>  $query
     * @return self<TModel>
     */
    public static function for(Builder $query, Request $request): self
    {
        return new self($query, $request);
    }

    /** @return self<TModel> */
    public function allow(AllowedFilter ...$filters): self
    {
        array_push($this->filters, ...$filters);

        return $this;
    }

    /**
     * Free-text search over the given columns, exposed as `?search=`.
     *
     * @return self<TModel>
     */
    public function search(array $columns, string $key = 'search'): self
    {
        return $this->allow(AllowedFilter::search($key, $columns));
    }

    /**
     * Declare the sortable columns and the default order.
     *
     * `?sort=name` sorts ascending, `?sort=-name` descending. `$default` takes the
     * same form, so `'-id'` means newest first.
     *
     * @param  array<int, string>  $columns
     * @return self<TModel>
     */
    public function sortable(array $columns, string $default): self
    {
        $this->sortable = $columns;
        $this->defaultSort = $default;

        return $this;
    }

    /**
     * Accept undeclared query keys instead of rejecting them. For endpoints whose
     * parameters are genuinely open-ended; prefer declaring the filter.
     *
     * @return self<TModel>
     */
    public function lenient(): self
    {
        $this->strict = false;

        return $this;
    }

    /** @return LengthAwarePaginator<int, TModel> */
    public function paginate(int $perPage = 25): LengthAwarePaginator
    {
        return $this->build()
            ->paginate($this->perPage($perPage))
            ->withQueryString();
    }

    /**
     * A non-paginated "top N" list. Still capped: an uncapped list endpoint is
     * the same denial of service as an uncapped page size.
     *
     * @return Collection<int, TModel>
     */
    public function limit(int $default): Collection
    {
        $requested = $this->request->integer('limit');

        return $this->build()
            ->limit($requested < 1 ? $default : min($requested, self::MAX_PAGE_SIZE))
            ->get();
    }

    /** The assembled query, for a caller that needs to keep chaining. */
    public function toQuery(): Builder
    {
        return $this->build();
    }

    /** @return Builder<TModel> */
    private function build(): Builder
    {
        if ($this->strict) {
            $this->rejectUndeclaredParameters();
        }

        foreach ($this->filters as $filter) {
            $filter->apply($this->query, $this->request);
        }

        if ($this->defaultSort !== null) {
            [$column, $direction] = $this->resolveSort();
            $this->query->orderBy($column, $direction);
        }

        return $this->query;
    }

    /** @return array{0: string, 1: string} */
    private function resolveSort(): array
    {
        $requested = (string) $this->request->input('sort', $this->defaultSort);

        $descending = str_starts_with($requested, '-');
        $column = $descending ? substr($requested, 1) : $requested;

        if (! in_array($column, $this->sortable, true)) {
            throw ValidationException::withMessages([
                'sort' => [
                    'Invalid sort column. Allowed: '.implode(', ', $this->sortable).
                    '. Prefix with "-" to sort descending.',
                ],
            ]);
        }

        return [$column, $descending ? 'desc' : 'asc'];
    }

    private function perPage(int $default): int
    {
        $requested = $this->request->integer('per_page');

        return $requested < 1 ? $default : min($requested, self::MAX_PAGE_SIZE);
    }

    /**
     * A parameter nothing consumes almost always means the caller expected a
     * filter that isn't there — and an unfiltered list looks exactly like a
     * legitimately unfiltered one, so it fails loudly instead.
     */
    private function rejectUndeclaredParameters(): void
    {
        $declared = self::RESERVED;

        foreach ($this->filters as $filter) {
            array_push($declared, ...$filter->names);
        }

        $unknown = array_diff(array_keys($this->request->query()), $declared);

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                array_key_first(array_flip($unknown)) => [
                    'Unknown query parameter(s): '.implode(', ', $unknown).
                    '. Supported: '.implode(', ', array_diff($declared, self::RESERVED)).'.',
                ],
            ]);
        }
    }
}
