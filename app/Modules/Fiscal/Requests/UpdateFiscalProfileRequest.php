<?php

namespace App\Modules\Fiscal\Requests;

use App\Modules\Fiscal\Support\FiscalProviderCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFiscalProfileRequest extends FormRequest
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
            'enabled' => ['required', 'boolean'],
            'environment' => ['required', Rule::in(['homologation'])],
            'nfce_enabled' => ['required', 'boolean'],
            'nfse_enabled' => ['required', 'boolean'],
            'provider' => ['nullable', Rule::in(array_keys(FiscalProviderCatalog::options()))],
            'provider_credential_reference' => ['nullable', 'string', 'max:160', 'regex:/^[A-Za-z0-9._:\/\-]+$/'],
            'nfse_dps_series' => ['nullable', 'integer', 'between:1,49999'],
            'nfse_dps_next_number' => ['nullable', 'integer', 'between:1,999999999999999'],
            'nfce_series' => ['nullable', 'integer', 'between:0,999'],
            'nfce_next_number' => ['nullable', 'integer', 'between:1,999999999'],
            'nfce_csc_id' => ['nullable', 'digits:6'],
            'nfce_csc_reference' => ['nullable', 'string', 'max:160', 'regex:/^[A-Za-z0-9._:\/\-]+$/'],
            'nfce_pix_payment_code' => ['nullable', Rule::in(['17', '20'])],
            'nfce_sefaz_credentialed' => ['nullable', 'boolean'],
            'simple_national_option' => ['nullable', Rule::in(['1', '2', '3'])],
            'simple_national_tax_regime' => ['nullable', Rule::in(['1', '2', '3'])],
            'special_tax_regime' => ['nullable', Rule::in(['0', '1', '2', '3', '4', '5', '6', '9'])],
            'state_registration' => ['nullable', 'string', 'max:30'],
            'municipal_registration' => ['nullable', 'string', 'max:30'],
            'tax_regime' => ['nullable', Rule::in(['simple_national', 'simple_excess_sublimit', 'normal'])],
            'municipality_code' => ['nullable', 'digits:7'],
            'cnae' => ['nullable', 'regex:/^\d{7}$/'],
            'certificate_reference' => ['nullable', 'string', 'max:160'],
            'certificate_fingerprint' => ['nullable', 'string', 'max:128'],
            'certificate_expires_at' => ['nullable', 'date'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->boolean('enabled') && ! $this->boolean('nfce_enabled') && ! $this->boolean('nfse_enabled')) {
                $validator->errors()->add('enabled', 'Habilite NFC-e ou NFS-e para ativar a preparação fiscal.');
            }

            if ($this->filled('nfse_dps_series') xor $this->filled('nfse_dps_next_number')) {
                $validator->errors()->add('nfse_dps_series', 'Informe juntos a série e o próximo número da DPS.');
            }

            if ($this->filled('nfce_series') xor $this->filled('nfce_next_number')) {
                $validator->errors()->add('nfce_series', 'Informe juntos a série e o próximo número da NFC-e.');
            }

            if ($this->input('simple_national_option') === '3' && ! $this->filled('simple_national_tax_regime')) {
                $validator->errors()->add('simple_national_tax_regime', 'Informe o regime de apuração do Simples Nacional para ME/EPP.');
            }
        });
    }
}
