<?php

namespace App\Modules\Sales\Models;

use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashRegisterClosure extends Model
{
    protected $table = 'cash_register_closures';

    protected $guarded = [];

    protected $casts = [
        'period_from' => 'datetime',
        'period_to' => 'datetime',
        'closed_at' => 'datetime',
        'expected_cash' => 'decimal:2',
        'counted_cash' => 'decimal:2',
        'cash_difference' => 'decimal:2',
        'expected_total' => 'decimal:2',
        'counted_total' => 'decimal:2',
        'total_difference' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }
}
