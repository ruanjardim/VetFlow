<?php

namespace App\Modules\Patients\Services;

use App\Modules\Hospitalizations\Models\HospitalizationEvolution;
use App\Modules\Patients\Contracts\PatientRepositoryInterface;
use Illuminate\Support\Collection;

class PatientClinicalProfileService
{
    public function __construct(
        private readonly PatientRepositoryInterface $patients,
        private readonly PatientClinicalTimelineService $timeline,
    ) {}

    /**
     * @param  array{appointments: bool, medicalRecords: bool, prescriptions: bool, vaccinations: bool, hospitalizations: bool}  $visibility
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
            : new Collection;

        $medicalRecords = $visibility['medicalRecords']
            ? $patient->medicalRecords()
                ->with([
                    'appointment',
                    'createdBy',
                    'pathologies',
                    'examRequests.result.createdBy',
                    'examRequests.result.finalizedBy',
                    'examRequests.result.cancelledBy',
                ])
                ->latest('examined_at')
                ->limit(10)
                ->get()
            : new Collection;

        $activeClinicalAlerts = $visibility['medicalRecords']
            ? $patient->activeClinicalAlerts()
                ->with('createdBy')
                ->get()
            : new Collection;

        $resolvedClinicalAlerts = $visibility['medicalRecords']
            ? $patient->clinicalAlerts()
                ->where('status', 'resolved')
                ->with(['createdBy', 'resolvedBy'])
                ->latest('resolved_at')
                ->limit(10)
                ->get()
            : new Collection;

        $prescriptions = $visibility['prescriptions']
            ? $patient->prescriptions()
                ->with(['medicalRecord', 'items', 'createdBy'])
                ->latest('prescribed_at')
                ->limit(10)
                ->get()
            : new Collection;

        $vaccinations = $visibility['vaccinations']
            ? $patient->vaccinations()
                ->with(['vaccine', 'createdBy'])
                ->latest('scheduled_for')
                ->limit(10)
                ->get()
            : new Collection;

        $hospitalizationEvolutions = $visibility['hospitalizations']
            ? HospitalizationEvolution::query()
                ->whereHas('hospitalization', fn ($query) => $query->where('patient_id', $patient->id))
                ->with(['hospitalization', 'recordedBy'])
                ->latest('observed_at')
                ->limit(20)
                ->get()
            : new Collection;

        $hospitalizations = $visibility['hospitalizations']
            ? $patient->hospitalizations()
                ->with(['medicalRecord', 'admittedBy'])
                ->withCount('evolutions')
                ->latest('admitted_at')
                ->limit(10)
                ->get()
            : new Collection;

        $clinicalTimeline = $this->timeline->build(compact(
            'appointments',
            'medicalRecords',
            'activeClinicalAlerts',
            'resolvedClinicalAlerts',
            'prescriptions',
            'vaccinations',
            'hospitalizations',
            'hospitalizationEvolutions'
        ));

        return compact(
            'patient',
            'appointments',
            'medicalRecords',
            'activeClinicalAlerts',
            'resolvedClinicalAlerts',
            'prescriptions',
            'vaccinations',
            'hospitalizations',
            'clinicalTimeline',
            'visibility'
        );
    }
}
