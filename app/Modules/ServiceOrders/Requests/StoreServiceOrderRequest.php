<?php

namespace App\Modules\ServiceOrders\Requests;

use App\Http\Requests\Concerns\ValidatesTenantScopedReferences;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceOrderRequest extends FormRequest
{
    use ValidatesTenantScopedReferences;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'clinic_id' => ['nullable', 'integer', 'exists:clinics,id'],
            'tutor_id' => ['nullable', 'integer', $this->existsInCurrentClinic('tutors')],
            'patient_id' => ['nullable', 'integer', $this->existsInCurrentClinic('patients')],
            'status' => ['required', 'string', Rule::in(['open', 'in_service', 'waiting_pickup', 'finished', 'cancelled'])],
            'opened_at' => ['nullable', 'date'],
            'scheduled_at' => ['nullable', 'date'],
            'closed_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'discount_total' => ['nullable', 'numeric', 'min:0'],

            'items' => ['nullable', 'array'],
            'items.*.type' => ['nullable', 'string', Rule::in(['service', 'product', 'custom'])],
            'items.*.product_id' => ['nullable', 'integer', $this->existsInCurrentClinic('products')],
            'items.*.petshop_service_id' => ['nullable', 'integer', $this->existsInCurrentClinic('petshop_services')],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0.001'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Informe o status da comanda.',
            'status.in' => 'Informe um status valido para a comanda.',
            'tutor_id.exists' => 'O tutor informado nao foi encontrado.',
            'patient_id.exists' => 'O paciente informado nao foi encontrado.',
            'items.*.product_id.exists' => 'Um dos produtos informados nao foi encontrado.',
            'items.*.petshop_service_id.exists' => 'Um dos servicos informados nao foi encontrado.',
        ];
    }
}
