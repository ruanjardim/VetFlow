<?php

namespace App\Modules\Fiscal\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreFiscalPeriodClosureRequest extends FormRequest
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
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:today'],
            'reviewer_name' => ['required', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'current_password' => ['required', 'current_password'],
            'confirm_close' => ['required', 'accepted'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['from', 'to'])) {
                    return;
                }

                $from = CarbonImmutable::parse($this->input('from'));
                $to = CarbonImmutable::parse($this->input('to'));
                if ($from->diffInDays($to) > 30) {
                    $validator->errors()->add('to', 'Feche no máximo 31 dias por vez.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'to.before_or_equal' => 'O período de fechamento não pode terminar no futuro.',
            'confirm_close.accepted' => 'Confirme que revisou a conciliação completa do período.',
        ];
    }
}
