<?php

namespace App\Modules\PurchaseEntries\Requests;

use App\Modules\PurchaseEntries\Services\ReplenishmentPilotReviewService;
use App\Modules\PurchaseEntries\Services\ReplenishmentPurchaseHistoryService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReplenishmentPilotReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period' => ['required', Rule::in(array_keys(ReplenishmentPurchaseHistoryService::PERIODS))],
            'decision' => ['required', Rule::in(array_keys(ReplenishmentPilotReviewService::DECISIONS))],
            'note' => ['nullable', 'string', 'max:500', 'required_if:decision,held'],
        ];
    }

    public function messages(): array
    {
        return [
            'note.required_if' => 'Informe o acompanhamento necessário antes de manter a revisão em espera.',
        ];
    }
}
