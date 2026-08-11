<?php

namespace App\Modules\Tutors\Requests;

use App\Core\Support\DocumentNormalizer;
use App\Core\Validation\Rules\ValidCpf;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTutorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cpf' => DocumentNormalizer::onlyNumbers($this->cpf),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],

            'cpf' => [
                'nullable',
                new ValidCpf(),
                Rule::unique('tutors', 'cpf'),
            ],

            'rg' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'max:50'],

            'phone' => ['required', 'string', 'max:20'],
            'phone_secondary' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],

            'zip_code' => ['nullable', 'string', 'max:10'],
            'state' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'street' => ['nullable', 'string', 'max:255'],
            'number' => ['nullable', 'string', 'max:50'],
            'complement' => ['nullable', 'string', 'max:255'],

            'notes' => ['nullable', 'string'],
            'active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome do responsável.',
            'phone.required' => 'Informe o telefone principal do responsável.',
            'cpf.unique' => 'Já existe um responsável cadastrado com este CPF.',
            'email.email' => 'Informe um e-mail válido.',
        ];
    }
}
