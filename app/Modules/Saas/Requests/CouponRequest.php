<?php

namespace App\Modules\Saas\Requests;

use App\Modules\Saas\Models\Coupon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => Str::upper(trim((string) $this->input('code'))),
            'active' => $this->boolean('active'),
            'max_redemptions' => $this->filled('max_redemptions') ? $this->input('max_redemptions') : null,
        ]);
    }

    public function rules(): array
    {
        $couponId = $this->route('coupon')?->id;

        return [
            'code' => ['required', 'string', 'max:64', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/', Rule::unique('saas_coupons', 'code')->ignore($couponId)],
            'percentage' => ['required', 'integer', Rule::in(Coupon::PERCENTAGES)],
            'active' => ['required', 'boolean'],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after:valid_from'],
            'duration_months' => ['required', 'integer', 'min:1', 'max:24'],
            'max_redemptions' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'plan_ids' => ['nullable', 'array'],
            'plan_ids.*' => ['integer', Rule::exists('saas_plans', 'id')->where(fn ($query) => $query->where('internal', false)->whereNull('deleted_at'))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
