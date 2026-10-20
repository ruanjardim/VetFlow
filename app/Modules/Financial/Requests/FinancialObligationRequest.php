<?php

namespace App\Modules\Financial\Requests;

use App\Core\Base\BaseRequest;
use App\Modules\Financial\Services\FinancialObligationService;
use Illuminate\Validation\Rule;

class FinancialObligationRequest extends BaseRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'type' => ['nullable', Rule::in(['all', 'income', 'expense'])],
            'status' => ['nullable', Rule::in(['all', 'open', 'overdue', 'today', 'upcoming', 'paid', 'cancelled'])],
            'aging' => ['nullable', Rule::in(array_keys(FinancialObligationService::AGING_LABELS))],
            'clinic_id' => [
                'nullable',
                'integer',
                Rule::exists('clinics', 'id')->where(fn ($query) => $query->where('active', true)),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'from.date_format' => 'Informe uma data inicial válida.',
            'to.date_format' => 'Informe uma data final válida.',
            'to.after_or_equal' => 'A data final deve ser igual ou posterior à data inicial.',
            'type.in' => 'Escolha um tipo de conta válido.',
            'status.in' => 'Escolha uma situação válida.',
            'aging.in' => 'Escolha uma faixa de atraso válida.',
            'clinic_id.exists' => 'O estabelecimento selecionado não está disponível.',
        ];
    }
}
