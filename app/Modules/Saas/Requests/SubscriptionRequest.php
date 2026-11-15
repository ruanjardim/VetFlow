<?php

namespace App\Modules\Saas\Requests;

use App\Modules\Saas\Models\Subscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'plan_id' => ['required', 'integer', Rule::exists('saas_plans', 'id')->where(fn ($query) => $query->where('active', true)->whereNull('deleted_at'))],
            'status' => ['required', Rule::in(Subscription::STATUSES)],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'trial_ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'renews_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'overrides' => ['nullable', 'array'],
            'overrides.*' => ['nullable'],
            'overrides.max_users' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'overrides.max_units' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];

        foreach (['dashboard', 'clients', 'pets', 'agenda', 'petshop_services', 'veterinary', 'pdv', 'products', 'inventory', 'purchases', 'suppliers', 'financial', 'commissions'] as $feature) {
            $rules['overrides.'.$feature] = ['nullable', 'boolean'];
        }

        return $rules;
    }
}
