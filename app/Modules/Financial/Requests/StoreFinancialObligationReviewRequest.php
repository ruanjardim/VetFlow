<?php

namespace App\Modules\Financial\Requests;

use App\Core\Base\BaseRequest;
use App\Modules\Financial\Services\FinancialObligationService;
use Illuminate\Validation\Rule;

class StoreFinancialObligationReviewRequest extends BaseRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'type' => ['required', Rule::in(array_keys(FinancialObligationService::TYPE_LABELS))],
            'status' => ['required', Rule::in(array_keys(FinancialObligationService::STATUS_LABELS))],
            'aging' => ['required', Rule::in(array_keys(FinancialObligationService::AGING_LABELS))],
            'clinic_id' => [
                'nullable',
                'integer',
                Rule::exists('clinics', 'id')->where(fn ($query) => $query->where('active', true)),
            ],
            'decision' => ['required', Rule::in(array_keys(FinancialObligationService::REVIEW_LABELS))],
            'note' => ['nullable', 'string', 'max:1000', 'required_if:decision,follow_up'],
            'confirm_review' => ['accepted'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'decision.in' => 'Escolha uma decisão de revisão válida.',
            'note.required_if' => 'Descreva o acompanhamento necessário.',
            'note.max' => 'A observação pode ter no máximo 1.000 caracteres.',
            'confirm_review.accepted' => 'Confirme que revisou a composição financeira apresentada.',
            'clinic_id.exists' => 'O estabelecimento selecionado não está disponível.',
        ];
    }
}
