<?php

namespace App\Modules\Patients\Requests;

use App\Modules\Patients\Support\PatientLifecycleStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangePatientLifecycleRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'reason' => trim((string) $this->input('reason', '')),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(PatientLifecycleStatus::keys())],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Informe o novo estado do paciente.',
            'status.in' => 'Informe um estado válido para o paciente.',
            'reason.required' => 'Informe a justificativa para esta alteração.',
            'reason.min' => 'A justificativa deve ter pelo menos 10 caracteres.',
            'reason.max' => 'A justificativa deve ter no máximo 2.000 caracteres.',
        ];
    }
}
