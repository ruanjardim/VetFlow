<?php

namespace App\Core\Traits;

use Illuminate\Database\Eloquent\Builder;

trait HasFilters
{
    protected function applySearch(
        Builder $query,
        ?string $search,
        array $columns
    ): Builder {

        if (!$search) {
            return $query;
        }

        $query->where(function ($q) use ($search, $columns) {

            foreach ($columns as $column) {

                $q->orWhere(
                    $column,
                    'like',
                    "%{$search}%"
                );

            }

        });

        return $query;
    }
}