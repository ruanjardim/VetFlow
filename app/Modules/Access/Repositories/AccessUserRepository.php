<?php

namespace App\Modules\Access\Repositories;

use App\Models\Role;
use App\Models\User;
use App\Modules\Access\Contracts\AccessUserRepositoryInterface;
use App\Modules\Clinics\Models\Clinic;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccessUserRepository implements AccessUserRepositoryInterface
{
    public function paginateFor(User $actor, int $perPage = 15): LengthAwarePaginator
    {
        return $this->scopedUsers($actor)
            ->with(['clinic', 'roles'])
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function findFor(User $actor, int $id): User
    {
        return $this->scopedUsers($actor)
            ->with(['clinic', 'roles.permissions'])
            ->findOrFail($id);
    }

    public function assignableRoles(): Collection
    {
        return Role::query()
            ->whereNull('clinic_id')
            ->where('system', true)
            ->active()
            ->with(['permissions' => fn ($query) => $query->active()->orderBy('group')->orderBy('name')])
            ->orderBy('id')
            ->get();
    }

    public function availableClinics(User $actor): Collection
    {
        if ($actor->clinic_id !== null) {
            return Clinic::query()
                ->whereKey($actor->clinic_id)
                ->get();
        }

        return Clinic::query()
            ->orderBy('trade_name')
            ->orderBy('corporate_name')
            ->get();
    }

    public function create(array $data): User
    {
        return User::query()->create($data);
    }

    public function update(User $user, array $data): User
    {
        $user->fill($data);
        $user->save();

        return $user;
    }

    public function syncRoles(User $user, array $roleIds, User $actor): void
    {
        $roleIds = collect($roleIds)
            ->unique()
            ->values();

        $existingLinks = DB::table('user_roles')
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('role_id');

        foreach ($existingLinks as $roleId => $link) {
            if ($roleIds->contains((int) $roleId) || $link->deleted_at !== null) {
                continue;
            }

            DB::table('user_roles')
                ->where('id', $link->id)
                ->update([
                    'updated_by' => $actor->id,
                    'updated_at' => now(),
                    'deleted_at' => now(),
                ]);
        }

        foreach ($roleIds as $roleId) {
            $existingLink = $existingLinks->get($roleId);

            if ($existingLink) {
                DB::table('user_roles')
                    ->where('id', $existingLink->id)
                    ->update([
                        'updated_by' => $actor->id,
                        'updated_at' => now(),
                        'deleted_at' => null,
                    ]);

                continue;
            }

            DB::table('user_roles')->insert([
                'ulid' => (string) Str::ulid(),
                'user_id' => $user->id,
                'role_id' => $roleId,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function scopedUsers(User $actor): Builder
    {
        return User::query()
            ->when(
                $actor->clinic_id !== null,
                fn ($query) => $query->where('clinic_id', $actor->clinic_id)
            );
    }
}
