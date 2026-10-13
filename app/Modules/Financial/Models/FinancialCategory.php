<?php

namespace App\Modules\Financial\Models;

use App\Models\Concerns\BelongsToClinicTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialCategory extends Model
{
    use BelongsToClinicTenant;

    public const TYPE_LABELS = [
        'income' => 'Entrada',
        'expense' => 'Saída',
    ];

    public const CODE_SALES = 'sales';

    public const CODE_OTHER_INCOME = 'other_income';

    public const CODE_INVENTORY_PURCHASES = 'inventory_purchases';

    public const CODE_COMMISSIONS = 'commissions';

    public const CODE_CARD_FEES = 'card_fees';

    public const CODE_SALE_REFUNDS = 'sale_refunds';

    public const CODE_CASH_EXPENSES = 'cash_expenses';

    public const CODE_OTHER_EXPENSES = 'other_expenses';

    protected $guarded = [];

    protected $casts = [
        'active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('type')->orderBy('sort_order')->orderBy('name');
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    public function isSystem(): bool
    {
        return filled($this->system_code);
    }
}
