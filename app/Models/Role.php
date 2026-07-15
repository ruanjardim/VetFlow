<?php

namespace App\Models;

use App\Modules\Clinics\Models\Clinic;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends Model
{
    use HasUlids;
    use SoftDeletes;

    protected $fillable = [
        'ulid',
        'clinic_id',
        'name',
        'slug',
        'description',
        'system',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'system' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles')
            ->wherePivotNull('deleted_at')
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
