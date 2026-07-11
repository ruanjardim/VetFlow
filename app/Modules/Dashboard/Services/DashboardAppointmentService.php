<?php

namespace App\Modules\Dashboard\Services;

use App\Modules\Appointments\Models\Appointment;

class DashboardAppointmentService
{
    public function total(): int
    {
        return Appointment::count();
    }

    public function today(): int
    {
        return Appointment::whereDate(
            'scheduled_at',
            today()
        )->count();
    }

    public function week(): int
    {
        return Appointment::whereBetween(
            'scheduled_at',
            [
                now()->startOfWeek(),
                now()->endOfWeek(),
            ]
        )->count();
    }

    public function scheduled(): int
    {
        return Appointment::where('status', 'scheduled')->count();
    }

    public function completed(): int
    {
        return Appointment::where('status', 'completed')->count();
    }

    public function cancelled(): int
    {
        return Appointment::where('status', 'cancelled')->count();
    }

    public function next(int $limit = 5)
    {
        return Appointment::where('scheduled_at', '>=', now())
            ->orderBy('scheduled_at')
            ->limit($limit)
            ->get();
    }

    public function todayList(int $limit = 5)
    {
        return Appointment::whereDate('scheduled_at', today())
            ->orderBy('scheduled_at')
            ->limit($limit)
            ->get();
    }
}
