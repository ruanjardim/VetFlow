<?php

namespace App\Modules\Fiscal\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DisconnectFocusCredentialRequest extends FormRequest
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
            'current_password' => ['required', 'current_password'],
            'confirm_disconnect' => ['accepted'],
        ];
    }
}
