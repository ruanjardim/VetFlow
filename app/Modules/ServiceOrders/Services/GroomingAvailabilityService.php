<?php

namespace App\Modules\ServiceOrders\Services;

use App\Models\User;
use App\Modules\Audit\Services\AuditTrailService;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\ServiceOrders\Models\GroomingScheduleBlock;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class GroomingAvailabilityService
{
    public const DAY_LABELS = [
        1 => 'Segunda-feira',
        2 => 'Terça-feira',
        3 => 'Quarta-feira',
        4 => 'Quinta-feira',
        5 => 'Sexta-feira',
        6 => 'Sábado',
        0 => 'Domingo',
    ];

    public const SLOT_OPTIONS = [5, 10, 15, 20, 30, 45, 60];

    public function __construct(private readonly AuditTrailService $audit) {}

    /** @return array{slot_minutes: int, days: array<string, array<string, mixed>>} */
    public function defaultSchedule(): array
    {
        $opensAt = (string) config('petshop.grooming.opens_at', '08:00');
        $closesAt = (string) config('petshop.grooming.closes_at', '18:00');

        return [
            'slot_minutes' => $this->normalizeSlotMinutes((int) config('petshop.grooming.slot_minutes', 30)),
            'days' => collect(array_keys(self::DAY_LABELS))->mapWithKeys(fn (int $weekday): array => [
                (string) $weekday => [
                    'enabled' => true,
                    'opens_at' => $opensAt,
                    'break_start' => null,
                    'break_end' => null,
                    'closes_at' => $closesAt,
                ],
            ])->all(),
        ];
    }

    /** @return array{slot_minutes: int, days: array<string, array<string, mixed>>} */
    public function scheduleFor(Clinic $clinic, ?User $professional = null): array
    {
        $clinicSchedule = $this->normalizeSchedule($clinic->grooming_schedule ?? []);

        if ($professional?->grooming_schedule) {
            $professionalSchedule = $this->normalizeSchedule($professional->grooming_schedule);
            $professionalSchedule['slot_minutes'] = $clinicSchedule['slot_minutes'];

            return $professionalSchedule;
        }

        return $clinicSchedule;
    }

    /** @return array{slot_minutes: int, days: array<string, array<string, mixed>>} */
    public function normalizeSchedule(array $schedule): array
    {
        $default = $this->defaultSchedule();
        $days = [];

        foreach (array_keys(self::DAY_LABELS) as $weekday) {
            $input = $schedule['days'][(string) $weekday] ?? $schedule['days'][$weekday] ?? [];
            $fallback = $default['days'][(string) $weekday];
            $days[(string) $weekday] = [
                'enabled' => array_key_exists('enabled', $input)
                    ? filter_var($input['enabled'], FILTER_VALIDATE_BOOL)
                    : (bool) $fallback['enabled'],
                'opens_at' => $this->normalizeTime($input['opens_at'] ?? $fallback['opens_at']),
                'break_start' => $this->normalizeOptionalTime($input['break_start'] ?? null),
                'break_end' => $this->normalizeOptionalTime($input['break_end'] ?? null),
                'closes_at' => $this->normalizeTime($input['closes_at'] ?? $fallback['closes_at']),
            ];
        }

        return [
            'slot_minutes' => $this->normalizeSlotMinutes((int) ($schedule['slot_minutes'] ?? $default['slot_minutes'])),
            'days' => $days,
        ];
    }

    /** @param array<string, mixed> $data */
    public function saveSchedule(Clinic $clinic, array $data, ?User $professional = null): void
    {
        if ($professional) {
            $before = ['grooming_schedule' => $professional->grooming_schedule];

            if ((bool) ($data['inherit'] ?? false)) {
                $professional->update(['grooming_schedule' => null]);

                $this->audit->record(
                    'grooming.schedule.professional.updated',
                    $professional,
                    $before,
                    ['grooming_schedule' => null],
                    clinicId: $clinic->id,
                    subjectLabel: $professional->name,
                );

                return;
            }

            $schedule = $this->normalizeSchedule([
                'slot_minutes' => $this->scheduleFor($clinic)['slot_minutes'],
                'days' => $data['days'] ?? [],
            ]);
            $saved = ['days' => $schedule['days']];
            $professional->update(['grooming_schedule' => $saved]);
            $this->audit->record(
                'grooming.schedule.professional.updated',
                $professional,
                $before,
                ['grooming_schedule' => $saved],
                clinicId: $clinic->id,
                subjectLabel: $professional->name,
            );

            return;
        }

        $before = ['grooming_schedule' => $clinic->grooming_schedule];
        $current = $this->normalizeSchedule($clinic->grooming_schedule ?? []);
        $saved = $this->normalizeSchedule([
            'slot_minutes' => $data['slot_minutes'] ?? null,
            'days' => $data['days'] ?? $current['days'],
        ]);
        $clinic->update(['grooming_schedule' => $saved]);
        $this->audit->record(
            'grooming.schedule.clinic.updated',
            $clinic,
            $before,
            ['grooming_schedule' => $saved],
            clinicId: $clinic->id,
            subjectLabel: $clinic->trade_name ?? $clinic->corporate_name,
        );
    }

    /**
     * @return Collection<int, array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    public function workingIntervals(CarbonInterface $day, Clinic $clinic, ?User $professional = null): Collection
    {
        $start = CarbonImmutable::parse($day->toDateString())->startOfDay();

        return collect([[$start, $start->addDay()]]);
    }

    /** @return Collection<int, GroomingScheduleBlock> */
    public function blocksForDay(CarbonInterface $day, Clinic $clinic, ?User $professional = null): Collection
    {
        $start = CarbonImmutable::parse($day->toDateString())->startOfDay();
        $end = $start->addDay();

        return $this->blocksForRange($start, $end, $clinic, $professional);
    }

    /** @return Collection<int, GroomingScheduleBlock> */
    public function blocksForRange(
        CarbonInterface $start,
        CarbonInterface $end,
        Clinic $clinic,
        ?User $professional = null,
    ): Collection {
        $start = CarbonImmutable::parse($start);
        $end = CarbonImmutable::parse($end);

        return GroomingScheduleBlock::query()
            ->withoutGlobalScopes()
            ->where('clinic_id', $clinic->id)
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->where(function ($query) use ($professional): void {
                $query->whereNull('user_id');

                if ($professional) {
                    $query->orWhere('user_id', $professional->id);
                }
            })
            ->orderBy('starts_at')
            ->get();
    }

    public function availabilityIssue(
        Clinic $clinic,
        ?User $professional,
        CarbonInterface $start,
        int $durationMinutes,
        ?Collection $blocks = null,
    ): ?string {
        $start = CarbonImmutable::parse($start);
        $end = $start->addMinutes(max(5, $durationMinutes));
        $block = ($blocks ?? $this->blocksForRange($start, $end, $clinic, $professional))
            ->first(fn (GroomingScheduleBlock $candidate): bool => $candidate->starts_at->lt($end)
                && $candidate->ends_at->gt($start));

        if ($block) {
            return 'O horário está bloqueado'.($block->reason ? ': '.$block->reason.'.' : '.');
        }

        return null;
    }

    /** @param array<string, mixed> $data */
    public function createBlock(Clinic $clinic, array $data): GroomingScheduleBlock
    {
        $userId = isset($data['user_id']) && $data['user_id'] !== '' ? (int) $data['user_id'] : null;

        if ($userId && ! User::query()->whereKey($userId)->where('clinic_id', $clinic->id)->exists()) {
            throw ValidationException::withMessages([
                'user_id' => 'O profissional não pertence à clínica selecionada.',
            ]);
        }

        $block = GroomingScheduleBlock::query()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $userId,
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'reason' => filled($data['reason'] ?? null) ? trim((string) $data['reason']) : null,
            'created_by' => auth()->id(),
        ]);

        $this->audit->record(
            'grooming.schedule.block.created',
            $block,
            [],
            [
                'user_id' => $block->user_id,
                'starts_at' => $block->starts_at?->toIso8601String(),
                'ends_at' => $block->ends_at?->toIso8601String(),
                'reason' => $block->reason,
            ],
            clinicId: $clinic->id,
            subjectLabel: $block->reason ?? 'Bloqueio de agenda',
        );

        return $block;
    }

    public function deleteBlock(GroomingScheduleBlock $block, Clinic $clinic): void
    {
        if ((int) $block->clinic_id !== (int) $clinic->id) {
            throw ValidationException::withMessages(['block' => 'O bloqueio não pertence à clínica selecionada.']);
        }

        $before = [
            'user_id' => $block->user_id,
            'starts_at' => $block->starts_at?->toIso8601String(),
            'ends_at' => $block->ends_at?->toIso8601String(),
            'reason' => $block->reason,
        ];
        $this->audit->record(
            'grooming.schedule.block.deleted',
            $block,
            $before,
            [],
            clinicId: $clinic->id,
            subjectLabel: $block->reason ?? 'Bloqueio de agenda',
        );
        $block->delete();
    }

    private function normalizeSlotMinutes(int $minutes): int
    {
        return in_array($minutes, self::SLOT_OPTIONS, true) ? $minutes : 30;
    }

    private function normalizeTime(mixed $time): string
    {
        return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', (string) $time)
            ? (string) $time
            : '08:00';
    }

    private function normalizeOptionalTime(mixed $time): ?string
    {
        return $time !== null && $time !== '' ? $this->normalizeTime($time) : null;
    }
}
