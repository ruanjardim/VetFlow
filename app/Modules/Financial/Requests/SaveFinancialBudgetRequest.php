<?php

namespace App\Modules\Financial\Requests;

use App\Modules\Financial\Models\FinancialCategory;
use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\Validator;

class SaveFinancialBudgetRequest extends FinancialBudgetRequest
{
    protected function prepareForValidation(): void
    {
        $budgets = $this->input('budgets', []);

        if (is_array($budgets)) {
            foreach ($budgets as $categoryId => $amount) {
                $budgets[$categoryId] = $this->normalizeDecimal($amount);
            }
        }

        $this->merge(['budgets' => $budgets]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_replace(parent::rules(), [
            'competence' => ['required', 'date_format:Y-m'],
            'basis' => ['required', 'string', 'in:cash,competence'],
            'clinic_id' => [
                app(TenantContext::class)->isGlobal() ? 'required' : 'nullable',
                'integer',
                'exists:clinics,id',
            ],
            'budgets' => ['required', 'array', 'min:1'],
            'budgets.*' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('budgets')) {
                return;
            }

            $budgets = $this->input('budgets', []);
            if (! is_array($budgets)) {
                return;
            }

            $categoryIds = array_keys($budgets);
            $normalizedIds = array_values(array_unique(array_filter(
                array_map(fn ($id): ?int => ctype_digit((string) $id) && (int) $id > 0 ? (int) $id : null, $categoryIds)
            )));

            if (count($normalizedIds) !== count($categoryIds)) {
                $validator->errors()->add('budgets', 'As categorias informadas são inválidas.');

                return;
            }

            $tenant = app(TenantContext::class);
            $clinicId = $tenant->isGlobal() ? (int) $this->input('clinic_id') : (int) $tenant->clinicId();
            if ($clinicId <= 0) {
                return;
            }

            $validCount = FinancialCategory::query()
                ->withoutGlobalScope('clinic_tenant')
                ->where('clinic_id', $clinicId)
                ->whereIn('id', $normalizedIds)
                ->count();

            if ($validCount !== count($normalizedIds)) {
                $validator->errors()->add('budgets', 'Uma ou mais categorias não pertencem ao estabelecimento selecionado.');
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return array_replace(parent::messages(), [
            'budgets.required' => 'Informe ao menos uma categoria do orçamento.',
            'clinic_id.required' => 'Selecione o estabelecimento do orçamento.',
            'budgets.*.numeric' => 'Informe os valores do orçamento em reais.',
            'budgets.*.min' => 'Os valores previstos não podem ser negativos.',
            'budgets.*.max' => 'Um valor previsto ultrapassa o limite permitido.',
        ]);
    }

    private function normalizeDecimal(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $normalized = trim($value);
        if ($normalized === '') {
            return '0';
        }

        if (str_contains($normalized, ',')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        }

        return $normalized;
    }
}
