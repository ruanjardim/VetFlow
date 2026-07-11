<?php

namespace App\Modules\Dashboard\Services;

use App\Modules\Tutors\Models\Tutor;
use Illuminate\Database\Eloquent\Collection;

class DashboardLatestTutorService
{
    public function getLatest(int $limit = 5): Collection
    {
        return Tutor::query()
            ->latest()
            ->limit($limit)
            ->get();
    }
}