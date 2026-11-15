<?php

namespace App\Modules\Operations\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportOperationsBackupEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'evidence' => ['required', 'file', 'extensions:json', 'max:512'],
        ];
    }

    public function messages(): array
    {
        return [
            'evidence.required' => 'Selecione a evidência JSON gerada após a restauração isolada.',
            'evidence.file' => 'A evidência enviada não é um arquivo válido.',
            'evidence.extensions' => 'A evidência deve usar a extensão .json.',
            'evidence.max' => 'A evidência deve ter no máximo 512 KB.',
        ];
    }
}
