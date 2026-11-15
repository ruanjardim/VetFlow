<?php

namespace App\Modules\Employees\Requests;

use App\Modules\Employees\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $compensation = $this->input('base_compensation');
        if (is_string($compensation) && str_contains($compensation, ',')) {
            $compensation = str_replace(',', '.', str_replace('.', '', trim($compensation)));
        }

        $this->merge([
            'clinic_id' => $this->user()?->clinic_id ?? $this->input('clinic_id'),
            'base_compensation' => $compensation,
            'active' => $this->boolean('active'),
        ]);
    }

    public function rules(): array
    {
        $clinicId = (int) $this->input('clinic_id');
        $employeeId = (int) $this->route('employee');

        return [
            'clinic_id' => ['required', 'integer', Rule::exists('clinics', 'id')->where('active', true)->whereNull('deleted_at')],
            'user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('clinic_id', $clinicId)->whereNull('deleted_at'),
                Rule::unique('employees', 'user_id')->ignore($employeeId)->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'position' => ['nullable', 'string', 'max:120'],
            'document' => ['nullable', 'string', 'max:40'],
            'employment_type' => ['required', Rule::in(array_keys(Employee::EMPLOYMENT_TYPES))],
            'hire_date' => ['nullable', 'date'],
            'termination_date' => ['nullable', 'date', 'after_or_equal:hire_date'],
            'base_compensation' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'payment_method' => ['nullable', Rule::in(array_keys(Employee::PAYMENT_METHODS))],
            'pix_key' => ['nullable', 'required_if:payment_method,pix', 'string', 'max:255'],
            'bank_name' => ['nullable', 'required_if:payment_method,bank_transfer', 'string', 'max:120'],
            'bank_branch' => ['nullable', 'required_if:payment_method,bank_transfer', 'string', 'max:40'],
            'bank_account' => ['nullable', 'required_if:payment_method,bank_transfer', 'string', 'max:60'],
            'account_holder' => ['nullable', 'string', 'max:255'],
            'active' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'clinic_id.required' => 'Selecione o estabelecimento.',
            'user_id.exists' => 'O acesso vinculado deve pertencer ao mesmo estabelecimento.',
            'user_id.unique' => 'Este acesso já está vinculado a outro colaborador.',
            'name.required' => 'Informe o nome do colaborador.',
            'termination_date.after_or_equal' => 'O desligamento não pode ser anterior à admissão.',
            'pix_key.required_if' => 'Informe a chave Pix para esta forma de pagamento.',
            'bank_name.required_if' => 'Informe o banco para a transferência.',
            'bank_branch.required_if' => 'Informe a agência para a transferência.',
            'bank_account.required_if' => 'Informe a conta para a transferência.',
        ];
    }
}
