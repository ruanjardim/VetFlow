<?php

namespace App\Modules\ServiceOrders\Requests;

use App\Http\Requests\Concerns\ValidatesTenantScopedReferences;
use App\Modules\Patients\Models\Patient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'clinic_id' => [
                Rule::requiredIf($this->user()?->clinic_id === null),
                'nullable',
                'integer',
                Rule::exists('clinics', 'id')->where('active', true),
            ],
            'tutor_id' => ['nullable', 'integer', $this->existsInCurrentClinic('tutors')],
            'patient_id' => ['nullable', 'integer', $this->existsInCurrentClinic('patients')],
            'assigned_user_id' => [
                'nullable',
                'integer',
                $this->existsInCurrentClinic('users')->where('active', true),
            ],
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
            'clinic_id.required' => 'Selecione a clinica da comanda.',
            'clinic_id.exists' => 'A clinica informada nao foi encontrada ou esta inativa.',
            'status.required' => 'Informe o status da comanda.',
            'status.in' => 'Informe um status valido para a comanda.',
            'tutor_id.exists' => 'O responsável informado nao foi encontrado.',
            'patient_id.exists' => 'O paciente informado nao foi encontrado.',
            'assigned_user_id.exists' => 'O profissional informado nao foi encontrado ou esta inativo.',
            'items.*.product_id.exists' => 'Um dos produtos informados nao foi encontrado.',
            'items.*.petshop_service_id.exists' => 'Um dos servicos informados nao foi encontrado.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $patientId = $this->integer('patient_id');
            $tutorId = $this->integer('tutor_id');

            if ($patientId <= 0 || $tutorId <= 0 || $validator->errors()->has('patient_id')) {
                return;
            }

            $patientBelongsToTutor = Patient::query()
                ->whereKey($patientId)
                ->where(function ($query) use ($tutorId): void {
                    $query
                        ->whereNull('tutor_id')
                        ->orWhere('tutor_id', $tutorId);
                })
                ->exists();

            if (! $patientBelongsToTutor) {
                $validator->errors()->add('patient_id', 'O pet selecionado nao pertence ao responsável informado.');
            }
        });
    }
}
