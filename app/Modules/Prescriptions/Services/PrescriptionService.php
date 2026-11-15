<?php

namespace App\Modules\Prescriptions\Services;

use App\Core\Base\BaseService;
use App\Models\User;
use App\Modules\MedicalRecords\Models\MedicalRecord;
use App\Modules\Prescriptions\Contracts\PrescriptionRepositoryInterface;
use App\Modules\Prescriptions\Models\Prescription;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PrescriptionService extends BaseService
{
    public function __construct(PrescriptionRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function create(array $data): Model
    {
        $medicalRecord = MedicalRecord::query()->findOrFail((int) $data['medical_record_id']);
        $responsibleVeterinarian = $this->resolveResponsibleVeterinarian(
            (int) $data['responsible_veterinarian_id'],
            (int) $medicalRecord->clinic_id
        );
        $items = $data['items'];

        return DB::transaction(function () use ($data, $items, $medicalRecord, $responsibleVeterinarian): Prescription {
            /** @var Prescription $prescription */
            $prescription = $this->repository->create([
                ...Arr::only($data, ['prescribed_at', 'general_instructions', 'notes']),
                'clinic_id' => $medicalRecord->clinic_id,
                'patient_id' => $medicalRecord->patient_id,
                'medical_record_id' => $medicalRecord->id,
                'responsible_veterinarian_id' => $responsibleVeterinarian->id,
                'created_by' => auth()->id(),
                'status' => 'draft',
            ]);

            $this->replaceItems($prescription, $items);

            return $prescription->load(['patient.tutor', 'medicalRecord', 'responsibleVeterinarian', 'createdBy', 'items']);
        });
    }

    public function update(int $id, array $data): Model
    {
        /** @var Prescription $prescription */
        $prescription = $this->repository->findOrFail($id);
        $this->assertDraft($prescription);
        $responsibleVeterinarian = $this->resolveResponsibleVeterinarian(
            (int) $data['responsible_veterinarian_id'],
            (int) $prescription->clinic_id
        );
        $items = $data['items'];

        return DB::transaction(function () use ($prescription, $data, $items, $responsibleVeterinarian): Prescription {
            $this->repository->update(
                $prescription,
                [
                    ...Arr::only($data, ['prescribed_at', 'general_instructions', 'notes']),
                    'responsible_veterinarian_id' => $responsibleVeterinarian->id,
                ]
            );
            $this->replaceItems($prescription, $items);

            return $prescription->refresh()->load([
                'patient.tutor',
                'medicalRecord.appointment',
                'responsibleVeterinarian',
                'createdBy',
                'items',
            ]);
        });
    }

    public function finalize(int $id): Prescription
    {
        /** @var Prescription $prescription */
        $prescription = $this->repository->findOrFail($id);
        $this->assertDraft($prescription);

        if ($prescription->items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Inclua pelo menos um item antes de finalizar a prescrição.',
            ]);
        }

        $responsibleVeterinarian = $prescription->responsibleVeterinarian;

        if (! $responsibleVeterinarian
            || ! $responsibleVeterinarian->active
            || (int) $responsibleVeterinarian->clinic_id !== (int) $prescription->clinic_id
            || ! $responsibleVeterinarian->hasRole('veterinario')) {
            throw ValidationException::withMessages([
                'responsible_veterinarian_id' => 'Selecione um veterinário ativo desta clínica antes de finalizar.',
            ]);
        }

        if (! $responsibleVeterinarian->veterinary_license_number
            || ! $responsibleVeterinarian->veterinary_license_state) {
            throw ValidationException::withMessages([
                'responsible_veterinarian_id' => 'Cadastre o CRMV e a UF do veterinário responsável antes de finalizar.',
            ]);
        }

        $this->repository->update($prescription, [
            'status' => 'finalized',
            'finalized_at' => now(),
            'finalized_by' => auth()->id(),
            'responsible_name' => $responsibleVeterinarian->name,
            'responsible_license_number' => $responsibleVeterinarian->veterinary_license_number,
            'responsible_license_state' => $responsibleVeterinarian->veterinary_license_state,
        ]);

        return $prescription->refresh();
    }

    public function cancel(int $id, string $reason): Prescription
    {
        /** @var Prescription $prescription */
        $prescription = $this->repository->findOrFail($id);

        if (! $prescription->isFinalized()) {
            throw ValidationException::withMessages([
                'cancellation_reason' => 'Somente uma prescrição finalizada pode ser cancelada.',
            ]);
        }

        $this->repository->update($prescription, [
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => auth()->id(),
            'cancellation_reason' => $reason,
        ]);

        return $prescription->refresh();
    }

    /** @param array<int, array<string, mixed>> $items */
    private function replaceItems(Prescription $prescription, array $items): void
    {
        $prescription->items()->delete();
        $prescription->items()->createMany(
            collect(array_values($items))
                ->map(fn (array $item, int $position): array => [
                    ...Arr::only($item, [
                        'medication_name',
                        'concentration',
                        'dosage',
                        'route',
                        'frequency',
                        'duration',
                        'quantity',
                        'instructions',
                    ]),
                    'position' => $position + 1,
                ])
                ->all()
        );
    }

    private function assertDraft(Prescription $prescription): void
    {
        if (! $prescription->isDraft()) {
            throw ValidationException::withMessages([
                'prescription' => 'Prescrições finalizadas ou canceladas não podem ser alteradas.',
            ]);
        }
    }

    private function resolveResponsibleVeterinarian(int $userId, int $clinicId): User
    {
        $veterinarian = User::query()
            ->whereKey($userId)
            ->where('clinic_id', $clinicId)
            ->where('active', true)
            ->whereHas('roles', fn ($query) => $query
                ->where('roles.slug', 'veterinario')
                ->where('roles.active', true))
            ->first();

        if (! $veterinarian) {
            throw ValidationException::withMessages([
                'responsible_veterinarian_id' => 'Selecione um veterinário ativo desta clínica.',
            ]);
        }

        return $veterinarian;
    }
}
