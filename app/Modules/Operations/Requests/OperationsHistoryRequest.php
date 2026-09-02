<?php

namespace App\Modules\Operations\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OperationsHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'type' => ['nullable', Rule::in(['runtime', 'backup', 'smoke', 'decision'])],
            'release' => ['nullable', Rule::in(['current', 'all'])],
        ];
    }
}
