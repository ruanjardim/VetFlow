<?php

namespace App\Modules\Fiscal\Services;

use App\Models\User;
use App\Modules\Audit\Services\AuditTrailService;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Fiscal\Models\FiscalProductionActivation;
use App\Modules\Fiscal\Models\FiscalProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FiscalProductionActivationService
{
    public function __construct(
        private readonly FiscalEnvironmentGuard $environment,
        private readonly FiscalHomologationReadinessService $homologation,
        private readonly FiscalProviderCredentialService $credentials,
        private readonly FiscalDpsSequenceService $dpsSequences,
        private readonly FiscalNfceSequenceService $nfceSequences,
        private readonly AuditTrailService $audit,
    ) {}

    public function activeForClinic(int $clinicId): ?FiscalProductionActivation
    {
        return $this->environment->activeProductionForClinic($clinicId)?->loadMissing([
            'activatedBy', 'homologationReview', 'revokedBy',
        ]);
    }

    /** @param array<string, mixed> $data */
    public function activate(Clinic $clinic, FiscalProfile $profile, User $actor, array $data): FiscalProductionActivation
    {
        if (! $this->environment->productionRuntimeEnabled()) {
            throw ValidationException::withMessages([
                'production' => 'A chave independente de produção fiscal está desligada neste servidor.',
            ]);
        }

        if (! $profile->exists || $profile->environment !== FiscalEnvironmentGuard::HOMOLOGATION) {
            throw ValidationException::withMessages([
                'production' => 'A ativação deve partir de um perfil de homologação concluído.',
            ]);
        }

        $evaluation = $this->homologation->evaluate($clinic, $profile);
        if (! $evaluation['eligible_for_production_review']) {
            throw ValidationException::withMessages([
                'production' => 'Conclua as evidências e a aprovação atual da homologação antes de ativar produção.',
            ]);
        }

        $credential = $this->credentials->activeForClinic((int) $clinic->id, FiscalEnvironmentGuard::PRODUCTION);
        if (! $credential) {
            throw ValidationException::withMessages([
                'production' => 'Conecte primeiro o token de produção da conta Focus desta clínica.',
            ]);
        }

        $this->validateProductionFields($profile, $data);

        return DB::transaction(function () use ($clinic, $profile, $actor, $data, $evaluation, $credential): FiscalProductionActivation {
            $locked = FiscalProfile::query()->whereKey($profile->id)->lockForUpdate()->firstOrFail();
            if ($locked->environment !== FiscalEnvironmentGuard::HOMOLOGATION
                || $this->environment->activeProductionForClinic((int) $clinic->id)) {
                throw ValidationException::withMessages(['production' => 'Esta clínica já possui uma ativação de produção vigente.']);
            }

            $activationUlid = (string) Str::ulid();
            $homologationSnapshot = [
                'environment' => $locked->environment,
                'provider' => $locked->provider,
                'metadata' => $locked->metadata,
                'configuration_version' => (int) $locked->configuration_version,
            ];
            $metadata = is_array($locked->metadata) ? $locked->metadata : [];
            $metadata['provider_credential_reference'] = $credential->reference;
            $metadata['focus_nfe']['dps_series'] = $locked->nfse_enabled ? (string) $data['nfse_dps_series'] : null;
            if ($locked->nfce_enabled) {
                $metadata['focus_nfce'] = array_replace($metadata['focus_nfce'] ?? [], [
                    'series' => (int) $data['nfce_series'],
                    'csc_id' => preg_replace('/\D+/', '', (string) $data['nfce_csc_id']),
                    'csc_reference' => trim((string) $data['nfce_csc_reference']),
                    'sefaz_credentialed' => true,
                ]);
            }
            $nextVersion = max(1, (int) $locked->configuration_version + 1);
            $metadata['production'] = [
                'activation_ulid' => $activationUlid,
                'evidence_fingerprint' => $evaluation['evidence_fingerprint'],
                'homologation_review_ulid' => $evaluation['latest_review']?->ulid,
                'activated_at' => now()->toIso8601String(),
            ];

            $locked->forceFill([
                'environment' => FiscalEnvironmentGuard::PRODUCTION,
                'metadata' => $metadata,
                'configuration_version' => $nextVersion,
            ])->save();

            $sequenceData = [
                'nfse_dps_series' => $data['nfse_dps_series'] ?? null,
                'nfse_dps_next_number' => $data['nfse_dps_next_number'] ?? null,
                'nfce_series' => $data['nfce_series'] ?? null,
                'nfce_next_number' => $data['nfce_next_number'] ?? null,
            ];
            $this->dpsSequences->configure($locked, $sequenceData);
            $this->nfceSequences->configure($locked, $sequenceData);

            $activation = FiscalProductionActivation::query()->create([
                'ulid' => $activationUlid,
                'clinic_id' => $clinic->id,
                'activated_by_user_id' => $actor->id,
                'homologation_review_id' => $evaluation['latest_review']?->id,
                'profile_version' => $nextVersion,
                'evidence_fingerprint' => $evaluation['evidence_fingerprint'],
                'homologation_profile_snapshot' => $homologationSnapshot,
                'reviewer_name' => trim((string) $data['reviewer_name']),
                'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
                'activated_at' => now(),
            ]);

            $this->audit->record(
                'fiscal.production.activated',
                $activation,
                [],
                ['environment' => FiscalEnvironmentGuard::PRODUCTION, 'profile_version' => $nextVersion],
                $actor,
                ['homologation_review_ulid' => $evaluation['latest_review']?->ulid],
                (int) $clinic->id,
                'Ativação fiscal de produção'
            );

            return $activation->fresh(['activatedBy', 'homologationReview']);
        });
    }

    public function deactivate(Clinic $clinic, FiscalProfile $profile, User $actor, string $reason): FiscalProductionActivation
    {
        return DB::transaction(function () use ($clinic, $profile, $actor, $reason): FiscalProductionActivation {
            $locked = FiscalProfile::query()->whereKey($profile->id)->lockForUpdate()->firstOrFail();
            $activation = FiscalProductionActivation::query()
                ->where('clinic_id', $clinic->id)
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->latest('activated_at')
                ->first();

            if (! $activation || $locked->environment !== FiscalEnvironmentGuard::PRODUCTION) {
                throw ValidationException::withMessages(['production' => 'Nenhuma ativação de produção vigente foi localizada.']);
            }

            $snapshot = $activation->homologation_profile_snapshot;
            $locked->forceFill([
                'environment' => FiscalEnvironmentGuard::HOMOLOGATION,
                'provider' => data_get($snapshot, 'provider'),
                'metadata' => data_get($snapshot, 'metadata', []),
                'configuration_version' => max(1, (int) $locked->configuration_version + 1),
            ])->save();
            $activation->forceFill([
                'revoked_at' => now(),
                'revoked_by_user_id' => $actor->id,
                'revocation_reason' => trim($reason),
            ])->save();

            $this->audit->record(
                'fiscal.production.deactivated',
                $activation,
                ['active' => true],
                ['active' => false],
                $actor,
                ['reason' => trim($reason)],
                (int) $clinic->id,
                'Ativação fiscal de produção'
            );

            return $activation->fresh(['activatedBy', 'revokedBy', 'homologationReview']);
        });
    }

    /** @param array<string, mixed> $data */
    private function validateProductionFields(FiscalProfile $profile, array $data): void
    {
        $errors = [];
        if ($profile->nfce_enabled) {
            foreach (['nfce_series', 'nfce_next_number', 'nfce_csc_id', 'nfce_csc_reference', 'nfce_sefaz_credentialed'] as $field) {
                if (! filled($data[$field] ?? null)) {
                    $errors[$field] = 'Informe este dado específico do ambiente de produção da NFC-e.';
                }
            }
        }
        if ($profile->nfse_enabled) {
            foreach (['nfse_dps_series', 'nfse_dps_next_number'] as $field) {
                if (! filled($data[$field] ?? null)) {
                    $errors[$field] = 'Informe este dado específico do ambiente de produção da NFS-e.';
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
