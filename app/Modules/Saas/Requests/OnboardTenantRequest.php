<?php

namespace App\Modules\Saas\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class OnboardTenantRequest extends FormRequest
{
    public const BUSINESS_TYPES = ['veterinary_clinic', 'pet_shop', 'feed_store', 'grooming', 'mixed', 'other'];

    public const DOCUMENT_TYPES = ['cpf', 'cnpj'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $clinic = (array) $this->input('clinic', []);
        $admin = (array) $this->input('admin', []);
        $clinic['document_type'] = in_array($clinic['document_type'] ?? null, self::DOCUMENT_TYPES, true)
            ? $clinic['document_type']
            : 'cnpj';

        foreach (['cnpj', 'phone', 'whatsapp'] as $field) {
            if (array_key_exists($field, $clinic)) {
                $clinic[$field] = $this->digits($clinic[$field]);
            }
        }

        if (array_key_exists('phone', $admin)) {
            $admin['phone'] = $this->digits($admin['phone']);
        }

        $this->merge(['clinic' => $clinic, 'admin' => $admin]);
    }

    public function rules(): array
    {
        $documentType = $this->input('clinic.document_type', 'cnpj');
        $documentLength = $documentType === 'cpf' ? 11 : 14;
        $rules = [
            'clinic.corporate_name' => ['required', 'string', 'max:255'],
            'clinic.trade_name' => ['required', 'string', 'max:255'],
            'clinic.business_type' => ['required', Rule::in(self::BUSINESS_TYPES)],
            'clinic.document_type' => ['required', Rule::in(self::DOCUMENT_TYPES)],
            'clinic.cnpj' => ['bail', 'required', 'string', 'size:'.$documentLength, Rule::unique('clinics', 'cnpj')],
            'clinic.email' => ['nullable', 'email', 'max:255'],
            'clinic.phone' => ['nullable', 'string', 'regex:/^\d{10,11}$/'],
            'clinic.whatsapp' => ['nullable', 'string', 'regex:/^\d{10,11}$/'],
            'clinic.timezone' => ['nullable', 'string', 'max:80'],
            'plan_id' => ['required', 'integer', Rule::exists('saas_plans', 'id')->where(fn ($query) => $query->where('active', true)->where('internal', false)->whereNull('deleted_at'))],
            'overrides' => ['nullable', 'array'],
            'overrides.*' => ['nullable'],
            'overrides.max_users' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'overrides.max_units' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'admin.name' => ['required', 'string', 'max:255'],
            'admin.email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'admin.phone' => ['nullable', 'string', 'regex:/^\d{10,11}$/'],
            'admin.password' => ['required', 'confirmed', Password::defaults()],
        ];

        foreach (['dashboard', 'clients', 'pets', 'agenda', 'petshop_services', 'veterinary', 'pdv', 'products', 'inventory', 'purchases', 'suppliers', 'financial', 'commissions'] as $feature) {
            $rules['overrides.'.$feature] = ['nullable', 'boolean'];
        }

        return $rules;
    }

    public function messages(): array
    {
        $documentLabel = $this->input('clinic.document_type') === 'cpf' ? 'CPF' : 'CNPJ';
        $documentLength = $documentLabel === 'CPF' ? 11 : 14;

        return [
            'clinic.corporate_name.required' => 'Informe o nome ou a razão social do estabelecimento.',
            'clinic.trade_name.required' => 'Informe o nome de exibição ou nome fantasia.',
            'clinic.business_type.required' => 'Selecione o tipo de negócio.',
            'clinic.business_type.in' => 'Selecione um tipo de negócio válido.',
            'clinic.document_type.required' => 'Selecione CPF ou CNPJ.',
            'clinic.document_type.in' => 'Selecione CPF ou CNPJ.',
            'clinic.cnpj.required' => "Informe o {$documentLabel} do cliente.",
            'clinic.cnpj.size' => "O {$documentLabel} deve conter {$documentLength} dígitos.",
            'clinic.cnpj.unique' => "Já existe um estabelecimento cadastrado com este {$documentLabel}.",
            'clinic.email.email' => 'Informe um e-mail comercial válido.',
            'clinic.phone.regex' => 'O telefone deve conter DDD e 10 ou 11 dígitos.',
            'clinic.whatsapp.regex' => 'O WhatsApp deve conter DDD e 10 ou 11 dígitos.',
            'plan_id.required' => 'Selecione o plano inicial.',
            'plan_id.exists' => 'O plano selecionado não está disponível.',
            'admin.name.required' => 'Informe o nome do administrador inicial.',
            'admin.email.required' => 'Informe o e-mail de acesso do administrador.',
            'admin.email.email' => 'Informe um e-mail de acesso válido.',
            'admin.email.unique' => 'Este e-mail já possui acesso ao VetFlow.',
            'admin.phone.regex' => 'O telefone do administrador deve conter DDD e 10 ou 11 dígitos.',
            'admin.password.required' => 'Defina a senha do administrador inicial.',
            'admin.password.confirmed' => 'A confirmação da senha não confere.',
        ];
    }

    private function digits(mixed $value): string
    {
        return preg_replace('/\D+/', '', (string) $value);
    }
}
