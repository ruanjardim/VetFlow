<?php

namespace App\Modules\Clinics\Requests;

use App\Core\Base\BaseRequest;
use Illuminate\Validation\Rule;

class StoreClinicRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'corporate_name' => ['required', 'string', 'max:255'],
            'trade_name' => ['required', 'string', 'max:255'],
            'cnpj' => ['required', 'string', 'max:20', Rule::unique('clinics', 'cnpj')],
            'crmv' => ['nullable', 'string', 'max:40'],
            'technical_manager' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
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
            'active' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'corporate_name.required' => 'Informe a razao social da clinica.',
            'trade_name.required' => 'Informe o nome fantasia da clinica.',
            'cnpj.required' => 'Informe o CNPJ da clinica.',
            'cnpj.unique' => 'Ja existe uma clinica cadastrada com este CNPJ.',
            'email.email' => 'Informe um e-mail valido para a clinica.',
            'website.url' => 'Informe um site valido para a clinica.',
        ];
    }
}
