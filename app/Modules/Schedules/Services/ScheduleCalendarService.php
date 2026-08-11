<?php

namespace App\Modules\Schedules\Services;

use App\Modules\Appointments\Models\Appointment;
use App\Modules\Schedules\Models\Schedule;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class ScheduleCalendarService
{
    /**
     * @return array<string, mixed>
     */
    public function calendarData(?string $requestedDate, ?string $requestedView): array
    {
        $view = in_array($requestedView, ['day', 'week', 'month'], true) ? $requestedView : 'week';
        $anchor = $this->anchorDate($requestedDate);
        [$start, $end] = $this->rangeFor($anchor, $view);
        $eventsByDate = $this->eventsFor($start, $end)
            ->sortBy(['starts_at', 'title'])
            ->groupBy('date');

        return [
            'calendarView' => $view,
            'anchorDate' => $anchor,
            'periodStart' => $start,
            'periodEnd' => $end,
            'calendarDays' => collect(CarbonPeriod::create($start, $end))
                ->map(fn (Carbon $day) => $day->copy()),
            'eventsByDate' => $eventsByDate,
            'previousDate' => $this->shiftAnchor($anchor, $view, -1),
            'nextDate' => $this->shiftAnchor($anchor, $view, 1),
            'todayDate' => today()->toDateString(),
        ];
    }

    private function anchorDate(?string $requestedDate): Carbon
    {
        if (! $requestedDate) {
            return today();
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $requestedDate)->startOfDay();
        } catch (\Throwable) {
            return today();
        }
    }

    /**
     * @return array{Carbon, Carbon}
     */
    private function rangeFor(Carbon $anchor, string $view): array
    {
        return match ($view) {
            'day' => [$anchor->copy()->startOfDay(), $anchor->copy()->endOfDay()],
            'month' => [
                $anchor->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY),
                $anchor->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY),
            ],
            default => [
                $anchor->copy()->startOfWeek(Carbon::MONDAY),
                $anchor->copy()->endOfWeek(Carbon::SUNDAY),
            ],
        };
    }

    private function shiftAnchor(Carbon $anchor, string $view, int $direction): string
    {
        return match ($view) {
            'day' => $anchor->copy()->addDays($direction)->toDateString(),
            'month' => $anchor->copy()->addMonthsNoOverflow($direction)->toDateString(),
            default => $anchor->copy()->addWeeks($direction)->toDateString(),
        };
    }

    private function eventsFor(Carbon $start, Carbon $end): Collection
    {
        $scheduleEvents = Schedule::query()
            ->with(['patient', 'tutor'])
            ->whereNotNull('scheduled_date')
            ->whereBetween('scheduled_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->map(fn (Schedule $schedule) => [
                'date' => $schedule->scheduled_date->toDateString(),
                'time' => $schedule->scheduled_time ? substr((string) $schedule->scheduled_time, 0, 5) : null,
                'starts_at' => $schedule->scheduled_date->toDateString().' '.($schedule->scheduled_time ?: '00:00:00'),
                'title' => $schedule->title ?: 'Agendamento',
                'patient' => $schedule->patient?->name,
                'tutor' => $schedule->tutor?->name,
                'status' => $schedule->status,
                'kind' => 'schedule',
                'kind_label' => 'Agenda',
                'url' => route('schedules.edit', $schedule->id),
            ]);

        $appointmentEvents = Appointment::query()
            ->with(['patient', 'tutor'])
            ->whereBetween('scheduled_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->get()
            ->map(fn (Appointment $appointment) => [
                'date' => $appointment->scheduled_at->toDateString(),
                'time' => $appointment->scheduled_at->format('H:i'),
                'starts_at' => $appointment->scheduled_at->format('Y-m-d H:i:s'),
                'title' => $appointment->title,
                'patient' => $appointment->patient?->name,
                'tutor' => $appointment->tutor?->name,
                'status' => $appointment->status,
                'kind' => 'appointment',
                'kind_label' => 'Consulta',
                'url' => route('appointments.edit', $appointment->id),
            ]);

        return $scheduleEvents->concat($appointmentEvents);
    }
}
