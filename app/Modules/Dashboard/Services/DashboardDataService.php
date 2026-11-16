<?php

namespace App\Modules\Dashboard\Services;

use App\Models\User;

class DashboardDataService
{
    public function __construct(
        private readonly DashboardStatsService $dashboardStatsService,
        private readonly DashboardAppointmentService $dashboardAppointmentService,
        private readonly DashboardPatientService $dashboardPatientService,
        private readonly DashboardLatestTutorService $dashboardLatestTutorService,
        private readonly DashboardAlertService $dashboardAlertService,
        private readonly DashboardOperationalInsightService $dashboardOperationalInsightService,
        private readonly DashboardProductIntelligenceService $dashboardProductIntelligenceService,
        private readonly DashboardWidgetRegistry $dashboardWidgetRegistry
    ) {}

    public function get(): array
    {
        $capabilities = $this->capabilities(auth()->user());
        $stats = $this->dashboardStatsService->getStats($capabilities);
        $alertSummary = $this->dashboardAlertService->summary(
            $capabilities['inventory'],
            $capabilities['financial']
        );

        return [
            'widgets' => $this->dashboardWidgetRegistry->all(),
            'dashboardCapabilities' => $capabilities,
            'stats' => $stats,
            'operationalPriorities' => $this->dashboardOperationalInsightService->priorities($stats, $capabilities),
            'nextAppointments' => $capabilities['appointments'] ? $this->dashboardAppointmentService->next() : collect(),
            'todayAppointments' => $capabilities['appointments'] ? $this->dashboardAppointmentService->todayList() : collect(),
            'latestPatients' => $capabilities['patients'] ? $this->dashboardPatientService->latest() : collect(),
            'latestTutors' => $capabilities['tutors'] ? $this->dashboardLatestTutorService->getLatest() : collect(),
            'alerts' => $alertSummary['latest'],
            'alertSummary' => $alertSummary,
            'productIntelligence' => $capabilities['productIntelligence']
                ? $this->dashboardProductIntelligenceService->summary()
                : [],
        ];
    }

    /**
     * Keep every dashboard query aligned with the permission that protects its
     * destination route. This prevents restricted values from reaching the
     * rendered view even when the dashboard itself is accessible.
     *
     * @return array<string, bool>
     */
    private function capabilities(?User $user): array
    {
        $allows = fn (string $permission): bool => (bool) $user?->can($permission);
        $products = $allows('products.manage');
        $globalProducts = $allows('global-products.manage');

        return [
            'appointments' => $allows('appointments.manage'),
            'schedules' => $allows('schedules.manage'),
            'serviceOrders' => $allows('service-orders.manage'),
            'sales' => $allows('sales.manage'),
            'financial' => $allows('financial.manage'),
            'inventory' => $allows('inventory.manage'),
            'products' => $products,
            'globalProducts' => $globalProducts,
            'productIntelligence' => $products && $globalProducts,
            'patients' => $allows('patients.manage'),
            'tutors' => $allows('tutors.manage'),
            'petshopServices' => $allows('petshop-services.manage'),
            'clinics' => $allows('clinics.manage'),
        ];
    }
}
