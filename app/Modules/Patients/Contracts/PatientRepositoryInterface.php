<?php

namespace App\Modules\Patients\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PatientRepositoryInterface
{
    public function search(?string $search = null, string $status = 'active', int $perPage = 15): LengthAwarePaginator;
}
