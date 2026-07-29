<?php

namespace App\Modules\Implementation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadPatientCsvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patients_file' => [
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
            'patients_file.required' => 'Selecione o arquivo CSV de pacientes.',
            'patients_file.file' => 'O arquivo de pacientes enviado não é válido.',
            'patients_file.extensions' => 'Envie um arquivo com a extensão .csv.',
            'patients_file.max' => 'O arquivo CSV deve ter no máximo 2 MB.',
        ];
    }
}
