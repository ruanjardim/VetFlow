<?php

namespace App\Modules\Hospitalizations\Requests;

use App\Http\Requests\Concerns\ValidatesTenantScopedReferences;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHospitalizationRequest extends FormRequest
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
            'status' => ['required', Rule::in(['hospitalized', 'discharged', 'cancelled'])],
            'accommodation' => ['nullable', 'string', 'max:120'],
            'admitted_at' => ['required', 'date'],
            'expected_discharge_at' => ['nullable', 'date', 'after_or_equal:admitted_at'],
            'discharged_at' => ['nullable', 'date', Rule::requiredIf($this->input('status') === 'discharged'), 'after_or_equal:admitted_at'],
            'admission_reason' => ['required', 'string'],
            'clinical_notes' => ['nullable', 'string'],
            'discharge_notes' => ['nullable', 'string'],
        ];
    }
}
