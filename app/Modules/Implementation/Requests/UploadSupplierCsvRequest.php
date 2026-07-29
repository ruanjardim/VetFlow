<?php

namespace App\Modules\Implementation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadSupplierCsvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'suppliers_file' => [
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
            'suppliers_file.required' => 'Selecione o arquivo CSV de fornecedores.',
            'suppliers_file.file' => 'O arquivo de fornecedores enviado não é válido.',
            'suppliers_file.extensions' => 'Envie um arquivo com a extensão .csv.',
            'suppliers_file.max' => 'O arquivo CSV deve ter no máximo 2 MB.',
        ];
    }
}
