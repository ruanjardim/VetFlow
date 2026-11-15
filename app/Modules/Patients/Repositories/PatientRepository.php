<?php

namespace App\Modules\Patients\Repositories;

use App\Core\Base\BaseRepository;
use App\Modules\Patients\Contracts\PatientRepositoryInterface;
use App\Modules\Patients\Models\Patient;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PatientRepository extends BaseRepository implements PatientRepositoryInterface
{
    public function __construct(Patient $model)
    {
        $this->model = $model;
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->search(null, 'active', $perPage);
    }

    public function search(?string $search = null, string $status = 'active', int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->query()
            ->with(['tutor', 'animalSpecies', 'animalBreed']);

        if ($status !== 'all') {
            $query->where('lifecycle_status', $status);
        }

        if ($search !== null) {
            $term = '%'.$search.'%';

            $query->where(function ($query) use ($term): void {
                $query
                    ->where('name', 'like', $term)
                    ->orWhere('microchip', 'like', $term)
                    ->orWhere('pedigree_number', 'like', $term)
                    ->orWhere('markings', 'like', $term)
                    ->orWhereHas('tutor', fn ($tutorQuery) => $tutorQuery->where('name', 'like', $term));
            });
        }

        return $query->orderBy('name')->paginate($perPage);
    }
}
