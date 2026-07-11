<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Modules\Clinics\Contracts\ClinicRepositoryInterface;
use App\Modules\Clinics\Repositories\ClinicRepository;
use App\Modules\Tutors\Contracts\TutorRepositoryInterface;
use App\Modules\Tutors\Repositories\TutorRepository;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            ClinicRepositoryInterface::class,
            ClinicRepository::class
        );

        $this->app->bind(
            TutorRepositoryInterface::class,
            TutorRepository::class
        );
    }

    public function boot(): void
    {
        //
    }
}