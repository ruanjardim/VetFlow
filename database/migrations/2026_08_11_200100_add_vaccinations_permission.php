<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const PERMISSION_SLUG = 'vaccinations.manage';

    public function up(): void
    {
        $permission = DB::table('permissions')->where('slug', self::PERMISSION_SLUG)->first();

        if (! $permission) {
            $permissionId = DB::table('permissions')->insertGetId([
                'ulid' => (string) Str::ulid(),
                'name' => 'Gerenciar vacinação',
                'slug' => self::PERMISSION_SLUG,
                'description' => 'Permite registrar e acompanhar a carteira de vacinação.',
                'group' => 'Atendimento',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $permissionId = $permission->id;

            DB::table('permissions')->where('id', $permissionId)->update([
                'name' => 'Gerenciar vacinação',
                'description' => 'Permite registrar e acompanhar a carteira de vacinação.',
                'group' => 'Atendimento',
                'active' => true,
                'deleted_at' => null,
                'updated_at' => now(),
            ]);
        }

        DB::table('roles')
            ->whereNull('clinic_id')
            ->whereIn('slug', ['administrador', 'veterinario'])
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
