<?php

namespace App\Modules\Sales\Requests;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuickPdvCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->hasPermission('sales.manage')
            && $user->hasPermission('tutors.manage')
            && $user->hasPermission('patients.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(collect($this->all())
            ->map(fn ($value) => is_string($value) ? trim($value) : $value)
            ->all());
    }

    public function rules(): array
    {
        $clinicId = app(TenantContext::class)->clinicId();
        $clinicExists = Rule::exists('clinics', 'id')
            ->where('active', true)
            ->whereNull('deleted_at');

        return [
            'clinic_id' => $clinicId === null
                ? ['required', 'integer', $clinicExists]
                : ['nullable', 'integer', Rule::in([$clinicId])],
            'tutor_name' => ['required', 'string', 'max:255'],
            'tutor_phone' => ['required', 'string', 'max:20'],
            'tutor_email' => ['nullable', 'email', 'max:255'],
            'patient_name' => ['required', 'string', 'max:255'],
            'patient_species' => ['nullable', 'string', 'max:255'],
            'patient_breed' => ['nullable', 'string', 'max:255'],
            'patient_weight' => ['nullable', 'numeric', 'gt:0', 'max:999999.99'],
        ];
    }

    public function messages(): array
    {
        return [
            'clinic_id.required' => 'Selecione a clínica antes de cadastrar.',
            'clinic_id.exists' => 'A clínica selecionada não está disponível.',
            'tutor_name.required' => 'Informe o nome do responsável.',
            'tutor_phone.required' => 'Informe o telefone do responsável.',
            'tutor_email.email' => 'Informe um e-mail válido.',
            'patient_name.required' => 'Informe o nome do pet.',
            'patient_weight.numeric' => 'Informe um peso válido.',
            'patient_weight.gt' => 'O peso deve ser maior que zero.',
        ];
    }
}
