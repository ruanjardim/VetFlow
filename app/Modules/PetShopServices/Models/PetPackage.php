<?php

namespace App\Modules\PetShopServices\Models;

use App\Models\Concerns\BelongsToClinicTenant;
use App\Modules\Patients\Models\Patient;
use App\Modules\Sales\Models\Sale;
use App\Modules\Tutors\Models\Tutor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Pacote vendido para um pet, com saldo por servico. */
class PetPackage extends Model
{
    use BelongsToClinicTenant;
    use SoftDeletes;

    public const STATUS_LABELS = [
        'pending_payment' => 'Aguardando pagamento',
        'active' => 'Ativo',
        'consumed' => 'Consumido',
        'expired' => 'Vencido',
        'cancelled' => 'Cancelado',
    ];

    protected $table = 'pet_packages';

    protected $guarded = [];

    protected $casts = [
        'price' => 'decimal:2',
        'starts_on' => 'date',
        'expires_on' => 'date',
        'activated_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(PetshopPackage::class, 'petshop_package_id')->withTrashed();
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function tutor(): BelongsTo
    {
        return $this->belongsTo(Tutor::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function balances(): HasMany
    {
        return $this->hasMany(PetPackageBalance::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(PetPackageUsage::class);
    }

    /** Status exibido, considerando validade e saldo. */
    public function effectiveStatus(): string
    {
        if ($this->status !== 'active') {
            return $this->status;
        }

        if ($this->expires_on !== null && $this->expires_on->lt(today())) {
            return 'expired';
        }

        if ($this->remainingTotal() <= 0) {
            return 'consumed';
        }

        return 'active';
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->effectiveStatus()] ?? $this->status;
    }

    public function totalQuantity(): int
    {
        return (int) $this->balances->sum('quantity');
    }

    public function usedTotal(): int
    {
        return (int) $this->usages->sum('quantity');
    }

    public function remainingTotal(): int
    {
        return max(0, $this->totalQuantity() - $this->usedTotal());
    }
}
