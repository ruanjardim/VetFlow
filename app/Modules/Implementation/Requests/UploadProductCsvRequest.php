<?php

namespace App\Modules\Implementation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadProductCsvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'products_file' => [
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
            'products_file.required' => 'Selecione o arquivo CSV de produtos.',
            'products_file.file' => 'O arquivo de produtos enviado não é válido.',
            'products_file.extensions' => 'Envie um arquivo com a extensão .csv.',
            'products_file.max' => 'O arquivo CSV deve ter no máximo 2 MB.',
        ];
    }
}
