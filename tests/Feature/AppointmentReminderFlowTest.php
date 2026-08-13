<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\Appointments\Models\AppointmentReminder;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Patients\Models\Patient;
use App\Modules\Tutors\Models\Tutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AppointmentReminderFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_prepares_whatsapp_contact_and_records_confirmation_with_history(): void
    {
        $clinic = $this->clinic('Clinica Lembrete', '00000000000301');
        $tutor = $this->tutor($clinic, 'Responsavel Lembrete', '21999990001', '21988880002');
        $patient = $this->patient($clinic, $tutor, 'Pet Lembrete');
        $appointment = $this->appointment($clinic, $patient, $tutor, 'Consulta para confirmar');
        $user = $this->userForClinic($clinic, ['appointments.manage']);

        $this->actingAs($user)
            ->get(route('appointments.reminders'))
            ->assertOk()
            ->assertSee('Consulta para confirmar')
            ->assertSee('Pet Lembrete')
            ->assertSee('Responsavel Lembrete')
            ->assertSee('https://wa.me/5521988880002', false)
            ->assertSee('Pendente');

        $this->post(route('appointments.reminders.store', $appointment->id), [
            'channel' => 'whatsapp',
            'outcome' => 'confirmed',
            'notes' => 'Responsavel confirmou pelo WhatsApp.',
            'return_from' => today()->toDateString(),
            'return_to' => today()->addDays(2)->toDateString(),
            'return_state' => 'all',
        ])
            ->assertRedirect(route('appointments.reminders', [
                'from' => today()->toDateString(),
                'to' => today()->addDays(2)->toDateString(),
                'state' => 'all',
            ]))
            ->assertSessionDoesntHaveErrors();

        $reminder = AppointmentReminder::query()->firstOrFail();

        $this->assertSame($clinic->id, (int) $reminder->clinic_id);
        $this->assertSame($appointment->id, (int) $reminder->appointment_id);
        $this->assertSame($user->id, (int) $reminder->recorded_by_user_id);
        $this->assertSame('whatsapp', $reminder->channel);
        $this->assertSame('confirmed', $reminder->outcome);
        $this->assertSame('21988880002', $reminder->destination_snapshot);
        $this->assertSame('confirmed', $appointment->fresh()->status);

        $this->get(route('appointments.edit', $appointment->id))
            ->assertOk()
            ->assertSee('Historico de lembretes')
            ->assertSee('Presenca confirmada')
            ->assertSee('Responsavel confirmou pelo WhatsApp.');

        $this->get(route('appointments.reminders', ['state' => 'confirmed']))
            ->assertOk()
            ->assertSee('Consulta para confirmar');

        $this->get(route('appointments.reminders', ['state' => 'pending']))
            ->assertOk()
            ->assertDontSee('Consulta para confirmar');
    }

    public function test_cancelled_contact_updates_appointment_and_keeps_the_audit_record(): void
    {
        $clinic = $this->clinic('Clinica Cancelamento Lembrete', '00000000000302');
        $tutor = $this->tutor($clinic, 'Responsavel Cancelamento', '21999990003');
        $patient = $this->patient($clinic, $tutor, 'Pet Cancelamento');
        $appointment = $this->appointment($clinic, $patient, $tutor, 'Consulta cancelada no contato');
        $user = $this->userForClinic($clinic, ['appointments.manage']);

        $this->actingAs($user)->post(route('appointments.reminders.store', $appointment->id), [
            'channel' => 'phone',
            'outcome' => 'cancelled',
            'notes' => 'Responsavel informou que nao podera comparecer.',
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame('cancelled', $appointment->fresh()->status);
        $this->assertDatabaseHas('appointment_reminders', [
            'clinic_id' => $clinic->id,
            'appointment_id' => $appointment->id,
            'outcome' => 'cancelled',
            'destination_snapshot' => '21999990003',
        ]);

        $this->get(route('appointments.edit', $appointment->id))
            ->assertOk()
            ->assertSee('Consulta cancelada')
            ->assertSee('Responsavel informou que nao podera comparecer.');
    }

    public function test_reminder_queue_requires_permission_and_rejects_another_clinics_appointment(): void
    {
        $clinicA = $this->clinic('Clinica Lembrete A', '00000000000303');
        $clinicB = $this->clinic('Clinica Lembrete B', '00000000000304');
        $tutorA = $this->tutor($clinicA, 'Responsavel A', '21999990004');
        $tutorB = $this->tutor($clinicB, 'Responsavel B', '21999990005');
        $patientA = $this->patient($clinicA, $tutorA, 'Pet A');
        $patientB = $this->patient($clinicB, $tutorB, 'Pet B');
        $appointmentA = $this->appointment($clinicA, $patientA, $tutorA, 'Consulta isolada A');
        $appointmentB = $this->appointment($clinicB, $patientB, $tutorB, 'Consulta isolada B');
        $userA = $this->userForClinic($clinicA, ['appointments.manage']);
        $unauthorized = $this->userForClinic($clinicA, []);

        $this->actingAs($userA)
            ->get(route('appointments.reminders'))
            ->assertOk()
            ->assertSee('Consulta isolada A')
            ->assertDontSee('Consulta isolada B');

        $this->post(route('appointments.reminders.store', $appointmentB->id), [
            'channel' => 'phone',
            'outcome' => 'contacted',
        ])->assertNotFound();

        $this->actingAs($unauthorized)
            ->get(route('appointments.reminders'))
            ->assertForbidden();

        $this->post(route('appointments.reminders.store', $appointmentA->id), [
            'channel' => 'phone',
            'outcome' => 'contacted',
        ])->assertForbidden();

        $this->assertSame(0, DB::table('appointment_reminders')->count());
    }

    public function test_contact_channel_requires_a_matching_destination(): void
    {
        $clinic = $this->clinic('Clinica Sem Email', '00000000000305');
        $tutor = $this->tutor($clinic, 'Responsavel Sem Email', '21999990006');
        $patient = $this->patient($clinic, $tutor, 'Pet Sem Email');
        $appointment = $this->appointment($clinic, $patient, $tutor, 'Consulta sem email');
        $user = $this->userForClinic($clinic, ['appointments.manage']);

        $this->actingAs($user)
            ->from(route('appointments.reminders'))
            ->post(route('appointments.reminders.store', $appointment->id), [
                'channel' => 'email',
                'outcome' => 'contacted',
            ])
            ->assertRedirect(route('appointments.reminders'))
            ->assertSessionHasErrors('channel');

        $this->assertDatabaseCount('appointment_reminders', 0);
        $this->assertSame('scheduled', $appointment->fresh()->status);
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

    private function tutor(Clinic $clinic, string $name, string $phone, ?string $secondary = null): Tutor
    {
        return Tutor::query()->create([
            'clinic_id' => $clinic->id,
            'name' => $name,
            'phone' => $phone,
            'phone_secondary' => $secondary,
            'active' => true,
        ]);
    }

    private function patient(Clinic $clinic, Tutor $tutor, string $name): Patient
    {
        return Patient::query()->create([
            'clinic_id' => $clinic->id,
            'tutor_id' => $tutor->id,
            'name' => $name,
            'species' => 'Canino',
        ]);
    }

    private function appointment(Clinic $clinic, Patient $patient, Tutor $tutor, string $title): Appointment
    {
        return Appointment::query()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'tutor_id' => $tutor->id,
            'title' => $title,
            'scheduled_at' => now()->addDay()->setTime(10, 0),
            'status' => 'scheduled',
        ]);
    }

    private function userForClinic(Clinic $clinic, array $permissionSlugs): User
    {
        $user = User::factory()->create([
            'active' => true,
            'clinic_id' => $clinic->id,
        ]);

        $role = Role::query()->create([
            'name' => 'Reminder role '.Str::random(6),
            'slug' => 'reminder-role-'.Str::lower(Str::random(8)),
            'description' => 'Reminder test role',
            'system' => false,
            'active' => true,
        ]);

        foreach ($permissionSlugs as $permissionSlug) {
            $permission = Permission::query()->firstOrCreate(
                ['slug' => $permissionSlug],
                [
                    'name' => Str::headline(str_replace('.', ' ', $permissionSlug)),
                    'description' => 'Test permission',
                    'group' => 'Tests',
                    'active' => true,
                ]
            );

            $role->permissions()->attach($permission->id);
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
