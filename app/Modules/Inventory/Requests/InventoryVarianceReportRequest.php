<?php

namespace App\Modules\Inventory\Requests;

use App\Modules\Inventory\Services\InventoryVarianceReportService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventoryVarianceReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period' => ['nullable', Rule::in(array_keys(InventoryVarianceReportService::PERIODS))],
            'direction' => ['nullable', Rule::in(array_keys(InventoryVarianceReportService::DIRECTIONS))],
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
