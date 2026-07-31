<?php

namespace App\Http\Controllers;

use App\Support\Query\DateRange;
use App\Support\Query\QueryPipeline;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;

/**
 * Resource list endpoints declare their filters through {@see QueryPipeline},
 * which owns paging, sorting and the page-size cap. What remains here is for the
 * aggregate report endpoints, whose queries are hand-built `selectRaw` rollups
 * rather than resource lists and so have nothing to declare.
 */
abstract class Controller
{
    /**
     * Row cap for a non-paginated "top N" endpoint.
     *
     * `limit` comes straight off the query string, so leaving it uncapped lets any
     * authenticated user pull an entire table into memory with `?limit=1000000` —
     * a one-request denial of service against a shared, multi-tenant API.
     */
    protected function limit(Request $request, int $default): int
    {
        $requested = $request->integer('limit');

        return $requested < 1 ? $default : min($requested, QueryPipeline::MAX_PAGE_SIZE);
    }

    /**
     * The conventional inclusive `from`/`to` day filter, for the report queries
     * that build their own builder. Designed for `->tap()`.
     *
     * @param  EloquentBuilder<*>|QueryBuilder  $query
     */
    protected function filterByDateRange(EloquentBuilder|QueryBuilder $query, Request $request, string $column): void
    {
        DateRange::apply($query, $request, $column);
    }
}
