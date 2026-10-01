<?php

namespace App\Modules\ServiceOrders\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGroomingScheduleBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clinicId = $this->user()?->clinic_id ?? $this->integer('clinic_id');

        return [
            'clinic_id' => [
                Rule::requiredIf($this->user()?->clinic_id === null),
                'nullable',
                'integer',
                Rule::exists('clinics', 'id')->where('active', true),
            ],
            'user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('clinic_id', $clinicId)
                    ->where('active', true)),
            ],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
