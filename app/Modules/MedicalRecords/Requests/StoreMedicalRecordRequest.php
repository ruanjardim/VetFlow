<?php

namespace App\Modules\MedicalRecords\Requests;

use App\Http\Requests\Concerns\ValidatesTenantScopedReferences;
use App\Modules\Appointments\Models\Appointment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMedicalRecordRequest extends FormRequest
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
            'appointment_id' => [
                'required',
                'integer',
                $this->existsInCurrentClinic('appointments'),
                Rule::unique('medical_records', 'appointment_id')->whereNull('deleted_at'),
            ],
            'examined_at' => ['required', 'date'],
            'chief_complaint' => ['nullable', 'string'],
            'anamnesis' => ['nullable', 'string'],
            'clinical_findings' => ['nullable', 'string'],
            'diagnosis' => ['nullable', 'string'],
            'pathology_ids' => ['nullable', 'array'],
            'pathology_ids.*' => ['integer', 'distinct'],
            'new_pathology' => ['nullable', 'string', 'max:160'],
            'treatment_plan' => ['nullable', 'string'],
            'prescription_notes' => ['nullable', 'string'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'temperature' => ['nullable', 'numeric', 'between:20,50'],
            'heart_rate' => ['nullable', 'integer', 'between:1,400'],
            'respiratory_rate' => ['nullable', 'integer', 'between:1,300'],
            'hydration' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'exam_ids' => ['nullable', 'array'],
            'exam_ids.*' => ['integer', 'distinct'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('patient_id') || $validator->errors()->has('appointment_id')) {
                return;
            }

            $appointment = Appointment::query()->find($this->integer('appointment_id'));

            if (! $appointment || (int) $appointment->patient_id !== $this->integer('patient_id')) {
                $validator->errors()->add(
                    'patient_id',
                    'O paciente deve ser o mesmo vinculado à consulta selecionada.'
                );
            }
        });
    }
}
