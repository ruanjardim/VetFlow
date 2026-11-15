<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Patients\Models\Patient;
use App\Modules\Schedules\Models\Schedule;
use App\Modules\Tutors\Models\Tutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ScheduleCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_visual_agenda_combines_current_clinic_schedules_and_appointments(): void
    {
        $clinicA = $this->clinic('Clínica Agenda A', '00000000001001');
        $clinicB = $this->clinic('Clínica Agenda B', '00000000001002');
        $patientA = $this->patient($clinicA, 'Luna');
        $patientB = $this->patient($clinicB, 'Nina');

        Schedule::query()->create([
            'clinic_id' => $clinicA->id,
            'patient_id' => $patientA->id,
            'tutor_id' => $patientA->tutor_id,
            'title' => 'Retorno da Luna',
            'scheduled_date' => '2026-08-12',
            'scheduled_time' => '09:00',
            'status' => 'confirmado',
        ]);
        Appointment::query()->create([
            'clinic_id' => $clinicA->id,
            'patient_id' => $patientA->id,
            'tutor_id' => $patientA->tutor_id,
            'title' => 'Consulta da Luna',
            'scheduled_at' => '2026-08-13 14:30:00',
            'status' => 'confirmed',
        ]);
        Schedule::query()->create([
            'clinic_id' => $clinicB->id,
            'patient_id' => $patientB->id,
            'tutor_id' => $patientB->tutor_id,
            'title' => 'Evento externo',
            'scheduled_date' => '2026-08-12',
            'scheduled_time' => '11:00',
            'status' => 'agendado',
        ]);

        $user = $this->userForClinic($clinicA, ['schedules.manage', 'appointments.manage']);

        $this->actingAs($user)
            ->get(route('schedules.index', ['view' => 'week', 'date' => '2026-08-12']))
            ->assertOk()
            ->assertSee('agenda-week')
            ->assertSee('Retorno da Luna')
            ->assertSee('Consulta da Luna')
            ->assertDontSee('Evento externo');

        $this->actingAs($user)
            ->get(route('schedules.index', ['view' => 'month', 'date' => '2026-08-12']))
            ->assertOk()
            ->assertSee('agenda-month')
            ->assertSee('Retorno da Luna');
    }

    private function clinic(string $name, string $cnpj): Clinic
    {
        return Clinic::query()->create(['corporate_name' => $name, 'trade_name' => $name, 'cnpj' => $cnpj, 'active' => true]);
    }

    private function patient(Clinic $clinic, string $name): Patient
    {
        $tutor = Tutor::query()->create(['clinic_id' => $clinic->id, 'name' => 'Tutor '.$name, 'phone' => '21999990000', 'active' => true]);

        return Patient::query()->create(['clinic_id' => $clinic->id, 'tutor_id' => $tutor->id, 'name' => $name, 'species' => 'Canino']);
    }

    private function userForClinic(Clinic $clinic, array $permissionSlugs): User
    {
        $user = User::factory()->create(['active' => true, 'clinic_id' => $clinic->id]);
        $role = Role::query()->create(['name' => 'Agenda '.Str::random(6), 'slug' => 'agenda-'.Str::lower(Str::random(8)), 'active' => true]);

        foreach ($permissionSlugs as $permissionSlug) {
            $permission = Permission::query()->firstOrCreate(['slug' => $permissionSlug], ['name' => $permissionSlug, 'group' => 'Tests', 'active' => true]);
            $role->permissions()->attach($permission->id);
        }

        DB::table('user_roles')->insert(['ulid' => (string) Str::ulid(), 'user_id' => $user->id, 'role_id' => $role->id, 'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }
}
