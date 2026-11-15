<?php

namespace App\Modules\Fiscal\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DisconnectFocusCredentialRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['environment' => $this->input('environment', 'homologation')]);
    }

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'clinic_id' => ['nullable', 'integer', 'exists:clinics,id'],
            'environment' => ['required', Rule::in(['homologation', 'production'])],
            'current_password' => ['required', 'current_password'],
            'confirm_disconnect' => ['accepted'],
        ];
    }
}
