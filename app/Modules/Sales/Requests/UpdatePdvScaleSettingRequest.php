<?php

namespace App\Modules\Sales\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePdvScaleSettingRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'clinic_id' => $this->user()?->clinic_id ?? $this->input('clinic_id'),
            'label_prefix' => preg_replace('/\D+/', '', (string) $this->input('label_prefix')),
        ]);
    }

    public function rules(): array
    {
        return [
            'clinic_id' => [
                'required',
                'integer',
                Rule::exists('clinics', 'id')->where('active', true)->whereNull('deleted_at'),
            ],
            'enabled' => ['required', 'boolean'],
            'label_prefix' => ['required', 'digits_between:1,4'],
            'product_code_digits' => ['required', 'integer', 'between:1,8'],
            'weight_decimals' => ['required', 'integer', 'between:0,3'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $valueDigits = 12
                    - strlen((string) $this->input('label_prefix'))
                    - (int) $this->input('product_code_digits');

                if ($valueDigits < 3 || $valueDigits > 8) {
                    $validator->errors()->add(
                        'product_code_digits',
                        'Prefixo e código do produto devem deixar de 3 a 8 dígitos para o peso da etiqueta.'
                    );
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'clinic_id.required' => 'Selecione o estabelecimento.',
            'clinic_id.exists' => 'O estabelecimento selecionado não está ativo.',
            'label_prefix.required' => 'Informe o prefixo usado pela balança.',
            'label_prefix.digits_between' => 'O prefixo deve ter entre 1 e 4 números.',
            'product_code_digits.between' => 'O código do produto deve ocupar entre 1 e 8 dígitos.',
            'weight_decimals.between' => 'O peso pode usar de 0 a 3 casas decimais.',
        ];
    }
}
