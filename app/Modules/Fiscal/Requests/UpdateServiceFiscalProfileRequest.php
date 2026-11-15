<?php

namespace App\Modules\Fiscal\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServiceFiscalProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'national_taxation_code' => $this->code($this->input('national_taxation_code')),
            'municipal_taxation_code' => $this->code($this->input('municipal_taxation_code')),
            'service_list_item' => $this->code($this->input('service_list_item')),
            'cnae' => $this->digits($this->input('cnae')),
            'nbs_code' => $this->code($this->input('nbs_code')),
            'municipality_code' => $this->digits($this->input('municipality_code')),
            'iss_rate' => $this->decimal($this->input('iss_rate')),
            'iss_withheld' => $this->boolean('iss_withheld'),
            'iss_taxation' => $this->code($this->input('iss_taxation')),
            'ibs_cbs_tax_status' => $this->digits($this->input('ibs_cbs_tax_status')),
            'ibs_cbs_classification_code' => $this->code($this->input('ibs_cbs_classification_code')),
            'operation_indicator' => $this->digits($this->input('operation_indicator')),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'national_taxation_code' => ['nullable', 'digits:6'],
            'municipal_taxation_code' => ['nullable', 'string', 'max:30', 'regex:/^[A-Z0-9.\/_-]+$/'],
            'service_list_item' => ['nullable', 'string', 'max:10', 'regex:/^\d{1,2}\.\d{2}$/'],
            'cnae' => ['nullable', 'digits:7'],
            'nbs_code' => ['nullable', 'string', 'max:15', 'regex:/^[A-Z0-9.-]+$/'],
            'municipality_code' => ['nullable', 'digits:7'],
            'iss_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'iss_withheld' => ['required', 'boolean'],
            'iss_taxation' => ['nullable', 'string', 'in:1,2,3,4'],
            'ibs_cbs_tax_status' => ['nullable', 'digits:3'],
            'ibs_cbs_classification_code' => ['nullable', 'digits:6'],
            'operation_indicator' => ['nullable', 'digits:6'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function digits(mixed $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        return $digits === '' ? null : $digits;
    }

    private function code(mixed $value): ?string
    {
        $code = strtoupper(trim((string) $value));

        return $code === '' ? null : $code;
    }

    private function decimal(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $normalized = trim($value);

        if ($normalized === '') {
            return null;
        }

        if (str_contains($normalized, ',')) {
            return str_replace(',', '.', str_replace('.', '', $normalized));
        }

        return $normalized;
    }
}
