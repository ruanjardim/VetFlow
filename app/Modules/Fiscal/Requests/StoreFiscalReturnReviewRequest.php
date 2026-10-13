<?php

namespace App\Modules\Fiscal\Requests;

use App\Modules\Fiscal\Services\FiscalReturnReviewService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFiscalReturnReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(array_keys(FiscalReturnReviewService::DECISION_LABELS))],
            'reviewer_name' => ['required', 'string', 'max:120'],
            'guidance_source' => ['required', 'string', 'min:3', 'max:160'],
            'resolution_summary' => ['required', 'string', 'min:20', 'max:2000'],
            'current_password' => ['required', 'current_password'],
            'confirm_review' => ['required', 'accepted'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'guidance_source.required' => 'Informe quem forneceu a orientação ou sua referência interna.',
            'resolution_summary.min' => 'Descreva a orientação e o próximo passo com pelo menos 20 caracteres.',
            'confirm_review.accepted' => 'Confirme que revisou a devolução e os documentos vinculados.',
        ];
    }
}
