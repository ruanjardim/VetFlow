<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Employees\Models\Employee;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_registers_employee_without_login_and_sensitive_fields_are_encrypted(): void
    {
        $this->seed(AuthorizationSeeder::class);
        $clinic = $this->clinic('Clínica Funcional', '00000000001141');
        $actor = $this->administrator($clinic);

        $this->actingAs($actor)
            ->post(route('employees.store'), [
                'name' => 'Marina sem Login',
                'email' => 'marina@vetflow.test',
                'phone' => '11999990000',
                'position' => 'Vendedora',
                'document' => '123.456.789-00',
                'employment_type' => 'employee',
                'hire_date' => '2026-11-01',
                'base_compensation' => '3.250,75',
                'payment_method' => 'pix',
                'pix_key' => 'marina-pix-secreto-7788',
                'active' => '1',
                'notes' => 'Cadastro sem licença de acesso.',
            ])
            ->assertRedirect(route('employees.index'))
            ->assertSessionHasNoErrors();

        $employee = Employee::query()->sole();
        $this->assertNull($employee->user_id);
        $this->assertSame('3250.75', (string) $employee->base_compensation);
        $this->assertSame('123.456.789-00', $employee->document);
        $this->assertSame('marina-pix-secreto-7788', $employee->payment_details['pix_key']);

        $raw = DB::table('employees')->where('id', $employee->id)->first();
        $this->assertNotSame('123.456.789-00', $raw->document);
        $this->assertStringNotContainsString('marina-pix-secreto-7788', (string) $raw->payment_details);

        $this->get(route('employees.edit', $employee->id))
            ->assertOk()
            ->assertSee('123.456.789-00')
            ->assertSee('marina-pix-secreto-7788');

        $this->assertTrue(AuditEvent::query()
            ->where('event', 'employee.created')
            ->where('subject_id', (string) $employee->id)
            ->exists());
    }

    public function test_employee_records_are_isolated_by_clinic(): void
    {
        $this->seed(AuthorizationSeeder::class);
        $clinicA = $this->clinic('Clínica A', '00000000001142');
        $clinicB = $this->clinic('Clínica B', '00000000001143');
        $actorA = $this->administrator($clinicA);
        $actorB = $this->administrator($clinicB);
        $employeeB = Employee::query()->withoutGlobalScope('clinic_tenant')->create([
            'clinic_id' => $clinicB->id,
            'name' => 'Colaborador exclusivo B',
            'employment_type' => 'contractor',
            'active' => true,
        ]);

        $this->actingAs($actorA)
            ->get(route('employees.index'))
            ->assertOk()
            ->assertDontSee($employeeB->name);

        $this->get(route('employees.edit', $employeeB->id))->assertNotFound();

        $this->post(route('access-users.store'), [
            'employee_id' => $employeeB->id,
            'name' => $employeeB->name,
            'email' => 'tentativa-cruzada@vetflow.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'active' => '1',
            'role_ids' => [Role::query()->where('slug', 'caixa')->value('id')],
        ])->assertSessionHasErrors('employee_id');

        $this->assertDatabaseMissing('users', ['email' => 'tentativa-cruzada@vetflow.test']);

        $this->actingAs($actorB)
            ->get(route('employees.edit', $employeeB->id))
            ->assertOk()
            ->assertSee($employeeB->name);
    }

    public function test_existing_employee_receives_login_without_duplicate_functional_record(): void
    {
        $this->seed(AuthorizationSeeder::class);
        $clinic = $this->clinic('Clínica Acesso Posterior', '00000000001144');
        $actor = $this->administrator($clinic);
        $employee = Employee::query()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Colaboradora já cadastrada',
            'email' => 'colaboradora@vetflow.test',
            'phone' => '21999998888',
            'position' => 'Vendedora',
            'employment_type' => 'employee',
            'active' => false,
        ]);
        $cashier = Role::query()->where('slug', 'caixa')->firstOrFail();

        $this->actingAs($actor)
            ->get(route('access-users.create', [
                'clinic_id' => $clinic->id,
                'employee_id' => $employee->id,
            ]))
            ->assertOk()
            ->assertViewHas('preferredEmployee', fn (?Employee $record): bool => $record?->is($employee) === true)
            ->assertSee('Cadastro funcional existente')
            ->assertSee($employee->name);

        $this->post(route('access-users.store'), [
            'employee_id' => $employee->id,
            'name' => $employee->name,
            'email' => $employee->email,
            'phone' => $employee->phone,
            'position' => $employee->position,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'active' => '1',
            'role_ids' => [$cashier->id],
        ])
            ->assertRedirect(route('employees.edit', $employee->id))
            ->assertSessionHasNoErrors();

        $linkedUser = User::query()->where('email', $employee->email)->sole();
        $this->assertSame($linkedUser->id, $employee->refresh()->user_id);
        $this->assertFalse($employee->active);
        $this->assertSame(1, Employee::query()->count());
        $this->assertTrue($linkedUser->hasRole('caixa'));
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

    private function administrator(Clinic $clinic): User
    {
        $user = User::factory()->create([
            'clinic_id' => $clinic->id,
            'active' => true,
        ]);
        $role = Role::query()->where('slug', 'administrador')->firstOrFail();

        DB::table('user_roles')->insert([
            'ulid' => (string) Str::ulid(),
            'user_id' => $user->id,
            'role_id' => $role->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }
}
