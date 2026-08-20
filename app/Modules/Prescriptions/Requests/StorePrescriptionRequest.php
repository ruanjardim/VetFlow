<?php

namespace App\Modules\Prescriptions\Requests;

use App\Http\Requests\Concerns\ValidatesTenantScopedReferences;
use Illuminate\Foundation\Http\FormRequest;

class StorePrescriptionRequest extends FormRequest
{
    use ValidatesTenantScopedReferences;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'medical_record_id' => ['required', 'integer', $this->existsInCurrentClinic('medical_records')],
            'prescribed_at' => ['required', 'date'],
            'general_instructions' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.medication_name' => ['required', 'string', 'max:255'],
            'items.*.concentration' => ['nullable', 'string', 'max:255'],
            'items.*.dosage' => ['required', 'string', 'max:255'],
            'items.*.route' => ['nullable', 'string', 'max:255'],
            'items.*.frequency' => ['required', 'string', 'max:255'],
            'items.*.duration' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['nullable', 'string', 'max:255'],
            'items.*.instructions' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'medical_record_id.required' => 'Selecione o prontuário relacionado.',
            'medical_record_id.exists' => 'O prontuário informado não foi encontrado nesta clínica.',
            'prescribed_at.required' => 'Informe a data e hora da prescrição.',
            'items.required' => 'Inclua pelo menos um item na prescrição.',
            'items.min' => 'Inclua pelo menos um item na prescrição.',
            'items.max' => 'A prescrição aceita no máximo 30 itens.',
            'items.*.medication_name.required' => 'Informe o medicamento em todos os itens.',
            'items.*.dosage.required' => 'Informe a dose em todos os itens.',
            'items.*.frequency.required' => 'Informe a frequência em todos os itens.',
        ];
    }
}
