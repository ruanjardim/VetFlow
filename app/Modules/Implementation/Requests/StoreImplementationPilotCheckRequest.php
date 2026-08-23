<?php

namespace App\Modules\Implementation\Requests;

use App\Modules\Implementation\Services\ImplementationPilotChecklistService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreImplementationPilotCheckRequest extends FormRequest
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
            'check_key' => [
                'required',
                'string',
                Rule::in(ImplementationPilotChecklistService::keys()),
            ],
            'completed' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'clinic_id.exists' => 'A clínica não está disponível para este checklist.',
            'check_key.in' => 'O item selecionado não pertence ao checklist do piloto.',
            'notes.max' => 'A observação deve ter no máximo 1000 caracteres.',
        ];
    }
}
