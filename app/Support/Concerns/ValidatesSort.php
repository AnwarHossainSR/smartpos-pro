<?php

namespace App\Support\Concerns;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Whitelists `sort`/`direction` query params so a client can't order by an arbitrary column. */
trait ValidatesSort
{
    /**
     * @param  string[]  $allowedColumns
     * @return array{0: string, 1: string}
     */
    protected function resolveSort(Request $request, array $allowedColumns, string $default): array
    {
        $sort = $request->input('sort', $default);
        $direction = strtolower((string) $request->input('direction', 'asc'));

        if (! in_array($sort, $allowedColumns, true)) {
            throw ValidationException::withMessages([
                'sort' => ['Invalid sort column. Allowed: '.implode(', ', $allowedColumns).'.'],
            ]);
        }

        if (! in_array($direction, ['asc', 'desc'], true)) {
            throw ValidationException::withMessages([
                'direction' => ['Invalid sort direction. Allowed: asc, desc.'],
            ]);
        }

        return [$sort, $direction];
    }
}
