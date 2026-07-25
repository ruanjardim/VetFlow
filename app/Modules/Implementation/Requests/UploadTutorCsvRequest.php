<?php

namespace App\Modules\Implementation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadTutorCsvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tutors_file' => [
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
            'tutors_file.required' => 'Selecione o arquivo CSV de tutores.',
            'tutors_file.file' => 'O arquivo de tutores enviado não é válido.',
            'tutors_file.extensions' => 'Envie um arquivo com a extensão .csv.',
            'tutors_file.max' => 'O arquivo CSV deve ter no máximo 2 MB.',
        ];
    }
}
