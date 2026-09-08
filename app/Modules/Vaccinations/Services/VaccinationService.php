<?php

namespace App\Modules\Vaccinations\Services;

use App\Core\Base\BaseService;
use App\Modules\Patients\Models\Patient;
use App\Modules\Vaccinations\Contracts\VaccinationRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class VaccinationService extends BaseService
{
    public function __construct(
        VaccinationRepositoryInterface $repository,
        private readonly VaccineCatalogService $vaccines
    ) {
        $this->repository = $repository;
    }

    public function create(array $data): Model
    {
        $patient = Patient::query()->findOrFail($data['patient_id']);

        $data = $this->prepareData($data, $patient, true);
        $data['clinic_id'] = $patient->clinic_id;
        $data['created_by'] = auth()->id();

        return parent::create($data);
    }

    public function update(int $id, array $data): Model
    {
        $patient = Patient::query()->findOrFail($data['patient_id']);

        $data = $this->prepareData($data, $patient, false);
        $data['clinic_id'] = $patient->clinic_id;

        return parent::update($id, $data);
    }

    private function prepareData(array $data, Patient $patient, bool $isCreating): array
    {
        $hasCatalogSelection = array_key_exists('animal_vaccine_id', $data);
        $vaccine = $hasCatalogSelection
            ? $this->vaccines->resolveForVaccination(
                $data['animal_vaccine_id'] ? (int) $data['animal_vaccine_id'] : null,
                $patient,
                (int) $patient->clinic_id
            )
            : null;

        if ($vaccine) {
            $data['animal_vaccine_id'] = $vaccine->id;
            $data['vaccine_name'] = $vaccine->name;

            if (empty($data['next_due_at']) && $vaccine->recommended_interval_days) {
                $referenceDate = $data['applied_at'] ?? $data['scheduled_for'];
                $data['next_due_at'] = Carbon::parse($referenceDate)
                    ->addDays($vaccine->recommended_interval_days)
                    ->toDateString();
            }
        } elseif ($isCreating || $hasCatalogSelection) {
            $data['animal_vaccine_id'] = null;
        }

        if (array_key_exists('vaccine_name', $data) && $data['vaccine_name'] !== null) {
            $data['vaccine_name'] = Str::of($data['vaccine_name'])->squish()->value();
        }

        return $data;
    }
}
