<?php

namespace App\Modules\Appointments\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['nullable', 'integer', 'exists:patients,id'],
            'tutor_id' => ['nullable', 'integer', 'exists:tutors,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'scheduled_at' => ['required', 'date'],
            'status' => [
                'nullable',
                'string',
                Rule::in([
                    'scheduled',
                    'confirmed',
                    'in_progress',
                    'completed',
                    'cancelled',
                    'no_show',
                ]),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Informe o titulo da consulta.',
            'scheduled_at.required' => 'Informe a data e hora da consulta.',
            'patient_id.exists' => 'O paciente informado nao foi encontrado.',
            'tutor_id.exists' => 'O tutor informado nao foi encontrado.',
            'status.in' => 'Informe um status valido para a consulta.',
        ];
    }
}
