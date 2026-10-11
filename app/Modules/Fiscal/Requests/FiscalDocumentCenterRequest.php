<?php

namespace App\Modules\Fiscal\Requests;

use App\Modules\Fiscal\Services\FiscalDocumentCenterService;
use App\Modules\Fiscal\Support\FiscalDocumentType;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class FiscalDocumentCenterRequest extends FormRequest
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
            'competence' => ['nullable', 'date_format:Y-m'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'type' => ['nullable', Rule::in(FiscalDocumentType::values())],
            'status' => ['nullable', Rule::in(array_keys(FiscalDocumentCenterService::STATUS_LABELS))],
            'sale' => ['nullable', 'string', 'max:80'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['competence', 'from', 'to'])) {
                    return;
                }

                $competence = CarbonImmutable::createFromFormat(
                    'Y-m-d',
                    ($this->input('competence') ?: now()->format('Y-m')).'-01'
                );
                $from = CarbonImmutable::parse($this->input('from') ?: $competence->startOfMonth()->toDateString());
                $to = CarbonImmutable::parse($this->input('to') ?: $competence->endOfMonth()->toDateString());

                if ($from->format('Y-m') !== $competence->format('Y-m')) {
                    $validator->errors()->add('from', 'A data inicial deve pertencer à competência selecionada.');
                }
                if ($to->format('Y-m') !== $competence->format('Y-m')) {
                    $validator->errors()->add('to', 'A data final deve pertencer à competência selecionada.');
                }
                if ($from->diffInDays($to) > 30) {
                    $validator->errors()->add('to', 'Consulte no máximo 31 dias por vez.');
                }
            },
        ];
    }
}
