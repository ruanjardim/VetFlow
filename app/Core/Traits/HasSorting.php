<?php

namespace App\Core\Traits;

use Illuminate\Database\Eloquent\Builder;

trait HasSorting
{
    protected function applySorting(
        Builder $query,
        string $sortBy = 'id',
        string $direction = 'desc'
    ): Builder {

        $allowedDirections = ['asc', 'desc'];

        if (!in_array(strtolower($direction), $allowedDirections)) {
            $direction = 'desc';
        }

        return $query->orderBy($sortBy, $direction);
    }
}