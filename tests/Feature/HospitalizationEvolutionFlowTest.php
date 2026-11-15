<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Hospitalizations\Models\Hospitalization;
use App\Modules\Hospitalizations\Models\HospitalizationEvolution;
use App\Modules\Patients\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class HospitalizationEvolutionFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_clinic_user_adds_an_immutable_evolution_to_an_active_hospitalization(): void
    {
        $this->travelTo(Carbon::parse('2026-08-20 12:00:00'));
        [$clinic, $patient, $hospitalization] = $this->hospitalizationContext('Principal', '00000000001301');
        $user = $this->userForClinic($clinic, true);

        $this->actingAs($user)
            ->post(route('hospitalizations.evolutions.store', $hospitalization->id), [
                'observed_at' => '2026-08-20 09:15:00',
                'weight' => '12.40',
                'temperature' => '38.4',
                'heart_rate' => 96,
                'respiratory_rate' => 24,
                'notes' => "Paciente alerta e responsivo.\nAceitou alimentação oferecida.",
            ])
            ->assertRedirect(route('hospitalizations.edit', $hospitalization->id))
            ->assertSessionDoesntHaveErrors();

        $evolution = HospitalizationEvolution::query()->firstOrFail();

        $this->assertSame($clinic->id, (int) $evolution->clinic_id);
        $this->assertSame($hospitalization->id, (int) $evolution->hospitalization_id);
        $this->assertSame($user->id, (int) $evolution->recorded_by);
        $this->assertSame('12.40', $evolution->weight);
        $this->assertSame('38.4', $evolution->temperature);

        $this->actingAs($user)
            ->get(route('hospitalizations.edit', $hospitalization->id))
            ->assertOk()
            ->assertSee('Diário de evoluções')
            ->assertSee('Paciente alerta e responsivo.')
            ->assertSee($user->name)
            ->assertSee('38.4 °C');

        $this->actingAs($user)
            ->get(route('patients.show', $patient->id))
            ->assertOk()
            ->assertSee('Evoluções');
    }

    public function test_evolutions_enforce_permission_tenant_period_and_active_status(): void
    {
        $this->travelTo(Carbon::parse('2026-08-20 12:00:00'));
        [$clinicA, , $hospitalizationA] = $this->hospitalizationContext('A', '00000000001311');
        [$clinicB, , $hospitalizationB] = $this->hospitalizationContext('B', '00000000001312');
        $authorizedUser = $this->userForClinic($clinicA, true);
        $unauthorizedUser = $this->userForClinic($clinicA, false);

        $this->actingAs($authorizedUser)
            ->post(route('hospitalizations.evolutions.store', $hospitalizationB->id), [
                'observed_at' => '2026-08-20 09:15:00',
                'notes' => 'Tentativa externa.',
            ])
            ->assertNotFound();

        $this->actingAs($unauthorizedUser)
            ->post(route('hospitalizations.evolutions.store', $hospitalizationA->id), [
                'observed_at' => '2026-08-20 09:15:00',
                'notes' => 'Tentativa sem permissão.',
            ])
            ->assertForbidden();

        $this->actingAs($authorizedUser)
            ->from(route('hospitalizations.edit', $hospitalizationA->id))
            ->post(route('hospitalizations.evolutions.store', $hospitalizationA->id), [
                'observed_at' => '2026-08-20 07:59:00',
                'notes' => 'Registro anterior à admissão.',
            ])
            ->assertRedirect(route('hospitalizations.edit', $hospitalizationA->id))
            ->assertSessionHasErrors('observed_at');

        $hospitalizationA->update([
            'status' => 'discharged',
            'discharged_at' => '2026-08-20 11:00:00',
        ]);

        $this->actingAs($authorizedUser)
            ->from(route('hospitalizations.edit', $hospitalizationA->id))
            ->post(route('hospitalizations.evolutions.store', $hospitalizationA->id), [
                'observed_at' => '2026-08-20 10:30:00',
                'notes' => 'Tentativa após a alta.',
            ])
            ->assertRedirect(route('hospitalizations.edit', $hospitalizationA->id))
            ->assertSessionHasErrors('evolution');

        $this->assertDatabaseCount('hospitalization_evolutions', 0);
    }

    /** @return array{Clinic, Patient, Hospitalization} */
    private function hospitalizationContext(string $suffix, string $cnpj): array
    {
        $clinic = Clinic::query()->create([
            'corporate_name' => 'Clínica Evolução '.$suffix,
            'trade_name' => 'Clínica Evolução '.$suffix,
            'cnpj' => $cnpj,
            'active' => true,
        ]);
        $patient = Patient::query()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Paciente '.$suffix,
            'species' => 'Canino',
        ]);
        $hospitalization = Hospitalization::query()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => 'hospitalized',
            'admitted_at' => '2026-08-20 08:00:00',
            'admission_reason' => 'Observação clínica '.$suffix,
        ]);

        return [$clinic, $patient, $hospitalization];
    }

    private function userForClinic(Clinic $clinic, bool $authorized): User
    {
        $user = User::factory()->create(['active' => true, 'clinic_id' => $clinic->id]);
        $role = Role::query()->create([
            'name' => 'Perfil '.Str::random(6),
            'slug' => 'perfil-'.Str::lower(Str::random(8)),
            'active' => true,
        ]);

        if ($authorized) {
            foreach (['hospitalizations.manage', 'patients.manage'] as $permissionSlug) {
                $permission = Permission::query()->firstOrCreate(
                    ['slug' => $permissionSlug],
                    ['name' => $permissionSlug, 'group' => 'Tests', 'active' => true]
                );
                $role->permissions()->attach($permission->id);
            }
        }

        DB::table('user_roles')->insert([
            'ulid' => (string) Str::ulid(),
            'user_id' => $user->id,
            'role_id' => $role->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }
}
