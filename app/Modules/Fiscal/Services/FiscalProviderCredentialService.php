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
    public function __construct(private readonly AuditTrailService $audit) {}

    public function customerManagedMode(): bool
    {
        return config('fiscal.credentials.driver') === 'encrypted_database';
    }

    public function activeForClinic(int $clinicId): ?FiscalProviderCredential
    {
        return FiscalProviderCredential::query()
            ->with('configuredBy')
            ->where('clinic_id', $clinicId)
            ->where('provider', FiscalProviderCatalog::FOCUS_NFE)
            ->where('environment', 'homologation')
            ->whereNull('revoked_at')
            ->latest('id')
            ->first();
    }

    public function connect(int $clinicId, #[\SensitiveParameter] string $token, User $actor): FiscalProviderCredential
    {
        $this->assertCustomerManagedMode();
        $this->assertToken($token);

        return DB::transaction(function () use ($clinicId, $token, $actor): FiscalProviderCredential {
            $clinic = Clinic::query()->whereKey($clinicId)->lockForUpdate()->firstOrFail();
            $profile = FiscalProfile::query()->where('clinic_id', $clinicId)->lockForUpdate()->first();
            $profileCreated = false;

            if (! $profile) {
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

            $credential = FiscalProviderCredential::query()
                ->where('clinic_id', $clinicId)
                ->where('provider', FiscalProviderCatalog::FOCUS_NFE)
                ->where('environment', 'homologation')
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
                    'environment' => 'homologation',
                    'reference' => 'focus-db://homologation/'.$ulid,
                ]);
            }

            $credential->fill([
                'configured_by_user_id' => $actor->id,
                'token' => $token,
                'token_last_four' => substr($token, -4),
                'rotated_at' => now(),
                'revoked_at' => null,
            ])->save();

            $metadata = is_array($profile->metadata) ? $profile->metadata : [];
            $referenceChanged = data_get($metadata, 'provider_credential_reference') !== $credential->reference
                || $profile->provider !== FiscalProviderCatalog::FOCUS_NFE;
            $metadata['provider_credential_reference'] = $credential->reference;
            $profile->forceFill([
                'provider' => FiscalProviderCatalog::FOCUS_NFE,
                'environment' => 'homologation',
                'metadata' => $metadata,
                'configuration_version' => $referenceChanged && ! $profileCreated
                    ? max(1, (int) $profile->configuration_version + 1)
                    : max(1, (int) $profile->configuration_version),
            ])->save();

            $this->audit->record(
                $event,
                $credential,
                ['connected' => $event === 'fiscal.provider-credential.rotated'],
                ['connected' => true],
                $actor,
                ['provider' => FiscalProviderCatalog::FOCUS_NFE, 'environment' => 'homologation'],
                $clinicId,
                'Conta Focus NFe da clínica'
            );

            return $credential->fresh(['configuredBy']);
        });
    }

    public function disconnect(int $clinicId, User $actor): void
    {
        $this->assertCustomerManagedMode();

        DB::transaction(function () use ($clinicId, $actor): void {
            Clinic::query()->whereKey($clinicId)->lockForUpdate()->firstOrFail();
            $profile = FiscalProfile::query()->where('clinic_id', $clinicId)->lockForUpdate()->first();
            $credential = FiscalProviderCredential::query()
                ->where('clinic_id', $clinicId)
                ->where('provider', FiscalProviderCatalog::FOCUS_NFE)
                ->where('environment', 'homologation')
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
                if (data_get($metadata, 'provider_credential_reference') === $credential->reference) {
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
                ['provider' => FiscalProviderCatalog::FOCUS_NFE, 'environment' => 'homologation'],
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

    private function assertToken(string $token): void
    {
        if (strlen($token) < 16
            || strlen($token) > 512
            || trim($token) !== $token
            || preg_match('/[\x00-\x1F\x7F]/', $token)) {
            throw ValidationException::withMessages([
                'focus_token' => 'Informe um token de homologação válido, sem espaços ou caracteres de controle.',
            ]);
        }
    }
}
