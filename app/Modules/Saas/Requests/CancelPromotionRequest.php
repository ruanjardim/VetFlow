<?php

namespace App\Modules\Saas\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelPromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:5', 'max:255']];
    }
}
