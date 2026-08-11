<?php

namespace App\Modules\Vaccinations\Services;

use App\Core\Base\BaseService;
use App\Modules\Patients\Models\Patient;
use App\Modules\Vaccinations\Contracts\VaccinationRepositoryInterface;
use Illuminate\Database\Eloquent\Model;

class VaccinationService extends BaseService
{
    public function __construct(VaccinationRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function create(array $data): Model
    {
        $patient = Patient::query()->findOrFail($data['patient_id']);

        $data['clinic_id'] = $patient->clinic_id;
        $data['created_by'] = auth()->id();

        return parent::create($data);
    }
}
