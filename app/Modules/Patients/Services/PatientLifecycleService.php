<?php

namespace App\Modules\Patients\Services;

use App\Modules\Appointments\Models\Appointment;
use App\Modules\Audit\Services\AuditTrailService;
use App\Modules\Patients\Models\Patient;
use App\Modules\Patients\Support\PatientLifecycleStatus;
use App\Modules\Schedules\Models\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PatientLifecycleService
{
    public function __construct(private readonly AuditTrailService $audit) {}

    public function transition(int $patientId, string $toStatus, string $reason): Patient
    {
        return DB::transaction(function () use ($patientId, $toStatus, $reason): Patient {
            $patient = Patient::query()->lockForUpdate()->findOrFail($patientId);
            $fromStatus = (string) ($patient->lifecycle_status ?: PatientLifecycleStatus::ACTIVE);

            if (! PatientLifecycleStatus::canTransition($fromStatus, $toStatus)) {
                throw ValidationException::withMessages([
                    'status' => 'Esta transição de estado não é permitida para o paciente.',
                ]);
            }

            $cancelledAppointments = 0;
            $cancelledSchedules = 0;
            $changedAt = now();

            if (in_array($toStatus, [PatientLifecycleStatus::ARCHIVED, PatientLifecycleStatus::DECEASED], true)) {
                $cancelledAppointments = Appointment::query()
                    ->where('patient_id', $patient->id)
                    ->whereIn('status', ['scheduled', 'confirmed'])
                    ->where('scheduled_at', '>=', $changedAt)
                    ->update(['status' => 'cancelled', 'updated_at' => $changedAt]);
                $cancelledSchedules = Schedule::query()
                    ->where('patient_id', $patient->id)
                    ->whereIn('status', ['agendado', 'confirmado'])
                    ->where(function ($query) use ($changedAt): void {
                        $query->whereDate('scheduled_date', '>', $changedAt->toDateString())
                            ->orWhere(function ($query) use ($changedAt): void {
                                $query->whereDate('scheduled_date', $changedAt->toDateString())
                                    ->where(function ($query) use ($changedAt): void {
                                        $query->whereNull('scheduled_time')
                                            ->orWhereTime('scheduled_time', '>=', $changedAt->format('H:i:s'));
                                    });
                            });
                    })
                    ->update(['status' => 'cancelado', 'updated_at' => $changedAt]);
            }

            $before = [
                'lifecycle_status' => $fromStatus,
                'lifecycle_reason' => $patient->lifecycle_reason,
            ];
            $patient->forceFill([
                'lifecycle_status' => $toStatus,
                'lifecycle_reason' => $reason,
                'lifecycle_changed_at' => $changedAt,
                'lifecycle_changed_by_user_id' => auth()->id(),
            ])->save();

            $this->audit->record(
                $this->eventName($fromStatus, $toStatus),
                $patient,
                $before,
                [
                    'lifecycle_status' => $toStatus,
                    'lifecycle_reason' => $reason,
                ],
                metadata: [
                    'reason' => $reason,
                    'cancelled_appointments' => $cancelledAppointments,
                    'cancelled_schedules' => $cancelledSchedules,
                ],
                subjectLabel: $patient->name
            );

            return $patient->refresh();
        });
    }

    private function eventName(string $fromStatus, string $toStatus): string
    {
        if ($toStatus === PatientLifecycleStatus::ARCHIVED) {
            return 'patient.lifecycle.archived';
        }

        if ($toStatus === PatientLifecycleStatus::DECEASED) {
            return 'patient.lifecycle.deceased';
        }

        return $fromStatus === PatientLifecycleStatus::DECEASED
            ? 'patient.lifecycle.death-corrected'
            : 'patient.lifecycle.restored';
    }
}
