<?php

namespace App\Modules\PetShopServices\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePetShopServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'clinic_id' => ['nullable', 'integer', 'exists:clinics,id'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'base_price' => ['nullable', 'numeric', 'min:0'],
            'small_price' => ['nullable', 'numeric', 'min:0'],
            'medium_price' => ['nullable', 'numeric', 'min:0'],
            'large_price' => ['nullable', 'numeric', 'min:0'],
            'giant_price' => ['nullable', 'numeric', 'min:0'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'requires_appointment' => ['nullable', 'boolean'],
            'active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome do servico.',
            'clinic_id.exists' => 'A clinica informada nao foi encontrada.',
            'base_price.numeric' => 'Informe um preco base valido.',
            'small_price.numeric' => 'Informe um preco valido para porte pequeno.',
            'medium_price.numeric' => 'Informe um preco valido para porte medio.',
            'large_price.numeric' => 'Informe um preco valido para porte grande.',
            'giant_price.numeric' => 'Informe um preco valido para porte gigante.',
            'duration_minutes.integer' => 'Informe a duracao em minutos.',
        ];
    }
}
