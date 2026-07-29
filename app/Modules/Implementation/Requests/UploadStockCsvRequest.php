<?php

namespace App\Modules\Implementation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadStockCsvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'stock_file' => [
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
            'stock_file.required' => 'Selecione o arquivo CSV de estoque.',
            'stock_file.file' => 'O arquivo de estoque enviado não é válido.',
            'stock_file.extensions' => 'Envie um arquivo com a extensão .csv.',
            'stock_file.max' => 'O arquivo CSV deve ter no máximo 2 MB.',
        ];
    }
}
