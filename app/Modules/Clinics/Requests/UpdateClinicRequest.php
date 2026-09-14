<?php

namespace App\Modules\Clinics\Requests;

use App\Core\Base\BaseRequest;
use App\Modules\Clinics\Services\ClinicBrandingService;
use Illuminate\Validation\Rule;

class UpdateClinicRequest extends BaseRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'document_type' => in_array($this->input('document_type'), ['cpf', 'cnpj'], true)
                ? $this->input('document_type')
                : 'cnpj',
            'cnpj' => $this->digits($this->input('cnpj')),
            'phone' => $this->digits($this->input('phone')),
            'whatsapp' => $this->digits($this->input('whatsapp')),
        ]);
    }

    public function rules(): array
    {
        $documentLength = $this->input('document_type') === 'cpf' ? 11 : 14;

        return [
            'corporate_name' => ['required', 'string', 'max:255'],
            'trade_name' => ['required', 'string', 'max:255'],
            'document_type' => ['required', Rule::in(['cpf', 'cnpj'])],
            'cnpj' => ['bail', 'required', 'string', 'size:'.$documentLength, Rule::unique('clinics', 'cnpj')->ignore($this->route('clinic'))],
            'crmv' => ['nullable', 'string', 'max:40'],
            'technical_manager' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'regex:/^\d{10,11}$/'],
            'whatsapp' => ['nullable', 'string', 'regex:/^\d{10,11}$/'],
            'website' => ['nullable', 'url', 'max:255'],
            'zip_code' => ['nullable', 'string', 'max:20'],
            'state' => ['nullable', 'string', 'max:2'],
            'city' => ['nullable', 'string', 'max:120'],
            'district' => ['nullable', 'string', 'max:120'],
            'street' => ['nullable', 'string', 'max:255'],
            'number' => ['nullable', 'string', 'max:40'],
            'complement' => ['nullable', 'string', 'max:120'],
            'timezone' => ['nullable', 'string', 'max:80'],
            'currency' => ['nullable', 'string', 'size:3'],
            'language' => ['nullable', 'string', 'max:12'],
            'brand_icon_mode' => ['sometimes', Rule::in(array_keys(ClinicBrandingService::modes()))],
            'brand_icon_key' => ['sometimes', Rule::in(array_keys(ClinicBrandingService::icons()))],
            'active' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        $documentLabel = $this->input('document_type') === 'cpf' ? 'CPF' : 'CNPJ';
        $documentLength = $documentLabel === 'CPF' ? 11 : 14;

        return [
            'corporate_name.required' => 'Informe a razao social da clinica.',
            'trade_name.required' => 'Informe o nome fantasia da clinica.',
            'document_type.required' => 'Selecione CPF ou CNPJ.',
            'document_type.in' => 'Selecione CPF ou CNPJ.',
            'cnpj.required' => "Informe o {$documentLabel} da clinica.",
            'cnpj.size' => "O {$documentLabel} deve conter {$documentLength} dígitos.",
            'cnpj.unique' => "Ja existe uma clinica cadastrada com este {$documentLabel}.",
            'email.email' => 'Informe um e-mail valido para a clinica.',
            'phone.regex' => 'O telefone deve conter DDD e 10 ou 11 dígitos.',
            'whatsapp.regex' => 'O WhatsApp deve conter DDD e 10 ou 11 dígitos.',
            'website.url' => 'Informe um site valido para a clinica.',
        ];
    }

    private function digits(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return preg_replace('/\D+/', '', (string) $value);
    }
}
