<?php

namespace App\Modules\Fiscal\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApproveFiscalSaleAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'confirm_allocation' => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirm_allocation.accepted' => 'Confirme a revisão do rateio antes de aprová-lo.',
        ];
    }
}
