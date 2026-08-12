<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const PERMISSION_SLUG = 'commissions.manage';

    public function up(): void
    {
        $permission = DB::table('permissions')->where('slug', self::PERMISSION_SLUG)->first();

        if (! $permission) {
            $permissionId = DB::table('permissions')->insertGetId([
                'ulid' => (string) Str::ulid(),
                'name' => 'Gerenciar comissoes',
                'slug' => self::PERMISSION_SLUG,
                'description' => 'Permite configurar regras e consultar previas de comissoes.',
                'group' => 'Financeiro',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $permissionId = $permission->id;

            DB::table('permissions')->where('id', $permissionId)->update([
                'name' => 'Gerenciar comissoes',
                'description' => 'Permite configurar regras e consultar previas de comissoes.',
                'group' => 'Financeiro',
                'active' => true,
                'deleted_at' => null,
                'updated_at' => now(),
            ]);
        }

        DB::table('roles')
            ->whereNull('clinic_id')
            ->whereIn('slug', ['administrador', 'financeiro'])
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
