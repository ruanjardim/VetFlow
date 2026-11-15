<?php

namespace App\Modules\Financial\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFinancialReconciliationGroupRequest extends FormRequest
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
            'group_kind' => ['required', 'in:one_to_many,many_to_one,net_settlement'],
            'entry_ids' => ['required', 'array', 'min:1', 'max:20'],
            'entry_ids.*' => ['required', 'integer', 'distinct'],
            'transaction_ids' => ['required', 'array', 'min:1', 'max:20'],
            'transaction_ids.*' => ['required', 'integer', 'distinct'],
            'note' => ['required', 'string', 'min:10', 'max:500'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', 'in:all,pending,reconciled'],
        ];
    }
}
