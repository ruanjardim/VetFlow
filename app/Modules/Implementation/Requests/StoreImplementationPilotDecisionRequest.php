<?php

namespace App\Modules\Implementation\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreImplementationPilotDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clinicId = $this->user()?->clinic_id;
        $clinicRule = Rule::exists('clinics', 'id')
            ->where(fn ($query) => $query
                ->where('active', true)
                ->whereNull('deleted_at'));

        if ($clinicId !== null) {
            $clinicRule->where(fn ($query) => $query->where('id', $clinicId));
        }

        return [
            'clinic_id' => ['required', 'integer', $clinicRule],
            'decision' => ['required', Rule::in(['approved', 'held'])],
            'notes' => [
                'nullable',
                'string',
                'max:2000',
                Rule::requiredIf(fn (): bool => $this->input('decision') === 'held'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'clinic_id.exists' => 'A clínica não está disponível para esta decisão.',
            'decision.in' => 'Selecione uma decisão válida para o piloto.',
            'notes.required' => 'Explique por que o piloto permanecerá em espera.',
        ];
    }
}
