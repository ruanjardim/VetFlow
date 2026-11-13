<?php

namespace App\Modules\Dashboard\Services;

use App\Modules\Patients\Models\Patient;

class DashboardPatientService
{
    public function total(): int
    {
        return Patient::query()->available()->count();
    }

    public function latest(int $limit = 5)
    {
        return Patient::query()
            ->available()
            ->with(['tutor', 'animalSpecies'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }
}
