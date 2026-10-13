<?php

namespace App\Modules\Fiscal\Requests;

use App\Modules\Fiscal\Services\FiscalReconciliationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class FiscalReconciliationRequest extends FormRequest
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
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'status' => ['nullable', Rule::in(array_keys(FiscalReconciliationService::STATUS_LABELS))],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['from', 'to'])) {
                    return;
                }

                $from = CarbonImmutable::parse($this->input('from', now()->toDateString()));
                $to = CarbonImmutable::parse($this->input('to', $from->toDateString()));
                if ($from->diffInDays($to) > 30) {
                    $validator->errors()->add('to', 'Consulte no máximo 31 dias por vez.');
                }
            },
        ];
    }
}
