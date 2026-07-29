<?php

namespace App\Modules\Implementation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadFinancialCsvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'financial_file' => [
                'required',
                'file',
                'extensions:csv',
                'max:2048',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'financial_file.required' => 'Selecione o arquivo CSV financeiro.',
            'financial_file.file' => 'O arquivo financeiro enviado não é válido.',
            'financial_file.extensions' => 'Envie um arquivo com a extensão .csv.',
            'financial_file.max' => 'O arquivo CSV deve ter no máximo 2 MB.',
        ];
    }
}
