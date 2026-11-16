<?php

namespace App\Modules\Dashboard\Services;

use App\Modules\Clinics\Models\Clinic;

class DashboardStatsService
{
    public function __construct(
        private readonly DashboardAppointmentService $appointmentService,
        private readonly DashboardFinancialService $financialService,
        private readonly DashboardInventoryService $inventoryService,
        private readonly DashboardPetShopServiceService $petShopServiceService,
        private readonly DashboardServiceOrderService $serviceOrderService,
        private readonly DashboardSalesService $salesService,
        private readonly DashboardPatientService $patientService,
        private readonly DashboardTutorService $tutorService
    ) {}

    /**
     * @param  array<string, bool>  $capabilities
     */
    public function getStats(array $capabilities): array
    {
        $stats = [];

        if ($capabilities['patients'] ?? false) {
            $stats['patients'] = $this->patientService->total();
        }

        if ($capabilities['tutors'] ?? false) {
            $stats['tutors'] = $this->tutorService->total();
        }

        if ($capabilities['clinics'] ?? false) {
            $stats['clinics'] = Clinic::count();
        }

        if ($capabilities['appointments'] ?? false) {
            $stats += [
                'appointments' => $this->appointmentService->total(),
                'appointments_today' => $this->appointmentService->today(),
                'appointments_week' => $this->appointmentService->week(),
                'appointments_scheduled' => $this->appointmentService->scheduled(),
                'appointments_completed' => $this->appointmentService->completed(),
                'appointments_cancelled' => $this->appointmentService->cancelled(),
            ];
        }

        if ($capabilities['financial'] ?? false) {
            $stats += [
                'financial' => $this->financialService->paidIncomeTotal(),
                'financial_month' => $this->financialService->monthIncome(),
                'financial_pending' => $this->financialService->pendingIncome(),
                'financial_overdue' => $this->financialService->overdueIncome(),
                'expenses_pending' => $this->financialService->pendingExpense(),
                'expenses_overdue' => $this->financialService->overdueExpense(),
                'expenses_month' => $this->financialService->monthExpense(),
            ];
        }

        if ($capabilities['inventory'] ?? false) {
            $stats += [
                'products' => $this->inventoryService->products(),
                'low_stock' => $this->inventoryService->lowStock(),
                'stock_value' => $this->inventoryService->stockValue(),
            ];
        }

        if ($capabilities['petshopServices'] ?? false) {
            $stats += [
                'petshop_services' => $this->petShopServiceService->total(),
                'petshop_services_active' => $this->petShopServiceService->active(),
            ];
        }

        if ($capabilities['serviceOrders'] ?? false) {
            $stats += [
                'service_orders_open' => $this->serviceOrderService->open(),
                'service_orders_in_service' => $this->serviceOrderService->inService(),
                'service_orders_waiting_pickup' => $this->serviceOrderService->waitingPickup(),
                'service_orders_day_total' => $this->serviceOrderService->dayTotal(),
            ];
        }

        if ($capabilities['sales'] ?? false) {
            $stats += [
                'sales_today' => $this->salesService->todayTotal(),
                'sales_month' => $this->salesService->monthTotal(),
                'sales_drafts' => $this->salesService->drafts(),
                'sales_pending_payment' => $this->salesService->pendingPayment(),
            ];
        }

        return $stats;
    }
}
