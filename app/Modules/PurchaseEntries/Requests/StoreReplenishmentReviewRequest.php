<?php

namespace App\Modules\PurchaseEntries\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReplenishmentReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['reviewed', 'held'])],
            'note' => ['nullable', 'string', 'max:500', 'required_if:decision,held'],
        ];
    }

    public function messages(): array
    {
        return [
            'note.required_if' => 'Informe o motivo para manter a sugestão em espera.',
        ];
    }
}
