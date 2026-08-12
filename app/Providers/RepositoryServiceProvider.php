<?php

namespace App\Providers;

use App\Modules\Access\Contracts\AccessUserRepositoryInterface;
use App\Modules\Access\Repositories\AccessUserRepository;
use App\Modules\Appointments\Contracts\AppointmentRepositoryInterface;
use App\Modules\Appointments\Repositories\AppointmentRepository;
use App\Modules\Clinics\Contracts\ClinicRepositoryInterface;
use App\Modules\Clinics\Repositories\ClinicRepository;
use App\Modules\Commissions\Contracts\CommissionRuleRepositoryInterface;
use App\Modules\Commissions\Repositories\CommissionRuleRepository;
use App\Modules\Financial\Contracts\FinancialTransactionRepositoryInterface;
use App\Modules\Financial\Repositories\FinancialTransactionRepository;
use App\Modules\Inventory\Contracts\InventoryMovementRepositoryInterface;
use App\Modules\Inventory\Repositories\InventoryMovementRepository;
use App\Modules\MedicalRecords\Contracts\MedicalRecordRepositoryInterface;
use App\Modules\MedicalRecords\Repositories\MedicalRecordRepository;
use App\Modules\Patients\Contracts\PatientRepositoryInterface;
use App\Modules\Patients\Repositories\PatientRepository;
use App\Modules\PetShopServices\Contracts\PetShopServiceRepositoryInterface;
use App\Modules\PetShopServices\Repositories\PetShopServiceRepository;
use App\Modules\Products\Contracts\ProductRepositoryInterface;
use App\Modules\Products\Repositories\ProductRepository;
use App\Modules\Sales\Contracts\SaleRepositoryInterface;
use App\Modules\Sales\Repositories\SaleRepository;
use App\Modules\Schedules\Contracts\ScheduleRepositoryInterface;
use App\Modules\Schedules\Repositories\ScheduleRepository;
use App\Modules\ServiceOrders\Contracts\ServiceOrderRepositoryInterface;
use App\Modules\ServiceOrders\Repositories\ServiceOrderRepository;
use App\Modules\Suppliers\Contracts\SupplierRepositoryInterface;
use App\Modules\Suppliers\Repositories\SupplierRepository;
use App\Modules\Tutors\Contracts\TutorRepositoryInterface;
use App\Modules\Tutors\Repositories\TutorRepository;
use App\Modules\Vaccinations\Contracts\VaccinationRepositoryInterface;
use App\Modules\Vaccinations\Repositories\VaccinationRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            AccessUserRepositoryInterface::class,
            AccessUserRepository::class
        );

        $this->app->bind(
            AppointmentRepositoryInterface::class,
            AppointmentRepository::class
        );

        $this->app->bind(
            ClinicRepositoryInterface::class,
            ClinicRepository::class
        );

        $this->app->bind(
            CommissionRuleRepositoryInterface::class,
            CommissionRuleRepository::class
        );

        $this->app->bind(
            FinancialTransactionRepositoryInterface::class,
            FinancialTransactionRepository::class
        );

        $this->app->bind(
            InventoryMovementRepositoryInterface::class,
            InventoryMovementRepository::class
        );

        $this->app->bind(
            MedicalRecordRepositoryInterface::class,
            MedicalRecordRepository::class
        );

        $this->app->bind(
            PatientRepositoryInterface::class,
            PatientRepository::class
        );

        $this->app->bind(
            PetShopServiceRepositoryInterface::class,
            PetShopServiceRepository::class
        );

        $this->app->bind(
            ProductRepositoryInterface::class,
            ProductRepository::class
        );

        $this->app->bind(
            SaleRepositoryInterface::class,
            SaleRepository::class
        );

        $this->app->bind(
            ServiceOrderRepositoryInterface::class,
            ServiceOrderRepository::class
        );

        $this->app->bind(
            SupplierRepositoryInterface::class,
            SupplierRepository::class
        );

        $this->app->bind(
            TutorRepositoryInterface::class,
            TutorRepository::class
        );

        $this->app->bind(
            VaccinationRepositoryInterface::class,
            VaccinationRepository::class
        );

        $this->app->bind(
            ScheduleRepositoryInterface::class,
            ScheduleRepository::class
        );
    }

    public function boot(): void
    {
        //
    }
}
