<?php

namespace App\Modules\Implementation\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreImplementationPilotReleaseRequest extends FormRequest
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
            'release_owner' => ['required', 'string', 'max:150'],
            'support_owner' => ['required', 'string', 'max:150'],
            'planned_start_date' => ['nullable', 'date'],
            'scope' => ['required', 'string', 'max:5000'],
            'release_notes' => ['required', 'string', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'clinic_id.exists' => 'A clínica não está disponível para este plano de piloto.',
            'release_owner.required' => 'Informe o responsável operacional pelo piloto.',
            'support_owner.required' => 'Informe o responsável pelo suporte do piloto.',
            'scope.required' => 'Descreva o escopo funcional do piloto.',
            'release_notes.required' => 'Registre as notas desta liberação piloto.',
        ];
    }
}
