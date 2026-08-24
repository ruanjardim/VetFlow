<?php

namespace App\Modules\Operations\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOperationsSmokeCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['complete', 'reopen'])],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
