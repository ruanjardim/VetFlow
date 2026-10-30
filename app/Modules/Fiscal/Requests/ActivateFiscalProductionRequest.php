<?php

namespace App\Modules\Fiscal\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ActivateFiscalProductionRequest extends FormRequest
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
            'reviewer_name' => ['required', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'nfse_dps_series' => ['nullable', 'integer', 'between:1,49999'],
            'nfse_dps_next_number' => ['nullable', 'integer', 'between:1,999999999999999'],
            'nfce_series' => ['nullable', 'integer', 'between:0,999'],
            'nfce_next_number' => ['nullable', 'integer', 'between:1,999999999'],
            'nfce_csc_id' => ['nullable', 'digits:6'],
            'nfce_csc_reference' => ['nullable', 'string', 'max:160', 'regex:/^[A-Za-z0-9._:\/\-]+$/'],
            'nfce_sefaz_credentialed' => ['nullable', 'accepted'],
            'current_password' => ['required', 'current_password'],
            'confirm_production' => ['required', 'accepted'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'confirm_production.accepted' => 'Confirme que esta ativação emitirá documentos fiscais com validade jurídica e tributária.',
            'nfce_sefaz_credentialed.accepted' => 'Confirme o credenciamento de produção na SEFAZ-RJ.',
        ];
    }
}
