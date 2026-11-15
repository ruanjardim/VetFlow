<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Patients\Models\Patient;
use App\Modules\Schedules\Models\Schedule;
use App\Modules\Tutors\Models\Tutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PatientLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_lifecycle_preserves_history_cancels_future_events_and_is_reversible(): void
    {
        $clinic = $this->clinic('Clínica Ciclo de Vida', '12345678000210');
        $tutor = $this->tutor($clinic, 'Tutor do Ciclo', '52998224725');
        $user = $this->authorizedUser($clinic, ['patients.manage', 'appointments.manage']);
        $patient = $this->patient($clinic, $tutor, 'Luna Ciclo');
        $scheduledAppointment = Appointment::query()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'tutor_id' => $tutor->id,
            'title' => 'Consulta futura',
            'scheduled_at' => now()->addDay(),
            'status' => 'scheduled',
        ]);
        $completedAppointment = Appointment::query()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'tutor_id' => $tutor->id,
            'title' => 'Consulta histórica',
            'scheduled_at' => now()->subDay(),
            'status' => 'completed',
        ]);
        $pastPendingAppointment = Appointment::query()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'tutor_id' => $tutor->id,
            'title' => 'Consulta pendente antiga',
            'scheduled_at' => now()->subDays(2),
            'status' => 'scheduled',
        ]);
        $scheduled = Schedule::query()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'tutor_id' => $tutor->id,
            'title' => 'Agenda futura',
            'scheduled_date' => today()->addDay(),
            'scheduled_time' => '10:00',
            'status' => 'agendado',
        ]);
        $completed = Schedule::query()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'tutor_id' => $tutor->id,
            'title' => 'Agenda histórica',
            'scheduled_date' => today()->subDay(),
            'scheduled_time' => '10:00',
            'status' => 'concluido',
        ]);
        $pastPendingSchedule = Schedule::query()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'tutor_id' => $tutor->id,
            'title' => 'Agenda pendente antiga',
            'scheduled_date' => today()->subDays(2),
            'scheduled_time' => '10:00',
            'status' => 'agendado',
        ]);

        $this->actingAs($user)
            ->patch(route('patients.lifecycle', $patient), [
                'status' => 'archived',
                'reason' => 'Cadastro duplicado confirmado pela clínica.',
            ])
            ->assertRedirect(route('patients.show', $patient));

        $patient->refresh();
        $this->assertSame('archived', $patient->lifecycle_status);
        $this->assertSame('Cadastro duplicado confirmado pela clínica.', $patient->lifecycle_reason);
        $this->assertSame($user->id, $patient->lifecycle_changed_by_user_id);
        $this->assertNotNull($patient->lifecycle_changed_at);
        $this->assertNull($patient->deleted_at);
        $this->assertSame('cancelled', $scheduledAppointment->refresh()->status);
        $this->assertSame('completed', $completedAppointment->refresh()->status);
        $this->assertSame('scheduled', $pastPendingAppointment->refresh()->status);
        $this->assertSame('cancelado', $scheduled->refresh()->status);
        $this->assertSame('concluido', $completed->refresh()->status);
        $this->assertSame('agendado', $pastPendingSchedule->refresh()->status);

        $event = AuditEvent::query()->where('event', 'patient.lifecycle.archived')->sole();
        $this->assertSame('Cadastro duplicado confirmado pela clínica.', $event->metadata['reason']);
        $this->assertSame(1, $event->metadata['cancelled_appointments']);
        $this->assertSame(1, $event->metadata['cancelled_schedules']);

        $this->get(route('patients.index'))
            ->assertOk()
            ->assertDontSee('Luna Ciclo');
        $this->get(route('patients.index', ['status' => 'archived']))
            ->assertOk()
            ->assertSee('Luna Ciclo')
            ->assertSee('Arquivado');
        $this->get(route('patients.show', $patient))
            ->assertOk()
            ->assertSee('Cadastro duplicado confirmado pela clínica.')
            ->assertSee('Paciente arquivado')
            ->assertDontSee(route('appointments.create', [
                'patient_id' => $patient->id,
                'tutor_id' => $patient->tutor_id,
            ]));
        $this->get(route('patients.edit', $patient))
            ->assertRedirect(route('patients.show', $patient))
            ->assertSessionHas('error');
        $this->put(route('patients.update', $patient), [
            'tutor_id' => $tutor->id,
            'name' => 'Nome que não pode ser aplicado',
        ])->assertSessionHasErrors('patient');
        $this->assertSame('Luna Ciclo', $patient->refresh()->name);
        $this->get(route('appointments.create'))
            ->assertOk()
            ->assertDontSee('Luna Ciclo');
        $this->post(route('appointments.store'), [
            'patient_id' => $patient->id,
            'tutor_id' => $tutor->id,
            'title' => 'Consulta indevida',
            'scheduled_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'status' => 'scheduled',
        ])->assertSessionHasErrors('patient_id');

        $this->patch(route('patients.lifecycle', $patient), [
            'status' => 'active',
            'reason' => 'Duplicidade descartada após conferência documental.',
        ])->assertRedirect(route('patients.show', $patient));

        $this->assertSame('active', $patient->refresh()->lifecycle_status);
        $this->assertDatabaseHas('audit_events', [
            'event' => 'patient.lifecycle.restored',
            'subject_id' => (string) $patient->id,
        ]);
        $this->assertSame('cancelled', $scheduledAppointment->refresh()->status);

        $newAppointment = Appointment::query()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'tutor_id' => $tutor->id,
            'title' => 'Consulta após restauração',
            'scheduled_at' => now()->addDays(3),
            'status' => 'confirmed',
        ]);

        $this->patch(route('patients.lifecycle', $patient), [
            'status' => 'deceased',
            'reason' => 'Óbito comunicado e confirmado pelo responsável.',
        ])->assertRedirect(route('patients.show', $patient));

        $this->assertSame('deceased', $patient->refresh()->lifecycle_status);
        $this->assertSame('cancelled', $newAppointment->refresh()->status);
        $this->assertDatabaseHas('audit_events', [
            'event' => 'patient.lifecycle.deceased',
            'subject_id' => (string) $patient->id,
        ]);

        $this->patch(route('patients.lifecycle', $patient), [
            'status' => 'active',
            'reason' => 'Registro de óbito lançado no paciente incorreto.',
        ])->assertRedirect(route('patients.show', $patient));

        $this->assertSame('active', $patient->refresh()->lifecycle_status);
        $this->assertDatabaseHas('audit_events', [
            'event' => 'patient.lifecycle.death-corrected',
            'subject_id' => (string) $patient->id,
        ]);
    }

    public function test_lifecycle_requires_reason_rejects_invalid_transitions_and_respects_tenant(): void
    {
        $clinic = $this->clinic('Clínica Própria', '12345678000211');
        $otherClinic = $this->clinic('Clínica Externa', '12345678000212');
        $tutor = $this->tutor($clinic, 'Tutor Próprio', '11144477735');
        $otherTutor = $this->tutor($otherClinic, 'Tutor Externo', '93541134780');
        $user = $this->authorizedUser($clinic, ['patients.manage']);
        $patient = $this->patient($clinic, $tutor, 'Paciente Próprio');
        $otherPatient = $this->patient($otherClinic, $otherTutor, 'Paciente Externo');

        $this->actingAs($user)
            ->patch(route('patients.lifecycle', $patient), [
                'status' => 'archived',
                'reason' => 'curta',
            ])
            ->assertSessionHasErrors('reason');

        $this->patch(route('patients.lifecycle', $patient), [
            'status' => 'active',
            'reason' => 'Tentativa de manter o mesmo estado.',
        ])->assertSessionHasErrors('status');

        $this->patch(route('patients.lifecycle', $otherPatient), [
            'status' => 'archived',
            'reason' => 'Não deveria acessar outra clínica.',
        ])->assertNotFound();

        $this->delete('/patients/'.$patient->id)->assertStatus(405);
        $this->assertSame('active', $patient->refresh()->lifecycle_status);
        $this->assertDatabaseCount('audit_events', 0);
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

    private function tutor(Clinic $clinic, string $name, string $cpf): Tutor
    {
        return Tutor::query()->create([
            'clinic_id' => $clinic->id,
            'name' => $name,
            'cpf' => $cpf,
            'phone' => '21999990001',
            'active' => true,
        ]);
    }

    private function patient(Clinic $clinic, Tutor $tutor, string $name): Patient
    {
        return Patient::query()->create([
            'clinic_id' => $clinic->id,
            'tutor_id' => $tutor->id,
            'name' => $name,
            'lifecycle_status' => 'active',
        ]);
    }

    /** @param array<int, string> $permissions */
    private function authorizedUser(Clinic $clinic, array $permissions): User
    {
        $user = User::factory()->create([
            'active' => true,
            'clinic_id' => $clinic->id,
        ]);
        $permissionIds = collect($permissions)->map(function (string $slug): int {
            return Permission::query()->firstOrCreate(['slug' => $slug], [
                'name' => $slug,
                'description' => $slug,
                'group' => 'Tests',
                'active' => true,
            ])->id;
        });
        $role = Role::query()->create([
            'name' => 'Ciclo '.Str::random(6),
            'slug' => 'ciclo-'.Str::lower(Str::random(8)),
            'description' => 'Test role',
            'system' => false,
            'active' => true,
        ]);
        $role->permissions()->attach($permissionIds);

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
