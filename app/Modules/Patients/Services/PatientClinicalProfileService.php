<?php

namespace App\Modules\Patients\Services;

use App\Modules\Patients\Contracts\PatientRepositoryInterface;
use Illuminate\Support\Collection;

class PatientClinicalProfileService
{
    public function __construct(private readonly PatientRepositoryInterface $patients)
    {
    }

    /**
     * @param  array{appointments: bool, medicalRecords: bool, vaccinations: bool, hospitalizations: bool}  $visibility
     * @return array<string, mixed>
     */
    public function forPatient(int $patientId, array $visibility): array
    {
        $patient = $this->patients->findOrFail($patientId);

        $patient->loadMissing(['tutor', 'animalSpecies', 'animalBreed', 'animalCoat']);

        $appointments = $visibility['appointments']
            ? $patient->appointments()
                ->with('medicalRecord')
                ->latest('scheduled_at')
                ->limit(10)
                ->get()
            : new Collection();

        $medicalRecords = $visibility['medicalRecords']
            ? $patient->medicalRecords()
                ->with(['appointment', 'pathologies', 'examRequests'])
                ->latest('examined_at')
                ->limit(10)
                ->get()
            : new Collection();

        $vaccinations = $visibility['vaccinations']
            ? $patient->vaccinations()
                ->with('vaccine')
                ->latest('scheduled_for')
                ->limit(10)
                ->get()
            : new Collection();

        $hospitalizations = $visibility['hospitalizations']
            ? $patient->hospitalizations()
                ->with(['medicalRecord', 'admittedBy'])
                ->latest('admitted_at')
                ->limit(10)
                ->get()
            : new Collection();

        return compact('patient', 'appointments', 'medicalRecords', 'vaccinations', 'hospitalizations', 'visibility');
    }
}
