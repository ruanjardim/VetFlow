<?php

namespace App\Modules\Fiscal\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeactivateFiscalProductionRequest extends FormRequest
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
            'reason' => ['required', 'string', 'min:15', 'max:1000'],
            'current_password' => ['required', 'current_password'],
            'confirm_deactivation' => ['required', 'accepted'],
        ];
    }
}
