<?php

namespace App\Modules\Appointments\Services;

use App\Modules\Appointments\Models\Appointment;
use App\Modules\Appointments\Models\AppointmentReminder;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentReminderService
{
    public const CHANNEL_LABELS = [
        'whatsapp' => 'WhatsApp',
        'phone' => 'Ligacao',
        'email' => 'E-mail',
        'other' => 'Outro',
    ];

    public const OUTCOME_LABELS = [
        'contacted' => 'Aviso enviado',
        'confirmed' => 'Presenca confirmada',
        'no_answer' => 'Sem resposta',
        'reschedule_requested' => 'Solicitou reagendamento',
        'cancelled' => 'Consulta cancelada',
    ];

    public function queue(?string $from = null, ?string $to = null, string $state = 'all'): array
    {
        [$start, $end] = $this->range($from, $to);
        $state = $this->normalizeState($state);

        $appointments = Appointment::query()
            ->with([
                'clinic',
                'patient',
                'tutor',
                'latestReminder.recordedBy',
            ])
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->whereBetween('scheduled_at', [$start, $end])
            ->oldest('scheduled_at')
            ->get()
            ->map(fn (Appointment $appointment) => $this->queueRow($appointment));

        $filtered = $appointments
            ->when($state !== 'all', fn (Collection $rows) => $rows->where('state', $state))
            ->values();

        return [
            'period' => [
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
                'label' => $start->format('d/m/Y').' a '.$end->format('d/m/Y'),
            ],
            'filter' => [
                'state' => $state,
                'state_label' => $state === 'all'
                    ? 'Todos'
                    : ($state === 'pending' ? 'Pendentes' : self::OUTCOME_LABELS[$state]),
            ],
            'stats' => [
                'appointments' => $appointments->count(),
                'pending' => $appointments->where('state', 'pending')->count(),
                'confirmed' => $appointments->where('state', 'confirmed')->count(),
                'needs_follow_up' => $appointments
                    ->whereIn('state', ['no_answer', 'reschedule_requested'])
                    ->count(),
            ],
            'appointments' => $filtered,
        ];
    }

    public function record(int $appointmentId, array $data): AppointmentReminder
    {
        return DB::transaction(function () use ($appointmentId, $data) {
            $appointment = Appointment::query()
                ->with(['clinic', 'patient', 'tutor'])
                ->findOrFail($appointmentId);

            if (! in_array($appointment->status, ['scheduled', 'confirmed'], true)) {
                throw ValidationException::withMessages([
                    'appointment' => 'Esta consulta nao aceita novos lembretes no status atual.',
                ]);
            }

            $channel = $data['channel'];
            $destination = $this->destination($appointment, $channel);

            if ($channel !== 'other' && ! $destination) {
                throw ValidationException::withMessages([
                    'channel' => 'O responsavel nao possui contato cadastrado para este canal.',
                ]);
            }

            $reminder = AppointmentReminder::query()->create([
                'clinic_id' => $appointment->clinic_id,
                'appointment_id' => $appointment->id,
                'recorded_by_user_id' => auth()->id(),
                'channel' => $channel,
                'outcome' => $data['outcome'],
                'destination_snapshot' => $destination,
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                'contacted_at' => now(),
            ]);

            if ($data['outcome'] === 'confirmed' && $appointment->status !== 'confirmed') {
                $appointment->update(['status' => 'confirmed']);
            }

            if ($data['outcome'] === 'cancelled') {
                $appointment->update(['status' => 'cancelled']);
            }

            return $reminder->load(['appointment', 'recordedBy']);
        });
    }

    private function queueRow(Appointment $appointment): array
    {
        $latest = $appointment->latestReminder;

        return [
            'appointment' => $appointment,
            'latest_reminder' => $latest,
            'state' => $latest?->outcome ?? 'pending',
            'state_label' => $latest
                ? (self::OUTCOME_LABELS[$latest->outcome] ?? ucfirst($latest->outcome))
                : 'Pendente',
            'message' => $this->message($appointment),
            'whatsapp_url' => $this->whatsappUrl($appointment),
            'has_contact' => (bool) ($appointment->tutor?->phone
                || $appointment->tutor?->phone_secondary
                || $appointment->tutor?->email),
        ];
    }

    private function message(Appointment $appointment): string
    {
        $responsible = $appointment->tutor?->name ?: 'responsavel';
        $patient = $appointment->patient?->name ?: 'seu pet';
        $clinic = $appointment->clinic?->trade_name
            ?: $appointment->clinic?->corporate_name
            ?: 'clinica';

        return sprintf(
            'Ola, %s! A %s lembra que a consulta de %s esta marcada para %s as %s. Por favor, responda confirmando a presenca.',
            $responsible,
            $clinic,
            $patient,
            $appointment->scheduled_at->format('d/m/Y'),
            $appointment->scheduled_at->format('H:i')
        );
    }

    private function whatsappUrl(Appointment $appointment): ?string
    {
        $phone = $appointment->tutor?->phone_secondary ?: $appointment->tutor?->phone;
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 10 || strlen($digits) === 11) {
            $digits = '55'.$digits;
        }

        return 'https://wa.me/'.$digits.'?text='.rawurlencode($this->message($appointment));
    }

    private function destination(Appointment $appointment, string $channel): ?string
    {
        return match ($channel) {
            'whatsapp' => $appointment->tutor?->phone_secondary ?: $appointment->tutor?->phone,
            'phone' => $appointment->tutor?->phone,
            'email' => $appointment->tutor?->email,
            default => null,
        };
    }

    private function normalizeState(string $state): string
    {
        return $state === 'pending' || array_key_exists($state, self::OUTCOME_LABELS)
            ? $state
            : 'all';
    }

    private function range(?string $from, ?string $to): array
    {
        $start = $from ? Carbon::parse($from)->startOfDay() : today()->startOfDay();
        $end = $to ? Carbon::parse($to)->endOfDay() : today()->addDays(2)->endOfDay();

        return [$start, $end];
    }
}
