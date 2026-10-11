<?php

namespace App\Modules\Fiscal\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductFiscalProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'ncm' => $this->digits($this->input('ncm')),
            'cest' => $this->digits($this->input('cest')),
            'default_cfop' => $this->digits($this->input('default_cfop')),
            'icms_code' => $this->digits($this->input('icms_code')),
            'pis_code' => $this->digits($this->input('pis_code')),
            'cofins_code' => $this->digits($this->input('cofins_code')),
            'ibs_cbs_tax_status' => $this->digits($this->input('ibs_cbs_tax_status')),
            'fiscal_unit' => strtoupper(trim((string) $this->input('fiscal_unit'))),
            'tax_benefit_code' => strtoupper(trim((string) $this->input('tax_benefit_code'))),
            'ibs_cbs_classification_code' => strtoupper(trim((string) $this->input('ibs_cbs_classification_code'))),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'ncm' => ['nullable', 'digits:8'],
            'cest' => ['nullable', 'digits:7'],
            'origin_code' => ['nullable', Rule::in(['0', '1', '2', '3', '4', '5', '6', '7', '8'])],
            'default_cfop' => ['nullable', 'digits:4'],
            'icms_code' => ['nullable', 'regex:/^\d{2,3}$/'],
            'pis_code' => ['nullable', 'digits:2'],
            'cofins_code' => ['nullable', 'digits:2'],
            'fiscal_unit' => ['nullable', 'regex:/^[A-Z0-9]{1,6}$/'],
            'tax_benefit_code' => ['nullable', 'string', 'max:20', 'regex:/^[A-Z0-9.-]+$/'],
            'ibs_cbs_tax_status' => ['nullable', 'digits:3'],
            'ibs_cbs_classification_code' => ['nullable', 'digits:6'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function digits(mixed $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        return $digits === '' ? null : $digits;
    }
}
