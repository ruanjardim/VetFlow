<?php

namespace App\Modules\Fiscal\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFiscalHomologationReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'clinic_id' => ['nullable', 'integer', 'exists:clinics,id'],
            'decision' => ['required', Rule::in(['approved_for_production_review', 'follow_up_required'])],
            'reviewer_name' => ['required', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000', 'required_if:decision,follow_up_required'],
            'current_password' => ['required', 'current_password'],
            'confirm_review' => ['required', 'accepted'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'notes.required_if' => 'Descreva o acompanhamento necessário.',
            'confirm_review.accepted' => 'Confirme que revisou as evidências atuais de homologação.',
        ];
    }
}
