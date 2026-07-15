<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Modules\Clinics\Models\Clinic;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory;
    use HasUlids;
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'clinic_id',
        'ulid',
        'name',
        'email',
        'phone',
        'photo',
        'position',
        'password',
        'active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'active' => 'boolean',
            'password' => 'hashed',
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

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'user_roles'
        )
            ->wherePivotNull('deleted_at')
            ->withTimestamps();
    }

    public function hasRole(string $role): bool
    {
        return $this->roles()
            ->where('roles.slug', $role)
            ->where('roles.active', true)
            ->exists();
    }

    public function hasPermission(string $permission): bool
    {
        return $this->hasAnyPermission([$permission]);
    }

    /**
     * @param array<int, string> $permissions
     */
    public function hasAnyPermission(array $permissions): bool
    {
        $permissions = array_values(array_filter($permissions));

        if ($permissions === []) {
            return true;
        }

        return $this->roles()
            ->where('roles.active', true)
            ->whereHas('permissions', function ($query) use ($permissions): void {
                $query->whereIn('permissions.slug', $permissions)
                    ->where('permissions.active', true);
            })
            ->exists();
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('active', false);
    }
}
