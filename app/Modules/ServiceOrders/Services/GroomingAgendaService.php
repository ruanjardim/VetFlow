<?php

namespace App\Modules\ServiceOrders\Services;

use App\Models\User;
use App\Modules\ServiceOrders\Models\ServiceOrder;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Agenda de Banho e Tosa: grade do dia por profissional, horarios livres e
 * conflitos. A comanda (ServiceOrder) e o proprio agendamento, entao o fluxo
 * agendou -> chegou -> atendeu -> animal pronto -> recebeu no PDV usa um so
 * registro.
 */
class GroomingAgendaService
{
    public const RECURRENCE_FREQUENCIES = [
        'weekly' => ['label' => 'Toda semana', 'weeks' => 1],
        'biweekly' => ['label' => 'A cada 2 semanas', 'weeks' => 2],
        'every_3_weeks' => ['label' => 'A cada 3 semanas', 'weeks' => 3],
        'every_4_weeks' => ['label' => 'A cada 4 semanas', 'weeks' => 4],
    ];

    public function opensAt(CarbonInterface $day): CarbonImmutable
    {
        return $this->timeOn($day, (string) config('petshop.grooming.opens_at', '08:00'));
    }

    public function closesAt(CarbonInterface $day): CarbonImmutable
    {
        return $this->timeOn($day, (string) config('petshop.grooming.closes_at', '18:00'));
    }

    public function slotMinutes(): int
    {
        return max(5, (int) config('petshop.grooming.slot_minutes', 30));
    }

    /**
     * Profissionais ativos da clinica que podem receber agendamentos.
     *
     * @return Collection<int, User>
     */
    public function professionals(?int $clinicId): Collection
    {
        if ($clinicId === null) {
            return collect();
        }

        return User::query()
            ->active()
            ->where('clinic_id', $clinicId)
            ->orderBy('name')
            ->get();
    }

    /**
     * Colunas da agenda: quem esta marcado como "Atende banho e tosa" e quem
     * tem atendimento no dia. Sem ninguem marcado, mostra todos os ativos.
     *
     * @param  Collection<int, ServiceOrder>  $orders
     * @return Collection<int, User>
     */
    public function agendaProfessionals(?int $clinicId, Collection $orders): Collection
    {
        $all = $this->professionals($clinicId);
        $flagged = $all->where('grooming_professional', true);

        if ($flagged->isEmpty()) {
            return $all;
        }

        $withOrders = $all->whereIn('id', $orders->pluck('assigned_user_id')->filter()->unique());

        return $flagged->merge($withOrders)->unique('id')->sortBy('name')->values();
    }

    /**
     * Comandas que ocupam agenda no dia (inclui finalizadas para historico).
     *
     * @return Collection<int, ServiceOrder>
     */
    public function ordersForDay(CarbonInterface $day, ?int $clinicId): Collection
    {
        return ServiceOrder::query()
            ->with(['tutor', 'patient', 'assignedUser', 'items'])
            ->when($clinicId !== null, fn ($query) => $query->where('clinic_id', $clinicId))
            ->whereNotNull('scheduled_at')
            ->whereDate('scheduled_at', $day->toDateString())
            ->whereNotIn('status', ['cancelled'])
            ->orderBy('scheduled_at')
            ->get();
    }

    /**
     * Grade do dia: horarios x profissionais.
     *
     * @return array<string, mixed>
     */
    public function dayGrid(CarbonInterface $day, ?int $clinicId): array
    {
        $day = CarbonImmutable::parse($day->toDateString());
        $orders = $this->ordersForDay($day, $clinicId);
        $professionals = $this->agendaProfessionals($clinicId, $orders);

        $columns = $professionals
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'orders' => $orders->where('assigned_user_id', $user->id)->values(),
            ])
            ->values();

        $unassigned = $orders
            ->filter(fn (ServiceOrder $order) => $order->assigned_user_id === null
                || ! $professionals->contains('id', $order->assigned_user_id))
            ->values();

        if ($unassigned->isNotEmpty() || $columns->isEmpty()) {
            $columns->push([
                'id' => null,
                'name' => 'Sem profissional',
                'orders' => $unassigned,
            ]);
        }

        $opens = $this->opensAt($day);
        $closes = $this->closesAt($day);
        $slotMinutes = $this->slotMinutes();

        $firstOrder = $orders->min('scheduled_at');
        $lastEnd = $orders->map(fn (ServiceOrder $order) => $order->scheduledEnd())->filter()->max();

        $gridStart = $firstOrder && $firstOrder->lt($opens) ? $this->floorToSlot(CarbonImmutable::parse($firstOrder)) : $opens;
        $gridEnd = $lastEnd && $lastEnd->gt($closes) ? CarbonImmutable::parse($lastEnd) : $closes;

        $slots = collect();
        for ($cursor = $gridStart; $cursor->lt($gridEnd); $cursor = $cursor->addMinutes($slotMinutes)) {
            $slots->push($cursor);
        }

        return [
            'day' => $day,
            'hasFlaggedProfessionals' => $this->professionals($clinicId)->contains('grooming_professional', true),
            'slots' => $slots,
            'slotMinutes' => $slotMinutes,
            'gridStart' => $gridStart,
            'columns' => $columns,
            'orders' => $orders,
            'summary' => [
                'total' => $orders->count(),
                'waiting' => $orders->whereIn('status', ServiceOrder::PRE_ARRIVAL_STATUSES)->count(),
                'late' => $orders->filter(fn (ServiceOrder $order) => $order->isLate())->count(),
                'in_progress' => $orders->whereIn('status', ['open', 'in_service'])->count(),
                'ready' => $orders->where('status', 'waiting_pickup')->count(),
                'finished' => $orders->where('status', 'finished')->count(),
                'expected_revenue' => (float) $orders->whereNotIn('status', ['no_show'])->sum('total'),
            ],
        ];
    }

    /**
     * Comandas do mesmo profissional que se sobrepoem ao intervalo.
     *
     * @return Collection<int, ServiceOrder>
     */
    public function conflicts(
        ?int $clinicId,
        ?int $assignedUserId,
        CarbonInterface $start,
        int $durationMinutes,
        ?int $ignoreOrderId = null,
    ): Collection {
        if ($assignedUserId === null) {
            return collect();
        }

        $start = CarbonImmutable::parse($start);
        $end = $start->addMinutes(max(5, $durationMinutes));

        return ServiceOrder::query()
            ->with(['patient'])
            ->when($clinicId !== null, fn ($query) => $query->where('clinic_id', $clinicId))
            ->where('assigned_user_id', $assignedUserId)
            ->whereIn('status', ServiceOrder::BLOCKING_STATUSES)
            ->whereNotNull('scheduled_at')
            ->when($ignoreOrderId !== null, fn ($query) => $query->whereKeyNot($ignoreOrderId))
            ->whereDate('scheduled_at', $start->toDateString())
            ->get()
            ->filter(function (ServiceOrder $order) use ($start, $end): bool {
                $orderStart = CarbonImmutable::parse($order->scheduled_at);
                $orderEnd = $orderStart->addMinutes($order->effectiveDuration());

                return $orderStart->lt($end) && $orderEnd->gt($start);
            })
            ->values();
    }

    /**
     * Horarios livres do dia para o profissional e a duracao informados.
     *
     * @return Collection<int, string> horarios no formato H:i
     */
    public function availableSlots(
        CarbonInterface $day,
        ?int $clinicId,
        ?int $assignedUserId,
        int $durationMinutes,
        ?int $ignoreOrderId = null,
    ): Collection {
        $day = CarbonImmutable::parse($day->toDateString());
        $duration = max(5, $durationMinutes);
        $closes = $this->closesAt($day);
        $now = CarbonImmutable::now();

        $busy = $assignedUserId === null
            ? collect()
            : ServiceOrder::query()
                ->when($clinicId !== null, fn ($query) => $query->where('clinic_id', $clinicId))
                ->where('assigned_user_id', $assignedUserId)
                ->whereIn('status', ServiceOrder::BLOCKING_STATUSES)
                ->whereDate('scheduled_at', $day->toDateString())
                ->when($ignoreOrderId !== null, fn ($query) => $query->whereKeyNot($ignoreOrderId))
                ->get()
                ->map(fn (ServiceOrder $order) => [
                    CarbonImmutable::parse($order->scheduled_at),
                    CarbonImmutable::parse($order->scheduled_at)->addMinutes($order->effectiveDuration()),
                ]);

        $slots = collect();

        for ($cursor = $this->opensAt($day); $cursor->addMinutes($duration)->lte($closes); $cursor = $cursor->addMinutes($this->slotMinutes())) {
            if ($day->isSameDay($now) && $cursor->lt($now)) {
                continue;
            }

            $end = $cursor->addMinutes($duration);
            $overlaps = $busy->contains(fn (array $interval) => $interval[0]->lt($end) && $interval[1]->gt($cursor));

            if (! $overlaps) {
                $slots->push($cursor->format('H:i'));
            }
        }

        return $slots;
    }

    /**
     * Datas das repeticoes (a primeira ocorrencia nao entra).
     *
     * @return Collection<int, CarbonImmutable>
     */
    public function recurrenceDates(CarbonInterface $first, ?string $frequency, ?int $count): Collection
    {
        $weeks = self::RECURRENCE_FREQUENCIES[$frequency]['weeks'] ?? null;
        $count = min((int) $count, (int) config('petshop.grooming.max_recurrences', 12));

        if ($weeks === null || $count < 2) {
            return collect();
        }

        $first = CarbonImmutable::parse($first);

        return collect(range(1, $count - 1))
            ->map(fn (int $step) => $first->addWeeks($weeks * $step));
    }

    private function timeOn(CarbonInterface $day, string $time): CarbonImmutable
    {
        [$hour, $minute] = array_map('intval', explode(':', $time) + [0, 0]);

        return CarbonImmutable::parse($day->toDateString())->setTime($hour, $minute);
    }

    private function floorToSlot(CarbonImmutable $time): CarbonImmutable
    {
        $slot = $this->slotMinutes();
        $minutes = intdiv($time->hour * 60 + $time->minute, $slot) * $slot;

        return $time->startOfDay()->addMinutes($minutes);
    }
}
