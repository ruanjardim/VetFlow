<?php

namespace App\Modules\Fiscal\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConnectFocusCredentialRequest extends FormRequest
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
            'focus_token' => ['required', 'string', 'min:16', 'max:512', 'confirmed'],
            'focus_token_confirmation' => ['required', 'string', 'max:512'],
            'current_password' => ['required', 'current_password'],
            'accept_focus_responsibility' => ['accepted'],
        ];
    }
}
