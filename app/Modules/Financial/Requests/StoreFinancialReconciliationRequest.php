<?php

namespace App\Modules\Financial\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFinancialReconciliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'clinic_id' => ['nullable', 'integer'],
            'account_id' => ['required', 'integer'],
            'entry_id' => ['required', 'integer'],
            'transaction_id' => ['required', 'integer'],
            'note' => ['nullable', 'string', 'max:500'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', 'in:all,pending,reconciled'],
        ];
    }
}
