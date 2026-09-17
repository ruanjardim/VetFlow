<?php

namespace App\Modules\Access\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreAccessUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'clinic_id' => [
                'nullable',
                'integer',
                Rule::exists('clinics', 'id')->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:30'],
            'position' => ['nullable', 'string', 'max:120'],
            'veterinary_license_number' => ['nullable', 'required_with:veterinary_license_state', 'string', 'max:30'],
            'veterinary_license_state' => ['nullable', 'required_with:veterinary_license_number', 'string', 'size:2', 'regex:/^[A-Za-z]{2}$/'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'active' => ['required', 'boolean'],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('roles', 'id')->where(
                    fn ($query) => $query
                        ->whereNull('clinic_id')
                        ->where('system', true)
                        ->where('active', true)
                        ->whereNull('deleted_at')
                ),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'clinic_id.exists' => 'Selecione uma clinica valida.',
            'name.required' => 'Informe o nome do colaborador.',
            'email.required' => 'Informe o e-mail de acesso.',
            'email.email' => 'Informe um e-mail valido.',
            'email.unique' => 'Este e-mail ja esta em uso.',
            'password.required' => 'Informe uma senha para o novo colaborador.',
            'password.confirmed' => 'A confirmacao da senha nao confere.',
            'veterinary_license_number.required_with' => 'Informe o numero do CRMV junto com a UF.',
            'veterinary_license_state.required_with' => 'Informe a UF do CRMV.',
            'veterinary_license_state.size' => 'A UF do CRMV deve ter duas letras.',
            'veterinary_license_state.regex' => 'A UF do CRMV deve conter somente letras.',
            'role_ids.required' => 'Selecione ao menos um perfil de acesso.',
            'role_ids.min' => 'Selecione ao menos um perfil de acesso.',
            'role_ids.*.exists' => 'Um dos perfis selecionados nao esta disponivel.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('veterinary_license_state')) {
            $this->merge([
                'veterinary_license_state' => strtoupper((string) $this->input('veterinary_license_state')),
            ]);
        }
    }
}
