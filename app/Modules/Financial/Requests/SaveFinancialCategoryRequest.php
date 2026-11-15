<?php

namespace App\Modules\Financial\Requests;

use App\Modules\Financial\Models\FinancialCategory;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveFinancialCategoryRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $categoryId = (int) $this->route('financialCategory');

        if ($categoryId <= 0) {
            return;
        }

        $clinicId = $this->clinicId();
        $type = FinancialCategory::query()
            ->withoutGlobalScope('clinic_tenant')
            ->when($clinicId, fn ($query) => $query->where('clinic_id', $clinicId))
            ->whereKey($categoryId)
            ->value('type');

        if ($type) {
            $this->merge(['type' => $type]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clinicId = $this->clinicId();
        $categoryId = (int) $this->route('financialCategory');

        return [
            'clinic_id' => [
                Rule::requiredIf(fn (): bool => app(TenantContext::class)->isGlobal()),
                'nullable',
                'integer',
                Rule::exists('clinics', 'id')->where(fn ($query) => $query->where('active', true)),
            ],
            'type' => ['required', 'string', Rule::in(array_keys(FinancialCategory::TYPE_LABELS))],
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('financial_categories', 'name')
                    ->where(fn ($query) => $query
                        ->where('clinic_id', $clinicId)
                        ->where('type', $this->input('type')))
                    ->ignore($categoryId > 0 ? $categoryId : null),
            ],
            'active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    public function messages(): array
    {
        return [
            'clinic_id.required' => 'Selecione o estabelecimento.',
            'type.required' => 'Selecione o tipo da categoria.',
            'type.in' => 'Selecione um tipo de categoria válido.',
            'name.required' => 'Informe o nome da categoria.',
            'name.unique' => 'Já existe uma categoria deste tipo com este nome.',
        ];
    }

    public function clinicId(): ?int
    {
        $clinicId = app(TenantContext::class)->clinicId();

        if ($clinicId !== null) {
            return $clinicId;
        }

        return is_numeric($this->input('clinic_id')) ? (int) $this->input('clinic_id') : null;
    }
}
