<?php

namespace App\Modules\MedicalRecords\Services;

use App\Modules\MedicalRecords\Models\MedicalRecordExam;
use App\Modules\MedicalRecords\Models\MedicalRecordExamResult;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class ExamResultService
{
    public function forRequest(int $requestId): MedicalRecordExam
    {
        return MedicalRecordExam::query()
            ->whereHas('medicalRecord')
            ->with([
                'medicalRecord.patient.tutor',
                'medicalRecord.appointment',
                'exam',
                'result.createdBy',
                'result.finalizedBy',
                'result.cancelledBy',
            ])
            ->findOrFail($requestId);
    }

    public function saveDraft(int $requestId, array $data): MedicalRecordExamResult
    {
        $examRequest = $this->forRequest($requestId);
        $result = $examRequest->result;

        if ($result && ! $result->isDraft()) {
            throw ValidationException::withMessages([
                'result' => 'Resultados finalizados ou cancelados não podem ser alterados.',
            ]);
        }

        $attributes = Arr::only($data, [
            'collected_at',
            'resulted_at',
            'laboratory_name',
            'result_summary',
            'result_details',
            'reference_notes',
            'notes',
        ]);

        if ($result) {
            $result->update($attributes);

            return $result->refresh();
        }

        return $examRequest->result()->create([
            ...$attributes,
            'clinic_id' => $examRequest->medicalRecord->clinic_id,
            'created_by' => auth()->id(),
            'status' => 'draft',
        ]);
    }

    public function finalize(int $requestId): MedicalRecordExamResult
    {
        $examRequest = $this->forRequest($requestId);
        $result = $examRequest->result;

        if (! $result) {
            throw ValidationException::withMessages([
                'result' => 'Salve o resultado antes de finalizá-lo.',
            ]);
        }

        if (! $result->isDraft()) {
            throw ValidationException::withMessages([
                'result' => 'Somente um resultado em rascunho pode ser finalizado.',
            ]);
        }

        if (blank($result->result_summary) && blank($result->result_details)) {
            throw ValidationException::withMessages([
                'result' => 'Informe o resumo ou os detalhes do resultado antes de finalizar.',
            ]);
        }

        $result->update([
            'status' => 'finalized',
            'resulted_at' => $result->resulted_at ?? now(),
            'finalized_at' => now(),
            'finalized_by' => auth()->id(),
        ]);

        return $result->refresh();
    }

    public function cancel(int $requestId, string $reason): MedicalRecordExamResult
    {
        $examRequest = $this->forRequest($requestId);
        $result = $examRequest->result;

        if (! $result?->isFinalized()) {
            throw ValidationException::withMessages([
                'cancellation_reason' => 'Somente um resultado finalizado pode ser cancelado.',
            ]);
        }

        $result->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => auth()->id(),
            'cancellation_reason' => $reason,
        ]);

        return $result->refresh();
    }
}
