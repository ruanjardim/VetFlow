<?php

namespace App\Modules\Saas\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['active' => $this->boolean('active')]);
    }

    public function rules(): array
    {
        $planId = $this->route('plan')?->id;

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'alpha_dash', 'max:120', Rule::unique('saas_plans', 'slug')->ignore($planId)],
            'description' => ['nullable', 'string', 'max:2000'],
            'active' => ['required', 'boolean'],
            'monthly_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'annual_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'max_users' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'max_units' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'features' => ['nullable', 'array'],
            'features.*' => ['nullable', 'boolean'],
        ];
    }
}
