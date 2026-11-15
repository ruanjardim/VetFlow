<?php

namespace App\Modules\Saas\Requests;

use App\Modules\Saas\Models\LegalDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LegalDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(array_keys(LegalDocument::TYPES))],
            'title' => ['required', 'string', 'max:255'],
            'version' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('saas_legal_documents', 'version')->where(fn ($query) => $query->where('type', $this->input('type')))],
            'body' => ['required', 'string', 'min:200'],
            'effective_at' => ['nullable', 'date'],
        ];
    }
}
