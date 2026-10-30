<?php

namespace App\Modules\Fiscal\Services;

use App\Models\User;
use App\Modules\Audit\Services\AuditTrailService;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Fiscal\Models\FiscalProfile;
use App\Modules\Fiscal\Models\FiscalProviderCredential;
use App\Modules\Fiscal\Support\FiscalMunicipalityCatalog;
use App\Modules\Fiscal\Support\FiscalProviderCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FiscalProviderCredentialService
{
    public function __construct(
        private readonly AuditTrailService $audit,
        private readonly FiscalHomologationReadinessService $homologationReadiness,
        private readonly FiscalEnvironmentGuard $environment,
    ) {}

    public function customerManagedMode(): bool
    {
        return config('fiscal.credentials.driver') === 'encrypted_database';
    }

    public function activeForClinic(int $clinicId, string $environment = FiscalEnvironmentGuard::HOMOLOGATION): ?FiscalProviderCredential
    {
        return FiscalProviderCredential::query()
            ->with('configuredBy')
            ->where('clinic_id', $clinicId)
            ->where('provider', FiscalProviderCatalog::FOCUS_NFE)
            ->where('environment', $environment)
            ->whereNull('revoked_at')
            ->latest('id')
            ->first();
    }

    public function connect(
        int $clinicId,
        #[\SensitiveParameter] string $token,
        User $actor,
        string $environment = FiscalEnvironmentGuard::HOMOLOGATION
    ): FiscalProviderCredential {
        $this->assertCustomerManagedMode();
        $this->assertEnvironment($environment);
        $this->assertToken($token, $environment);

        return DB::transaction(function () use ($clinicId, $token, $actor, $environment): FiscalProviderCredential {
            $clinic = Clinic::query()->whereKey($clinicId)->lockForUpdate()->firstOrFail();
            $profile = FiscalProfile::query()->where('clinic_id', $clinicId)->lockForUpdate()->first();
            $profileCreated = false;

            if (! $profile) {
                if ($environment === FiscalEnvironmentGuard::PRODUCTION) {
                    throw ValidationException::withMessages([
                        'focus_token' => 'Crie e homologue o perfil fiscal antes de conectar uma credencial de produção.',
                    ]);
                }
                $profile = FiscalProfile::query()->create([
                    'clinic_id' => $clinicId,
                    'enabled' => false,
                    'environment' => 'homologation',
                    'provider' => FiscalProviderCatalog::FOCUS_NFE,
                    'nfce_enabled' => false,
                    'nfse_enabled' => false,
                    'municipality_code' => FiscalMunicipalityCatalog::inferFromClinic($clinic),
                    'configuration_version' => 1,
                ]);
                $profileCreated = true;
            }

            if ($environment === FiscalEnvironmentGuard::PRODUCTION) {
                if (! $this->environment->productionRuntimeEnabled()) {
                    throw ValidationException::withMessages([
                        'focus_token' => 'A preparação de credenciais de produção está bloqueada neste servidor.',
                    ]);
                }

                $evaluation = $this->homologationReadiness->evaluate($clinic, $profile);
                if (! $evaluation['eligible_for_production_review']) {
                    throw ValidationException::withMessages([
                        'focus_token' => 'A credencial de produção só pode ser conectada depois da homologação aprovada e atual.',
                    ]);
                }
            }

            $credential = FiscalProviderCredential::query()
                ->where('clinic_id', $clinicId)
                ->where('provider', FiscalProviderCatalog::FOCUS_NFE)
                ->where('environment', $environment)
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->latest('id')
                ->first();
            $event = $credential ? 'fiscal.provider-credential.rotated' : 'fiscal.provider-credential.connected';

            if (! $credential) {
                $ulid = (string) Str::ulid();
                $credential = new FiscalProviderCredential([
                    'clinic_id' => $clinicId,
                    'ulid' => $ulid,
                    'provider' => FiscalProviderCatalog::FOCUS_NFE,
                    'environment' => $environment,
                    'reference' => 'focus-db://'.$environment.'/'.$ulid,
                ]);
            }

            $credential->fill([
                'configured_by_user_id' => $actor->id,
                'token' => $token,
                'token_last_four' => substr($token, -4),
                'rotated_at' => now(),
                'revoked_at' => null,
            ])->save();

            if ($environment === FiscalEnvironmentGuard::HOMOLOGATION) {
                $metadata = is_array($profile->metadata) ? $profile->metadata : [];
                $referenceChanged = data_get($metadata, 'provider_credential_reference') !== $credential->reference
                    || $profile->provider !== FiscalProviderCatalog::FOCUS_NFE;
                $metadata['provider_credential_reference'] = $credential->reference;
                $profile->forceFill([
                    'provider' => FiscalProviderCatalog::FOCUS_NFE,
                    'environment' => FiscalEnvironmentGuard::HOMOLOGATION,
                    'metadata' => $metadata,
                    'configuration_version' => $referenceChanged && ! $profileCreated
                        ? max(1, (int) $profile->configuration_version + 1)
                        : max(1, (int) $profile->configuration_version),
                ])->save();
            }

            $this->audit->record(
                $event,
                $credential,
                ['connected' => $event === 'fiscal.provider-credential.rotated'],
                ['connected' => true],
                $actor,
                ['provider' => FiscalProviderCatalog::FOCUS_NFE, 'environment' => $environment],
                $clinicId,
                'Conta Focus NFe da clínica'
            );

            return $credential->fresh(['configuredBy']);
        });
    }

    public function disconnect(
        int $clinicId,
        User $actor,
        string $environment = FiscalEnvironmentGuard::HOMOLOGATION
    ): void {
        $this->assertCustomerManagedMode();
        $this->assertEnvironment($environment);

        DB::transaction(function () use ($clinicId, $actor, $environment): void {
            Clinic::query()->whereKey($clinicId)->lockForUpdate()->firstOrFail();
            $profile = FiscalProfile::query()->where('clinic_id', $clinicId)->lockForUpdate()->first();
            $credential = FiscalProviderCredential::query()
                ->where('clinic_id', $clinicId)
                ->where('provider', FiscalProviderCatalog::FOCUS_NFE)
                ->where('environment', $environment)
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if (! $credential) {
                throw ValidationException::withMessages([
                    'focus_token' => 'Nenhuma conta Focus está conectada a esta clínica.',
                ]);
            }

            $credential->forceFill([
                'configured_by_user_id' => $actor->id,
                'token' => null,
                'token_last_four' => null,
                'revoked_at' => now(),
            ])->save();

            if ($profile) {
                $metadata = is_array($profile->metadata) ? $profile->metadata : [];
                if ($profile->environment === $environment
                    && data_get($metadata, 'provider_credential_reference') === $credential->reference) {
                    $metadata['provider_credential_reference'] = null;
                    $profile->forceFill([
                        'metadata' => $metadata,
                        'configuration_version' => max(1, (int) $profile->configuration_version + 1),
                    ])->save();
                }
            }

            $this->audit->record(
                'fiscal.provider-credential.disconnected',
                $credential,
                ['connected' => true],
                ['connected' => false],
                $actor,
                ['provider' => FiscalProviderCatalog::FOCUS_NFE, 'environment' => $environment],
                $clinicId,
                'Conta Focus NFe da clínica'
            );
        });
    }

    private function assertCustomerManagedMode(): void
    {
        if (! $this->customerManagedMode()) {
            throw ValidationException::withMessages([
                'focus_token' => 'A conexão de contas próprias da Focus não está habilitada neste ambiente.',
            ]);
        }
    }

    private function assertEnvironment(string $environment): void
    {
        if (! in_array($environment, [FiscalEnvironmentGuard::HOMOLOGATION, FiscalEnvironmentGuard::PRODUCTION], true)) {
            throw ValidationException::withMessages(['environment' => 'Ambiente fiscal inválido.']);
        }
    }

    private function assertToken(string $token, string $environment): void
    {
        if (strlen($token) < 16
            || strlen($token) > 512
            || trim($token) !== $token
            || preg_match('/[\x00-\x1F\x7F]/', $token)) {
            throw ValidationException::withMessages([
                'focus_token' => "Informe um token de {$environment} válido, sem espaços ou caracteres de controle.",
            ]);
        }
    }
}
