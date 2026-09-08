<?php

namespace App\Modules\Schedules\Requests;

use App\Http\Requests\Concerns\ValidatesTenantScopedReferences;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreScheduleRequest extends FormRequest
{
    use ValidatesTenantScopedReferences;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'clinic_id' => ['nullable', 'integer', 'exists:clinics,id'],
            'patient_id' => ['nullable', 'integer', $this->existsInCurrentClinic('patients')],
            'tutor_id' => ['nullable', 'integer', $this->existsInCurrentClinic('tutors')],
            'title' => ['nullable', 'string', 'max:255'],
            'scheduled_date' => ['nullable', 'date'],
            'scheduled_time' => ['nullable', 'date_format:H:i'],
            'type' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(['agendado', 'confirmado', 'concluido', 'cancelado'])],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'patient_id.exists' => 'O paciente informado nao foi encontrado.',
            'tutor_id.exists' => 'O responsável informado nao foi encontrado.',
            'scheduled_date.date' => 'Informe uma data valida.',
            'scheduled_time.date_format' => 'Informe uma hora valida.',
            'status.in' => 'Informe um status valido para a agenda.',
        ];
    }
}
