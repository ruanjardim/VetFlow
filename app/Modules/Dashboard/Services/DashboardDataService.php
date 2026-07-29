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
        private readonly DashboardOperationalInsightService $dashboardOperationalInsightService,
        private readonly DashboardProductIntelligenceService $dashboardProductIntelligenceService,
        private readonly DashboardWidgetRegistry $dashboardWidgetRegistry
    ) {}

    public function get(): array
    {
        $stats = $this->dashboardStatsService->getStats();
        $alertSummary = $this->dashboardAlertService->summary();

        return [
            'widgets' => $this->dashboardWidgetRegistry->all(),
            'stats' => $stats,
            'operationalPriorities' => $this->dashboardOperationalInsightService->priorities($stats),
            'nextAppointments' => $this->dashboardAppointmentService->next(),
            'todayAppointments' => $this->dashboardAppointmentService->todayList(),
            'latestPatients' => $this->dashboardPatientService->latest(),
            'latestTutors' => $this->dashboardLatestTutorService->getLatest(),
            'recentActivities' => $this->dashboardActivityService->latest(),
            'alerts' => $alertSummary['latest'],
            'alertSummary' => $alertSummary,
            'productIntelligence' => $this->dashboardProductIntelligenceService->summary(),
        ];
    }
}
