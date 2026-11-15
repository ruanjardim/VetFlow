<?php

namespace App\Modules\Sales\Models;

use App\Models\Concerns\BelongsToClinicTenant;
use App\Models\User;
use App\Modules\Patients\Models\Patient;
use App\Modules\Sales\Support\SaleType;
use App\Modules\Tutors\Models\Tutor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A price quote prepared in the PDV. It never moves stock, money or
 * commissions; converting it opens the PDV prefilled with its items.
 */
class SaleQuote extends Model
{
    use BelongsToClinicTenant;
    use SoftDeletes;

    public const STATUS_LABELS = [
        'open' => 'Aberto',
        'expired' => 'Vencido',
        'converted' => 'Convertido em venda',
        'cancelled' => 'Cancelado',
    ];

    protected $table = 'sale_quotes';

    protected $guarded = [];

    protected $casts = [
        'valid_until' => 'date',
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'additions_total' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'total' => 'decimal:2',
        'converted_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function tutor(): BelongsTo
    {
        return $this->belongsTo(Tutor::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleQuoteItem::class)->orderBy('id');
    }

    public function convertedSale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'converted_sale_id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isExpired(): bool
    {
        return $this->isOpen()
            && $this->valid_until !== null
            && $this->valid_until->lt(today());
    }

    /** open, expired, converted or cancelled */
    public function displayStatus(): string
    {
        return $this->isExpired() ? 'expired' : (string) $this->status;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->displayStatus()] ?? ucfirst((string) $this->status);
    }

    public function saleTypeLabel(): string
    {
        return SaleType::label($this->sale_type);
    }

    public function hasDelivery(): bool
    {
        return SaleType::hasDelivery($this->sale_type);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'sale_quote_id');
    }

    public function scopeWithDisplayStatus(Builder $query, ?string $status): Builder
    {
        return match ($status) {
            'open' => $query->where('status', 'open')->whereDate('valid_until', '>=', today()),
            'expired' => $query->where('status', 'open')->whereDate('valid_until', '<', today()),
            'converted', 'cancelled' => $query->where('status', $status),
            default => $query,
        };
    }
}
