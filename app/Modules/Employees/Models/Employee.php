<?php

namespace App\Modules\Employees\Models;

use App\Models\Concerns\BelongsToClinicTenant;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Commissions\Models\SalesCommissionSettlement;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use BelongsToClinicTenant;
    use HasUlids;
    use SoftDeletes;

    public const EMPLOYMENT_TYPES = [
        'employee' => 'Funcionário',
        'contractor' => 'Prestador de serviço',
        'partner' => 'Sócio',
        'intern' => 'Estagiário',
        'other' => 'Outro vínculo',
    ];

    public const PAYMENT_METHODS = [
        'pix' => 'Pix',
        'bank_transfer' => 'Transferência bancária',
        'cash' => 'Dinheiro',
        'other' => 'Outro',
    ];

    protected $guarded = [];

    protected $casts = [
        'document' => 'encrypted',
        'payment_details' => 'encrypted:array',
        'hire_date' => 'date',
        'termination_date' => 'date',
        'base_compensation' => 'decimal:2',
        'active' => 'boolean',
    ];

    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function salesCommissionSettlements(): HasMany
    {
        return $this->hasMany(SalesCommissionSettlement::class);
    }

    public function paymentSnapshot(): ?array
    {
        if (! $this->payment_method) {
            return null;
        }

        $details = $this->payment_details ?? [];

        return array_filter([
            'method' => $this->payment_method,
            'method_label' => self::PAYMENT_METHODS[$this->payment_method] ?? $this->payment_method,
            'pix_key_masked' => $this->mask($details['pix_key'] ?? null),
            'bank_name' => $details['bank_name'] ?? null,
            'bank_branch_masked' => $this->mask($details['bank_branch'] ?? null),
            'bank_account_masked' => $this->mask($details['bank_account'] ?? null),
        ], fn (mixed $value): bool => filled($value));
    }

    private function mask(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $visible = mb_substr($value, -4);

        return str_repeat('•', max(4, min(8, mb_strlen($value) - 4))).$visible;
    }
}
