<?php

namespace App\Modules\Patients\Services;

use App\Modules\Patients\Models\Patient;
use App\Modules\Patients\Models\PatientClinicalAlert;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PatientClinicalAlertService
{
    public function create(int $patientId, array $data): PatientClinicalAlert
    {
        $patient = Patient::query()->findOrFail($patientId);

        return $patient->clinicalAlerts()->create([
            ...Arr::only($data, ['title', 'details']),
            'clinic_id' => $patient->clinic_id,
            'created_by' => auth()->id(),
            'status' => 'active',
        ]);
    }

    public function resolve(int $patientId, int $alertId, string $resolutionNotes): PatientClinicalAlert
    {
        return DB::transaction(function () use ($patientId, $alertId, $resolutionNotes): PatientClinicalAlert {
            Patient::query()->findOrFail($patientId);

            $alert = PatientClinicalAlert::query()
                ->where('patient_id', $patientId)
                ->lockForUpdate()
                ->findOrFail($alertId);

            if (! $alert->isActive()) {
                throw ValidationException::withMessages([
                    'resolution_notes' => 'Este alerta clínico já foi resolvido.',
                ]);
            }

            $alert->update([
                'status' => 'resolved',
                'resolved_at' => now(),
                'resolved_by' => auth()->id(),
                'resolution_notes' => $resolutionNotes,
            ]);

            return $alert->refresh();
        });
    }
}
