<?php

namespace App\Modules\Financial\Services;

use App\Modules\Financial\Models\FinancialCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FinancialCategoryService
{
    public const DEFAULTS = [
        ['system_code' => FinancialCategory::CODE_SALES, 'type' => 'income', 'name' => 'Vendas e serviços', 'sort_order' => 10],
        ['system_code' => FinancialCategory::CODE_OTHER_INCOME, 'type' => 'income', 'name' => 'Outras entradas', 'sort_order' => 90],
        ['system_code' => FinancialCategory::CODE_INVENTORY_PURCHASES, 'type' => 'expense', 'name' => 'Compras e estoque', 'sort_order' => 10],
        ['system_code' => FinancialCategory::CODE_COMMISSIONS, 'type' => 'expense', 'name' => 'Comissões', 'sort_order' => 20],
        ['system_code' => FinancialCategory::CODE_CARD_FEES, 'type' => 'expense', 'name' => 'Taxas de cartão', 'sort_order' => 30],
        ['system_code' => FinancialCategory::CODE_SALE_REFUNDS, 'type' => 'expense', 'name' => 'Estornos de vendas', 'sort_order' => 40],
        ['system_code' => FinancialCategory::CODE_CASH_EXPENSES, 'type' => 'expense', 'name' => 'Despesas de caixa', 'sort_order' => 50],
        ['system_code' => FinancialCategory::CODE_OTHER_EXPENSES, 'type' => 'expense', 'name' => 'Outras despesas', 'sort_order' => 90],
    ];

    /** @return Collection<int, FinancialCategory> */
    public function forClinic(?int $clinicId): Collection
    {
        if (! $clinicId) {
            return collect();
        }

        $this->ensureDefaults($clinicId);

        return FinancialCategory::query()
            ->withoutGlobalScope('clinic_tenant')
            ->where('clinic_id', $clinicId)
            ->ordered()
            ->get();
    }

    /** @return Collection<int, FinancialCategory> */
    public function activeForClinic(?int $clinicId): Collection
    {
        return $this->forClinic($clinicId)->where('active', true)->values();
    }

    public function ensureDefaults(int $clinicId): void
    {
        $now = now();

        foreach (self::DEFAULTS as $definition) {
            DB::table('financial_categories')->insertOrIgnore($definition + [
                'clinic_id' => $clinicId,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function idForCode(?int $clinicId, string $code): ?int
    {
        if (! $clinicId) {
            return null;
        }

        $this->ensureDefaults($clinicId);

        return FinancialCategory::query()
            ->withoutGlobalScope('clinic_tenant')
            ->where('clinic_id', $clinicId)
            ->where('system_code', $code)
            ->value('id');
    }

    /** @param array<string, mixed> $data */
    public function create(int $clinicId, array $data): FinancialCategory
    {
        $this->ensureDefaults($clinicId);

        $category = new FinancialCategory([
            'type' => $data['type'],
            'name' => $data['name'],
            'active' => $data['active'],
            'sort_order' => $data['sort_order'] ?? 0,
        ]);
        $category->clinic_id = $clinicId;
        $category->save();

        return $category;
    }

    /** @param array<string, mixed> $data */
    public function update(FinancialCategory $category, array $data): FinancialCategory
    {
        $category->update([
            'name' => $data['name'],
            'active' => $data['active'],
            'sort_order' => $data['sort_order'] ?? $category->sort_order,
        ]);

        return $category->refresh();
    }

    public function findForClinic(int $clinicId, int $id): FinancialCategory
    {
        return FinancialCategory::query()
            ->withoutGlobalScope('clinic_tenant')
            ->where('clinic_id', $clinicId)
            ->findOrFail($id);
    }
}
