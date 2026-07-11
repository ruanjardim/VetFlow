<?php

namespace App\Modules\Clinics\Providers;

use Illuminate\Support\ServiceProvider;
use App\Modules\Clinics\Contracts\ClinicRepositoryInterface;
use App\Modules\Clinics\Repositories\ClinicRepository;

class ClinicServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            ClinicRepositoryInterface::class,
            ClinicRepository::class
        );
    }

    public function boot(): void
    {
        //
    }
}