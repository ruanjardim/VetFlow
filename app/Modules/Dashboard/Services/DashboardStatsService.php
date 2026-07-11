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
    ) {
    }

    public function getStats(): array
    {
        return [
            'patients' => $this->patientService->total(),
            'tutors' => $this->tutorService->total(),
            'clinics' => Clinic::count(),

            'appointments' => $this->appointmentService->total(),
            'appointments_today' => $this->appointmentService->today(),
            'appointments_week' => $this->appointmentService->week(),
            'appointments_scheduled' => $this->appointmentService->scheduled(),
            'appointments_completed' => $this->appointmentService->completed(),
            'appointments_cancelled' => $this->appointmentService->cancelled(),

            'financial' => $this->financialService->paidIncomeTotal(),
            'financial_month' => $this->financialService->monthIncome(),
            'financial_pending' => $this->financialService->pendingIncome(),
            'financial_overdue' => $this->financialService->overdueIncome(),
            'expenses_pending' => $this->financialService->pendingExpense(),
            'expenses_overdue' => $this->financialService->overdueExpense(),
            'expenses_month' => $this->financialService->monthExpense(),

            'products' => $this->inventoryService->products(),
            'low_stock' => $this->inventoryService->lowStock(),
            'stock_value' => $this->inventoryService->stockValue(),
            'petshop_services' => $this->petShopServiceService->total(),
            'petshop_services_active' => $this->petShopServiceService->active(),
            'service_orders_open' => $this->serviceOrderService->open(),
            'service_orders_in_service' => $this->serviceOrderService->inService(),
            'service_orders_waiting_pickup' => $this->serviceOrderService->waitingPickup(),
            'service_orders_day_total' => $this->serviceOrderService->dayTotal(),
            'sales_today' => $this->salesService->todayTotal(),
            'sales_month' => $this->salesService->monthTotal(),
            'sales_drafts' => $this->salesService->drafts(),
            'sales_pending_payment' => $this->salesService->pendingPayment(),
        ];
    }
}
