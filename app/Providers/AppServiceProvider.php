<?php

namespace App\Providers;

use App\Models\User;
use App\Modules\Clinics\Contracts\ClinicRepositoryInterface;
use App\Modules\Clinics\Repositories\ClinicRepository;
use App\Modules\Clinics\Services\ClinicBrandingService;
use App\Modules\Fiscal\Contracts\FiscalCredentialResolver;
use App\Modules\Fiscal\Contracts\FiscalProvider;
use App\Modules\Fiscal\Services\DisabledFiscalProvider;
use App\Modules\Fiscal\Services\EncryptedDatabaseFiscalCredentialResolver;
use App\Modules\Fiscal\Services\ExternalFileFiscalCredentialResolver;
use App\Modules\Fiscal\Services\FocusNfeFiscalProvider;
use App\Modules\Fiscal\Services\UnavailableFiscalCredentialResolver;
use App\Modules\Saas\Services\SubscriptionFeatureService;
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
        $this->app->scoped(
            SubscriptionFeatureService::class,
            fn (): SubscriptionFeatureService => new SubscriptionFeatureService
        );

        $this->app->bind(
            ClinicRepositoryInterface::class,
            ClinicRepository::class
        );

        $this->app->bind(
            TutorRepositoryInterface::class,
            TutorRepository::class
        );

        $focusAdapterEnabled = config('fiscal.provider_adapter') === 'focus_nfe';
        $credentialDriver = (string) config('fiscal.credentials.driver');
        $credentialResolver = match ($credentialDriver) {
            'external_file' => ExternalFileFiscalCredentialResolver::class,
            'encrypted_database' => EncryptedDatabaseFiscalCredentialResolver::class,
            default => UnavailableFiscalCredentialResolver::class,
        };
        $credentialDriverEnabled = in_array($credentialDriver, ['external_file', 'encrypted_database'], true);

        // Profile selection never changes these bindings. A deployment must
        // independently opt into both the adapter and credential driver.
        $this->app->bind(
            FiscalCredentialResolver::class,
            $credentialResolver
        );
        $this->app->bind(
            FiscalProvider::class,
            $focusAdapterEnabled && $credentialDriverEnabled
                ? FocusNfeFiscalProvider::class
                : DisabledFiscalProvider::class
        );
    }

    public function boot(): void
    {
        foreach (PermissionCatalog::slugs() as $permission) {
            Gate::define($permission, fn (User $user): bool => $user->hasPermission($permission)
                && app(SubscriptionFeatureService::class)->allowsPermission($user, $permission));
        }

        View::composer('layouts.admin', function ($view): void {
            $view->with(
                'brandIconKey',
                app(ClinicBrandingService::class)->resolveForUser(Auth::user())
            );
        });
    }
}
