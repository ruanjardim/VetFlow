<?php

namespace App\Modules\Fiscal\Models;

use App\Models\Concerns\BelongsToClinicTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class FiscalDpsSequence extends Model
{
    use BelongsToClinicTenant;
    use HasUlids;

    protected $guarded = [];

    protected $casts = [
        'next_number' => 'integer',
        'last_reserved_number' => 'integer',
    ];

    public function uniqueIds(): array
    {
        return ['ulid'];
    }
}
