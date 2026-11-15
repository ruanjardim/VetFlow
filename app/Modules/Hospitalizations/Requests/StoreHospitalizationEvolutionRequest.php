<?php

namespace App\Modules\Hospitalizations\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreHospitalizationEvolutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'observed_at' => ['required', 'date', 'before_or_equal:now'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'temperature' => ['nullable', 'numeric', 'between:20,50'],
            'heart_rate' => ['nullable', 'integer', 'between:1,400'],
            'respiratory_rate' => ['nullable', 'integer', 'between:1,300'],
            'notes' => ['required', 'string', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'observed_at.required' => 'Informe a data e hora da evolução.',
            'observed_at.before_or_equal' => 'A evolução não pode ser registrada com uma data futura.',
            'notes.required' => 'Descreva a evolução observada.',
        ];
    }
}
