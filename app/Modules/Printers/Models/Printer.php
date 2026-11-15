<?php

namespace App\Modules\Printers\Models;

use App\Models\Concerns\BelongsToClinicTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Printer extends Model
{
    use BelongsToClinicTenant;
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'network_port' => 'integer',
        'is_default' => 'boolean',
        'active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Printer $printer): void {
            if (empty($printer->ulid)) {
                $printer->ulid = (string) Str::ulid();
            }
        });
    }
}
