<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Printers\Models\Printer;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PrinterManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_clinic_user_can_configure_and_test_a_printer(): void
    {
        $clinic = $this->clinic('Pet Shop Impressão', '00000000000801');
        $user = $this->userForClinic($clinic, true);

        $response = $this->actingAs($user)->post(route('printers.store'), $this->payload([
            'name' => 'Térmica do caixa',
            'is_default' => '1',
        ]));

        $response->assertRedirect(route('printers.index'))->assertSessionDoesntHaveErrors();

        $printer = Printer::query()->firstOrFail();
        $this->assertSame($clinic->id, $printer->clinic_id);
        $this->assertTrue($printer->is_default);

        $this->get(route('printers.index'))
            ->assertOk()
            ->assertSee('Configuração de impressoras')
            ->assertSee('Térmica do caixa')
            ->assertSee('Padrão');

        $this->get(route('printers.test', $printer->id))
            ->assertOk()
            ->assertSee('Teste de impressão')
            ->assertSee('data-print-page', false)
            ->assertSee('printer-paper-80mm', false);

        $this->assertDatabaseHas('audit_events', [
            'clinic_id' => $clinic->id,
            'event' => 'printer.created',
            'subject_id' => (string) $printer->id,
        ]);
    }

    public function test_only_one_active_default_printer_is_kept(): void
    {
        $clinic = $this->clinic('Clínica Padrão', '00000000000802');
        $user = $this->userForClinic($clinic, true);

        $this->actingAs($user)->post(route('printers.store'), $this->payload([
            'name' => 'Caixa 1',
            'is_default' => '1',
        ]))->assertSessionDoesntHaveErrors();

        $this->post(route('printers.store'), $this->payload([
            'name' => 'Caixa 2',
            'connection_type' => 'network',
            'network_host' => '192.168.1.50',
            'network_port' => 9100,
            'is_default' => '1',
        ]))->assertSessionDoesntHaveErrors();

        $this->assertFalse(Printer::query()->where('name', 'Caixa 1')->firstOrFail()->is_default);
        $this->assertTrue(Printer::query()->where('name', 'Caixa 2')->firstOrFail()->is_default);

        $second = Printer::query()->where('name', 'Caixa 2')->firstOrFail();
        $this->put(route('printers.update', $second->id), $this->payload([
            'name' => 'Caixa 2',
            'active' => '0',
            'is_default' => '1',
        ]))->assertSessionDoesntHaveErrors();

        $second->refresh();
        $this->assertFalse($second->active);
        $this->assertFalse($second->is_default);
    }

    public function test_printer_access_is_permission_protected_and_tenant_scoped(): void
    {
        $clinicA = $this->clinic('Clínica A', '00000000000803');
        $clinicB = $this->clinic('Clínica B', '00000000000804');
        $authorized = $this->userForClinic($clinicA, true);
        $unauthorized = $this->userForClinic($clinicA, false);

        $foreignPrinterId = DB::table('printers')->insertGetId([
            'ulid' => (string) Str::ulid(),
            'clinic_id' => $clinicB->id,
            'name' => 'Impressora da clínica B',
            'type' => 'non_fiscal',
            'purpose' => 'receipt',
            'connection_type' => 'browser',
            'paper_size' => '80mm',
            'is_default' => false,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($unauthorized)->get(route('printers.index'))->assertForbidden();
        $this->actingAs($authorized)->get(route('printers.edit', $foreignPrinterId))->assertNotFound();

        $global = User::factory()->create(['clinic_id' => null, 'active' => true]);
        $this->attachPermission($global, 'printers.manage');
        $this->actingAs($global)->get(route('printers.index'))->assertForbidden();
    }

    public function test_all_standard_role_presets_receive_printer_access(): void
    {
        $this->seed(AuthorizationSeeder::class);

        foreach (['administrador', 'veterinario', 'atendimento', 'estoque-compras', 'caixa', 'financeiro'] as $slug) {
            $this->assertTrue(
                Role::query()->where('slug', $slug)->firstOrFail()
                    ->permissions()->where('permissions.slug', 'printers.manage')->exists(),
                "O perfil {$slug} deve receber printers.manage."
            );
        }
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Impressora principal',
            'type' => 'non_fiscal',
            'purpose' => 'receipt',
            'connection_type' => 'browser',
            'paper_size' => '80mm',
            'queue_name' => 'EPSON Caixa',
            'network_host' => null,
            'network_port' => null,
            'manufacturer' => 'Epson',
            'model' => 'TM-T20X',
            'notes' => null,
            'is_default' => '0',
            'active' => '1',
        ];
    }

    private function clinic(string $name, string $cnpj): Clinic
    {
        return Clinic::query()->create([
            'corporate_name' => $name,
            'trade_name' => $name,
            'cnpj' => $cnpj,
            'active' => true,
        ]);
    }

    private function userForClinic(Clinic $clinic, bool $authorized): User
    {
        $user = User::factory()->create(['clinic_id' => $clinic->id, 'active' => true]);

        if ($authorized) {
            $this->attachPermission($user, 'printers.manage');
        }

        return $user;
    }

    private function attachPermission(User $user, string $slug): void
    {
        $permission = Permission::query()->firstOrCreate(
            ['slug' => $slug],
            [
                'name' => 'Gerenciar impressoras',
                'description' => 'Permissão do teste de impressoras.',
                'group' => 'Administrativo',
                'active' => true,
            ]
        );
        $role = Role::query()->create([
            'name' => 'Impressoras '.Str::random(6),
            'slug' => 'impressoras-'.Str::lower(Str::random(8)),
            'description' => 'Perfil de teste',
            'system' => false,
            'active' => true,
        ]);
        $role->permissions()->attach($permission->id);

        DB::table('user_roles')->insert([
            'ulid' => (string) Str::ulid(),
            'user_id' => $user->id,
            'role_id' => $role->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
