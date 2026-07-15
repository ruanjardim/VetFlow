<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Auth\PermissionCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthorizationSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $permissionIds = $this->seedPermissions();
            $roles = $this->seedRoles($permissionIds);

            $adminRole = $roles['administrador'] ?? null;

            if (! $adminRole) {
                return;
            }

            User::query()
                ->active()
                ->whereDoesntHave('roles')
                ->each(fn (User $user): bool => $this->attachRole($user, $adminRole));
        });
    }

    /**
     * @return array<string, int>
     */
    private function seedPermissions(): array
    {
        $permissionIds = [];

        foreach (PermissionCatalog::permissions() as $permissionData) {
            $permission = Permission::query()
                ->withTrashed()
                ->firstOrNew(['slug' => $permissionData['slug']]);

            $permission->fill($permissionData);
            $permission->active = true;
            $permission->save();

            if ($permission->trashed()) {
                $permission->restore();
            }

            $permissionIds[$permission->slug] = $permission->id;
        }

        return $permissionIds;
    }

    /**
     * @param array<string, int> $permissionIds
     * @return array<string, Role>
     */
    private function seedRoles(array $permissionIds): array
    {
        $roles = [];

        foreach (PermissionCatalog::roles() as $slug => $roleData) {
            $role = Role::query()
                ->withTrashed()
                ->whereNull('clinic_id')
                ->where('slug', $slug)
                ->firstOrNew([
                    'clinic_id' => null,
                    'slug' => $slug,
                ]);

            $role->fill([
                'clinic_id' => null,
                'name' => $roleData['name'],
                'slug' => $slug,
                'description' => $roleData['description'],
                'system' => true,
                'active' => true,
            ]);
            $role->save();

            if ($role->trashed()) {
                $role->restore();
            }

            $role->permissions()->sync(
                collect($roleData['permissions'])
                    ->map(fn (string $permission): ?int => $permissionIds[$permission] ?? null)
                    ->filter()
                    ->values()
                    ->all()
            );

            $roles[$slug] = $role;
        }

        return $roles;
    }

    private function attachRole(User $user, Role $role): bool
    {
        $existingRole = DB::table('user_roles')
            ->where('user_id', $user->id)
            ->where('role_id', $role->id)
            ->first();

        if ($existingRole) {
            return DB::table('user_roles')
                ->where('id', $existingRole->id)
                ->update([
                    'deleted_at' => null,
                    'updated_at' => now(),
                ]) > 0;
        }

        return DB::table('user_roles')->insert([
            'ulid' => (string) Str::ulid(),
            'user_id' => $user->id,
            'role_id' => $role->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
