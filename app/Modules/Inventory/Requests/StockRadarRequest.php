<?php

namespace App\Modules\Inventory\Requests;

use App\Modules\Inventory\Services\StockRadarService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockRadarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', Rule::in(array_keys(StockRadarService::CATEGORIES))],
            'product_category' => ['nullable', 'string', 'max:120'],
            'brand' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
