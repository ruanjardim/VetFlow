<?php

namespace App\Modules\Saas\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class OnboardTenantRequest extends FormRequest
{
    public const BUSINESS_TYPES = ['veterinary_clinic', 'pet_shop', 'feed_store', 'grooming', 'mixed', 'other'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'clinic.corporate_name' => ['required', 'string', 'max:255'],
            'clinic.trade_name' => ['required', 'string', 'max:255'],
            'clinic.business_type' => ['required', Rule::in(self::BUSINESS_TYPES)],
            'clinic.cnpj' => ['required', 'string', 'max:18', Rule::unique('clinics', 'cnpj')],
            'clinic.email' => ['nullable', 'email', 'max:255'],
            'clinic.phone' => ['nullable', 'string', 'max:30'],
            'clinic.whatsapp' => ['nullable', 'string', 'max:30'],
            'clinic.timezone' => ['nullable', 'string', 'max:80'],
            'plan_id' => ['required', 'integer', Rule::exists('saas_plans', 'id')->where(fn ($query) => $query->where('active', true)->where('internal', false)->whereNull('deleted_at'))],
            'overrides' => ['nullable', 'array'],
            'overrides.*' => ['nullable'],
            'overrides.max_users' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'overrides.max_units' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'admin.name' => ['required', 'string', 'max:255'],
            'admin.email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'admin.phone' => ['nullable', 'string', 'max:30'],
            'admin.password' => ['required', 'confirmed', Password::defaults()],
        ];

        foreach (['dashboard', 'clients', 'pets', 'agenda', 'petshop_services', 'veterinary', 'pdv', 'products', 'inventory', 'purchases', 'suppliers', 'financial', 'commissions'] as $feature) {
            $rules['overrides.'.$feature] = ['nullable', 'boolean'];
        }

        return $rules;
    }
}
