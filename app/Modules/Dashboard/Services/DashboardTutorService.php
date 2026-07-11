<?php

namespace App\Modules\Dashboard\Services;

use App\Modules\Tutors\Models\Tutor;

class DashboardTutorService
{
    public function total(): int
    {
        return Tutor::count();
    }

    public function latest(int $limit = 5)
    {
        return Tutor::orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }
}