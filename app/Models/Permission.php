<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Permission extends Model
{
    use HasUlids;
    use SoftDeletes;

    protected $fillable = [
        'ulid',
        'name',
        'slug',
        'description',
        'group',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permission');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
