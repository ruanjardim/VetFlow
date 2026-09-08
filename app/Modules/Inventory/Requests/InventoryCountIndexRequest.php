<?php

namespace App\Modules\Inventory\Requests;

use App\Modules\Inventory\Models\InventoryCount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventoryCountIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(array_keys(InventoryCount::STATUS_LABELS))],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
