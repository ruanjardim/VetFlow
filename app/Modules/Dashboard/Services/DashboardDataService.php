<?php

namespace App\Modules\Dashboard\Services;

class DashboardDataService
{
    public function __construct(
        private readonly DashboardStatsService $dashboardStatsService,
        private readonly DashboardAppointmentService $dashboardAppointmentService,
        private readonly DashboardPatientService $dashboardPatientService,
        private readonly DashboardLatestTutorService $dashboardLatestTutorService,
        private readonly DashboardActivityService $dashboardActivityService,
        private readonly DashboardAlertService $dashboardAlertService,
        private readonly DashboardWidgetRegistry $dashboardWidgetRegistry
    ) {
    }

    public function get(): array
    {
        $alertSummary = $this->dashboardAlertService->summary();

        return [
            'widgets'            => $this->dashboardWidgetRegistry->all(),
            'stats'              => $this->dashboardStatsService->getStats(),
            'nextAppointments'   => $this->dashboardAppointmentService->next(),
            'todayAppointments'  => $this->dashboardAppointmentService->todayList(),
            'latestPatients'     => $this->dashboardPatientService->latest(),
            'latestTutors'       => $this->dashboardLatestTutorService->getLatest(),
            'recentActivities'   => $this->dashboardActivityService->latest(),
            'alerts'             => $alertSummary['latest'],
            'alertSummary'       => $alertSummary,
        ];
    }
}
