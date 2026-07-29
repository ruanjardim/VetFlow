<?php

namespace App\Modules\Implementation\Models;

use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImplementationImport extends Model
{
    protected $table = 'implementation_imports';

    protected $guarded = [];

    protected $casts = [
        'total_rows' => 'integer',
        'imported_count' => 'integer',
        'invalid_rows' => 'integer',
        'completed_at' => 'datetime',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
