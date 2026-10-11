<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const PERMISSION_SLUG = 'fiscal.manage';

    private const STANDARD_ROLES = ['administrador', 'caixa', 'financeiro'];

    public function up(): void
    {
        $permission = DB::table('permissions')->where('slug', self::PERMISSION_SLUG)->first();
        $attributes = [
            'name' => 'Gerenciar módulo fiscal',
            'description' => 'Permite configurar a preparação e acompanhar documentos fiscais do estabelecimento.',
            'group' => 'Fiscal',
            'active' => true,
            'updated_at' => now(),
        ];

        if ($permission) {
            $permissionId = $permission->id;
            DB::table('permissions')->where('id', $permissionId)->update($attributes + ['deleted_at' => null]);
        } else {
            $permissionId = DB::table('permissions')->insertGetId($attributes + [
                'ulid' => (string) Str::ulid(),
                'slug' => self::PERMISSION_SLUG,
                'created_at' => now(),
            ]);
        }

        DB::table('roles')
            ->whereNull('clinic_id')
            ->whereIn('slug', self::STANDARD_ROLES)
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
