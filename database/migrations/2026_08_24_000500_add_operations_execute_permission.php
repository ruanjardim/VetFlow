<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const PERMISSION_SLUG = 'operations.execute';

    public function up(): void
    {
        $permission = DB::table('permissions')->where('slug', self::PERMISSION_SLUG)->first();
        $attributes = [
            'name' => 'Executar controles operacionais',
            'description' => 'Permite registrar decisões, smoke tests e evidências operacionais da release.',
            'group' => 'Administrativo',
            'active' => true,
            'updated_at' => now(),
        ];

        if (! $permission) {
            $permissionId = DB::table('permissions')->insertGetId($attributes + [
                'ulid' => (string) Str::ulid(),
                'slug' => self::PERMISSION_SLUG,
                'created_at' => now(),
            ]);
        } else {
            $permissionId = $permission->id;
            DB::table('permissions')->where('id', $permissionId)->update($attributes + ['deleted_at' => null]);
        }

        DB::table('roles')
            ->whereNull('clinic_id')
            ->where('slug', 'administrador')
            ->whereNull('deleted_at')
            ->pluck('id')
            ->each(fn (int $roleId) => DB::table('role_permission')->updateOrInsert(
                ['role_id' => $roleId, 'permission_id' => $permissionId],
                ['created_at' => now(), 'updated_at' => now()]
            ));
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('slug', self::PERMISSION_SLUG)->value('id');

        if ($permissionId !== null) {
            DB::table('role_permission')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }
};
