<?php

namespace App\Modules\Operations\Models;

use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationsSmokeCheck extends Model
{
    use HasUlids;

    protected $fillable = [
        'clinic_id',
        'actor_user_id',
        'environment',
        'release_sha',
        'check_key',
        'completed',
        'note',
    ];

    protected $casts = [
        'completed' => 'boolean',
    ];

    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
