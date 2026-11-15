<?php

namespace App\Modules\Sales\Models;

use App\Models\Concerns\BelongsToClinicTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An operator's cash session (caixa): opened with a change float, receives
 * the sale payments of that operator, holds supplies, withdrawals and
 * expenses, is closed by the operator with the counted values and reviewed
 * by a manager.
 */
class CashSession extends Model
{
    use BelongsToClinicTenant;

    public const STATUS_LABELS = [
        'open' => 'Aberto',
        'closed' => 'Fechado, aguardando conferência',
        'reviewed' => 'Conferido e encerrado',
    ];

    public const SHORT_STATUS_LABELS = [
        'open' => 'Aberto',
        'closed' => 'Fechado',
        'reviewed' => 'Encerrado',
    ];

    protected $table = 'cash_sessions';

    protected $guarded = [];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'opening_amount' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'counted_cash' => 'decimal:2',
        'expected_total' => 'decimal:2',
        'counted_total' => 'decimal:2',
        'difference_total' => 'decimal:2',
        'cash_left' => 'decimal:2',
        'closing_snapshot' => 'array',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CashSessionMovement::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function isReviewed(): bool
    {
        return $this->status === 'reviewed';
    }

    public function wasAutoClosed(): bool
    {
        return (bool) ($this->metadata['auto_closed'] ?? false);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function shortStatusLabel(): string
    {
        return self::SHORT_STATUS_LABELS[$this->status] ?? $this->status;
    }

    /**
     * Compact data for the PDV badge.
     *
     * @return array<string, mixed>
     */
    public function toPdvArray(): array
    {
        return [
            'id' => $this->id,
            'clinic_id' => $this->clinic_id,
            'code' => $this->code,
            'opened_at' => $this->opened_at?->format('d/m H:i'),
            'opening_amount' => (float) $this->opening_amount,
            'url' => route('sales.cash-sessions.show', $this->id),
        ];
    }
}
