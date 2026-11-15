<?php

namespace App\Modules\Saas\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PublishLegalDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'legal_review_confirmed' => ['required', 'accepted'],
            'reviewer_name' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }
}
