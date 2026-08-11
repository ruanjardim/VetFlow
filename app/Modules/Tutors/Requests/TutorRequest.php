<?php

namespace App\Modules\Tutors\Requests;

use App\Core\Support\DocumentNormalizer;
use App\Core\Validation\Rules\ValidCpf;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TutorRequest extends FormRequest
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
        $tutorId = $this->route('tutore')?->id
            ?? $this->route('tutor')?->id
            ?? $this->route('id');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'cpf' => [
                'nullable',
                new ValidCpf(),
                Rule::unique('tutors', 'cpf')->ignore($tutorId),
            ],

            'rg' => [
                'nullable',
                'string',
                'max:30',
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:20',
            ],

            'zip_code' => [
                'nullable',
                'string',
                'max:9',
            ],

            'address' => [
                'nullable',
                'string',
                'max:255',
            ],

            'number' => [
                'nullable',
                'string',
                'max:20',
            ],

            'complement' => [
                'nullable',
                'string',
                'max:255',
            ],

            'district' => [
                'nullable',
                'string',
                'max:255',
            ],

            'city' => [
                'nullable',
                'string',
                'max:255',
            ],

            'state' => [
                'nullable',
                'string',
                'size:2',
            ],

            'notes' => [
                'nullable',
                'string',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome do responsável.',

            'cpf.unique' => 'Já existe um responsável cadastrado com este CPF.',

            'email.email' => 'Informe um e-mail válido.',

            'state.size' => 'O estado deve possuir 2 caracteres.',
        ];
    }
}
