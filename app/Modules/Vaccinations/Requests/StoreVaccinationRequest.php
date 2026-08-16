<?php

namespace App\Modules\Vaccinations\Requests;

use App\Http\Requests\Concerns\ValidatesTenantScopedReferences;
use App\Modules\MedicalRecords\Models\MedicalRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreVaccinationRequest extends FormRequest
{
    use ValidatesTenantScopedReferences;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'integer', $this->existsInCurrentClinic('patients')],
            'medical_record_id' => ['nullable', 'integer', $this->existsInCurrentClinic('medical_records')],
            'animal_vaccine_id' => ['nullable', 'integer'],
            'vaccine_name' => ['required_without:animal_vaccine_id', 'nullable', 'string', 'max:255'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'batch_number' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['scheduled', 'applied', 'skipped'])],
            'scheduled_for' => ['required', 'date'],
            'applied_at' => ['nullable', 'date', Rule::requiredIf($this->input('status') === 'applied')],
            'next_due_at' => ['nullable', 'date', 'after_or_equal:scheduled_for'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('patient_id') || $validator->errors()->has('medical_record_id')) {
                return;
            }

            $medicalRecordId = $this->integer('medical_record_id');

            if ($medicalRecordId === 0) {
                return;
            }

            $medicalRecord = MedicalRecord::query()->find($medicalRecordId);

            if (! $medicalRecord || (int) $medicalRecord->patient_id !== $this->integer('patient_id')) {
                $validator->errors()->add(
                    'medical_record_id',
                    'O prontuário relacionado deve pertencer ao paciente selecionado.'
                );
            }
        });
    }
}
