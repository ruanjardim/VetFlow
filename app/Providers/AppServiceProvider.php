<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\ServiceProvider;
use App\Modules\Clinics\Contracts\ClinicRepositoryInterface;
use App\Modules\Clinics\Repositories\ClinicRepository;
use App\Modules\Tutors\Contracts\TutorRepositoryInterface;
use App\Modules\Tutors\Repositories\TutorRepository;
use App\Support\Auth\PermissionCatalog;
use Illuminate\Support\Facades\Gate;

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
        foreach (PermissionCatalog::slugs() as $permission) {
            Gate::define($permission, fn (User $user): bool => $user->hasPermission($permission));
        }
    }
}
