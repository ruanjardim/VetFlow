<?php

namespace App\Providers;

use App\Models\User;
use App\Modules\Clinics\Contracts\ClinicRepositoryInterface;
use App\Modules\Clinics\Repositories\ClinicRepository;
use App\Modules\Clinics\Services\ClinicBrandingService;
use App\Modules\Tutors\Contracts\TutorRepositoryInterface;
use App\Modules\Tutors\Repositories\TutorRepository;
use App\Support\Auth\PermissionCatalog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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

        View::composer('layouts.admin', function ($view): void {
            $view->with(
                'brandIconKey',
                app(ClinicBrandingService::class)->resolveForUser(Auth::user())
            );
        });
    }
}
