<?php

namespace App\Modules\Saas\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class ApplyCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['coupon_code' => Str::upper(trim((string) $this->input('coupon_code')))]);
    }

    public function rules(): array
    {
        return ['coupon_code' => ['required', 'string', 'max:64']];
    }
}
