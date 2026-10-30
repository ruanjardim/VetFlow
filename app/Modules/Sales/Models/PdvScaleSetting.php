<?php

namespace App\Modules\Sales\Models;

use App\Models\Concerns\BelongsToClinicTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdvScaleSetting extends Model
{
    use BelongsToClinicTenant;

    protected $guarded = [];

    protected $casts = [
        'enabled' => 'boolean',
        'product_code_digits' => 'integer',
        'weight_decimals' => 'integer',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function valueDigits(): int
    {
        return 12 - strlen((string) $this->label_prefix) - (int) $this->product_code_digits;
    }
}
