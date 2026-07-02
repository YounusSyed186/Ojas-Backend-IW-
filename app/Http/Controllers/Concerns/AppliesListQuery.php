<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

trait AppliesListQuery
{
    protected function applyListQuery(
        Builder $query,
        Request $request,
        array $searchColumns = [],
        array $filterColumns = [],
        string $defaultSort = 'created_at',
        string $defaultDirection = 'desc',
    ): LengthAwarePaginator {
        if ($search = $request->query('search')) {
            $query->where(function (Builder $builder) use ($searchColumns, $search): void {
                foreach ($searchColumns as $column) {
                    if (str_contains($column, '.')) {
                        [$relation, $field] = explode('.', $column, 2);
                        $builder->orWhereHas($relation, fn (Builder $relationQuery) => $relationQuery->where($field, 'like', "%{$search}%"));
                    } else {
                        $builder->orWhere($column, 'like', "%{$search}%");
                    }
                }
            });
        }

        foreach ($filterColumns as $column) {
            if ($request->filled($column)) {
                $query->where($column, $request->query($column));
            }
        }

        $sort = (string) $request->query('sort', $defaultSort);
        $direction = strtolower((string) $request->query('direction', $defaultDirection));
        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = $defaultDirection;
        }

        $query->orderBy($sort, $direction);

        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);

        return $query->paginate($perPage);
    }
}
