<?php

namespace App\Modules\Patients\Providers;

use Illuminate\Support\ServiceProvider;
use App\Modules\Patients\Contracts\PatientRepositoryInterface;
use App\Modules\Patients\Repositories\PatientRepository;

class PatientServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            PatientRepositoryInterface::class,
            PatientRepository::class
        );
    }

    public function boot(): void
    {
        //
    }
}
