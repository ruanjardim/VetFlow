<?php

namespace App\Modules\Saas\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AcceptLegalPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'acceptance_confirmed' => ['required', 'accepted'],
            'authority_confirmed' => ['required', 'accepted'],
        ];
    }
}
