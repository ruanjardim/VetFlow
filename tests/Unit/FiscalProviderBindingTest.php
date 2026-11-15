<?php

namespace Tests\Unit;

use App\Modules\Fiscal\Contracts\FiscalCredentialResolver;
use App\Modules\Fiscal\Contracts\FiscalProvider;
use App\Modules\Fiscal\Services\DisabledFiscalProvider;
use App\Modules\Fiscal\Services\EncryptedDatabaseFiscalCredentialResolver;
use App\Modules\Fiscal\Services\ExternalFileFiscalCredentialResolver;
use App\Modules\Fiscal\Services\FocusNfeFiscalProvider;
use App\Modules\Fiscal\Services\UnavailableFiscalCredentialResolver;
use App\Providers\AppServiceProvider;
use Tests\TestCase;

class FiscalProviderBindingTest extends TestCase
{
    public function test_default_runtime_resolves_only_disabled_fiscal_services(): void
    {
        $this->registerBindings('disabled', 'disabled');

        $this->assertInstanceOf(DisabledFiscalProvider::class, app(FiscalProvider::class));
        $this->assertInstanceOf(UnavailableFiscalCredentialResolver::class, app(FiscalCredentialResolver::class));
    }

    public function test_both_explicit_gates_are_required_for_the_focus_binding(): void
    {
        $this->registerBindings('focus_nfe', 'disabled');
        $this->assertInstanceOf(DisabledFiscalProvider::class, app(FiscalProvider::class));

        $this->registerBindings('focus_nfe', 'external_file');
        $this->assertInstanceOf(FocusNfeFiscalProvider::class, app(FiscalProvider::class));
        $this->assertInstanceOf(ExternalFileFiscalCredentialResolver::class, app(FiscalCredentialResolver::class));

        $this->registerBindings('focus_nfe', 'encrypted_database');
        $this->assertInstanceOf(FocusNfeFiscalProvider::class, app(FiscalProvider::class));
        $this->assertInstanceOf(EncryptedDatabaseFiscalCredentialResolver::class, app(FiscalCredentialResolver::class));
    }

    private function registerBindings(string $adapter, string $credentialDriver): void
    {
        config()->set('fiscal.provider_adapter', $adapter);
        config()->set('fiscal.credentials.driver', $credentialDriver);
        (new AppServiceProvider($this->app))->register();
    }
}
