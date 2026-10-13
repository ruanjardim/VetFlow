<?php

namespace App\Modules\Fiscal\Services;

use App\Models\User;
use App\Modules\Audit\Services\AuditTrailService;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Fiscal\Models\FiscalDocument;
use App\Modules\Fiscal\Models\FiscalHomologationReview;
use App\Modules\Fiscal\Models\FiscalProfile;
use App\Modules\Fiscal\Models\FiscalSaleAllocation;
use App\Modules\Fiscal\Support\FiscalDocumentType;
use App\Modules\Fiscal\Support\FiscalProviderCatalog;
use App\Modules\PetShopServices\Models\PetShopService;
use App\Modules\Products\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FiscalHomologationReadinessService
{
    public const APPROVED = 'approved_for_production_review';

    public const FOLLOW_UP = 'follow_up_required';

    public const SCHEMA_VERSION = 1;

    public function __construct(
        private readonly FiscalRuntimeReadinessService $runtimeReadiness,
        private readonly FiscalMunicipalityReadinessService $municipalityReadiness,
        private readonly FiscalNfceReadinessService $nfceReadiness,
        private readonly FiscalNfceSequenceService $nfceSequences,
        private readonly AuditTrailService $audit,
    ) {}

    /** @return array<string, mixed> */
    public function evaluate(Clinic $clinic, FiscalProfile $profile): array
    {
        $runtime = $this->runtimeReadiness->evaluate($profile);
        $municipality = $this->municipalityReadiness->evaluate($clinic, $profile);
        $nfce = $this->nfceReadiness->evaluate($clinic, $profile, $this->nfceSequences->currentFor($profile));
        $productCatalog = $this->catalogEvidence($clinic->id, FiscalDocumentType::NFCE);
        $serviceCatalog = $this->catalogEvidence($clinic->id, FiscalDocumentType::NFSE);
        $nfceEvidence = $this->documentEvidence($clinic->id, FiscalDocumentType::NFCE);
        $nfseEvidence = $this->documentEvidence($clinic->id, FiscalDocumentType::NFSE);
        $checks = [
            $this->check(
                'profile',
                'Configuração fiscal ativa somente em homologação, com a Focus selecionada',
                $profile->exists
                    && $profile->enabled
                    && $profile->environment === 'homologation'
                    && $profile->provider === FiscalProviderCatalog::FOCUS_NFE
                    && ($profile->nfce_enabled || $profile->nfse_enabled),
                'Configuração'
            ),
            $this->check(
                'runtime',
                'Os sete controles operacionais do servidor estão prontos',
                $runtime['operational_ready'],
                'Configuração'
            ),
        ];

        if ($profile->nfce_enabled) {
            $checks = array_merge($checks, [
                $this->check('nfce_configuration', 'Configuração NFC-e RJ/modelo 65 completa', $nfce['configuration_ready'], 'NFC-e'),
                $this->check('nfce_catalog', 'Todos os produtos ativos possuem tributação revisada', $productCatalog['ready'], 'NFC-e', $this->catalogDetail($productCatalog)),
            ], $this->evidenceChecks(FiscalDocumentType::NFCE, $nfceEvidence));
        }

        if ($profile->nfse_enabled) {
            $checks = array_merge($checks, [
                $this->check('nfse_configuration', 'Configuração municipal da NFS-e completa', $municipality['configuration_ready'], 'NFS-e'),
                $this->check('nfse_catalog', 'Todos os serviços ativos possuem tributação revisada', $serviceCatalog['ready'], 'NFS-e', $this->catalogDetail($serviceCatalog)),
            ], $this->evidenceChecks(FiscalDocumentType::NFSE, $nfseEvidence), [
                $this->check('nfse_webhook', 'Retorno autenticado por webhook recebido em homologação', $nfseEvidence['webhook'], 'NFS-e'),
                $this->check('nfse_webhook_resend', 'Reenvio de webhook executado e confirmado em homologação', $nfseEvidence['webhook_resend'], 'NFS-e'),
            ]);
        }

        if ($profile->nfce_enabled && $profile->nfse_enabled) {
            $checks[] = $this->check(
                'mixed_sale_allocation',
                'Rateio de uma venda mista foi revisado e congelado',
                $this->hasMixedSaleAllocation($clinic->id),
                'Fluxo misto'
            );
        }

        $readyCount = collect($checks)->where('ready', true)->count();
        $technicalReady = $readyCount === count($checks);
        $snapshot = [
            'schema_version' => self::SCHEMA_VERSION,
            'profile' => [
                'exists' => $profile->exists,
                'configuration_version' => (int) ($profile->configuration_version ?: 1),
                'enabled' => (bool) $profile->enabled,
                'environment' => $profile->environment,
                'provider' => $profile->provider,
                'nfce_enabled' => (bool) $profile->nfce_enabled,
                'nfse_enabled' => (bool) $profile->nfse_enabled,
            ],
            'runtime' => collect($runtime['checks'])->mapWithKeys(fn (array $check): array => [$check['key'] => (bool) $check['ready']])->all(),
            'catalogs' => [
                'products' => $productCatalog,
                'services' => $serviceCatalog,
            ],
            'evidence' => [
                'nfce' => $nfceEvidence,
                'nfse' => $nfseEvidence,
            ],
            'checks' => collect($checks)->map(fn (array $check): array => [
                'key' => $check['key'],
                'ready' => $check['ready'],
            ])->values()->all(),
        ];
        $fingerprint = hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $reviews = FiscalHomologationReview::query()
            ->with('reviewer')
            ->where('clinic_id', $clinic->id)
            ->latest('occurred_at')
            ->latest('id')
            ->limit(10)
            ->get();
        $latestReview = $reviews->first();
        $latestReviewCurrent = $latestReview !== null
            && hash_equals($fingerprint, (string) $latestReview->evidence_fingerprint);
        $eligible = $technicalReady
            && $latestReviewCurrent
            && $latestReview->decision === self::APPROVED;

        return [
            'checks' => $checks,
            'ready_count' => $readyCount,
            'total_count' => count($checks),
            'technical_ready' => $technicalReady,
            'evidence_snapshot' => $snapshot,
            'evidence_fingerprint' => $fingerprint,
            'latest_review' => $latestReview,
            'latest_review_current' => $latestReviewCurrent,
            'eligible_for_production_review' => $eligible,
            'production_enabled' => false,
            'reviews' => $reviews,
            'runtime_readiness' => $runtime,
            'municipality_readiness' => $municipality,
            'nfce_readiness' => $nfce,
        ];
    }

    /** @param array<string, mixed> $data */
    public function record(Clinic $clinic, FiscalProfile $profile, User $actor, array $data): FiscalHomologationReview
    {
        $evaluation = $this->evaluate($clinic, $profile);

        if ($data['decision'] === self::APPROVED && ! $evaluation['technical_ready']) {
            throw ValidationException::withMessages([
                'decision' => 'A aprovação só pode ser registrada depois que todas as evidências técnicas estiverem prontas.',
            ]);
        }

        return DB::transaction(function () use ($clinic, $actor, $data, $evaluation): FiscalHomologationReview {
            $review = FiscalHomologationReview::query()->create([
                'clinic_id' => $clinic->id,
                'reviewed_by_user_id' => $actor->id,
                'decision' => $data['decision'],
                'reviewer_name' => trim($data['reviewer_name']),
                'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
                'schema_version' => self::SCHEMA_VERSION,
                'evidence_fingerprint' => $evaluation['evidence_fingerprint'],
                'evidence_snapshot' => $evaluation['evidence_snapshot'],
                'occurred_at' => now(),
            ]);

            $this->audit->record(
                'fiscal.homologation-review.recorded',
                $review,
                [],
                [
                    'decision' => $review->decision,
                    'reviewer_name' => $review->reviewer_name,
                    'schema_version' => $review->schema_version,
                ],
                $actor,
                clinicId: (int) $clinic->id,
                subjectLabel: 'Revisão da homologação fiscal'
            );

            return $review;
        });
    }

    /** @return array<string, mixed> */
    public function report(Clinic $clinic, FiscalProfile $profile): array
    {
        $evaluation = $this->evaluate($clinic, $profile);
        $latestReview = $evaluation['latest_review'];
        $report = [
            'schema_version' => 'fiscal-homologation-report-v1',
            'clinic' => [
                'id' => (int) $clinic->id,
                'name' => $clinic->trade_name ?: $clinic->corporate_name,
                'corporate_name' => $clinic->corporate_name,
                'cnpj' => $clinic->cnpj,
            ],
            'scope' => [
                'environment' => 'homologation',
                'provider' => $profile->provider,
                'profile_version' => (int) ($profile->configuration_version ?: 1),
                'nfce_enabled' => (bool) $profile->nfce_enabled,
                'nfse_enabled' => (bool) $profile->nfse_enabled,
            ],
            'status' => [
                'technical_ready' => $evaluation['technical_ready'],
                'eligible_for_production_review' => $evaluation['eligible_for_production_review'],
                'production_enabled' => false,
                'ready_count' => $evaluation['ready_count'],
                'total_count' => $evaluation['total_count'],
            ],
            'checks' => collect($evaluation['checks'])->map(fn (array $check): array => [
                'key' => $check['key'],
                'category' => $check['category'],
                'label' => $check['label'],
                'ready' => $check['ready'],
                'detail' => $check['detail'],
                'next_action' => $check['ready'] ? null : $check['guidance'],
            ])->values()->all(),
            'latest_review' => $latestReview ? [
                'decision' => $latestReview->decision,
                'reviewer_name' => $latestReview->reviewer_name,
                'recorded_by' => $latestReview->reviewer?->name,
                'notes' => $latestReview->notes,
                'occurred_at' => $latestReview->occurred_at?->toIso8601String(),
                'current' => $evaluation['latest_review_current'],
            ] : null,
            'review_history' => $evaluation['reviews']->map(fn (FiscalHomologationReview $review): array => [
                'decision' => $review->decision,
                'reviewer_name' => $review->reviewer_name,
                'recorded_by' => $review->reviewer?->name,
                'occurred_at' => $review->occurred_at?->toIso8601String(),
                'current' => hash_equals($evaluation['evidence_fingerprint'], $review->evidence_fingerprint),
            ])->values()->all(),
        ];
        $report['report_hash'] = hash('sha256', json_encode($report, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $report['generated_at'] = now()->toIso8601String();

        return $report;
    }

    /** @return array<int, array{key: string, label: string, ready: bool, category: string, detail: string|null}> */
    private function evidenceChecks(string $type, array $evidence): array
    {
        $label = $type === FiscalDocumentType::NFCE ? 'NFC-e' : 'NFS-e';
        $prefix = $type === FiscalDocumentType::NFCE ? 'nfce' : 'nfse';

        return [
            $this->check("{$prefix}_authorized", "{$label} autorizada pelo provedor em homologação", $evidence['authorized'], $label),
            $this->check("{$prefix}_rejected", "{$label} rejeitada e motivo capturado em homologação", $evidence['rejected'], $label),
            $this->check("{$prefix}_cancelled", "{$label} autorizada e depois cancelada em homologação", $evidence['cancelled'], $label),
            $this->check("{$prefix}_reconciled", 'Consulta de situação reconciliada em homologação', $evidence['reconciled'], $label),
            $this->check("{$prefix}_artifact", 'XML e documento auxiliar exercitados por download protegido', $evidence['artifact'], $label),
        ];
    }

    /** @return array<string, bool> */
    private function documentEvidence(int $clinicId, string $type): array
    {
        $base = FiscalDocument::withoutGlobalScopes()
            ->where('clinic_id', $clinicId)
            ->where('environment', 'homologation')
            ->where('provider', FiscalProviderCatalog::FOCUS_NFE)
            ->where('type', $type);
        $providerEvents = ['provider_response', 'status_synchronized', 'webhook_received'];
        $xmlDownloaded = (clone $base)
            ->whereHas('events', fn ($query) => $query
                ->where('event', 'artifact_downloaded')
                ->where('metadata->artifact', 'xml'))
            ->exists();
        $auxiliaryArtifact = $type === FiscalDocumentType::NFCE ? 'danfe' : 'danfse';
        $auxiliaryDownloaded = (clone $base)
            ->whereHas('events', fn ($query) => $query
                ->where('event', 'artifact_downloaded')
                ->where('metadata->artifact', $auxiliaryArtifact))
            ->exists();

        return [
            'authorized' => (clone $base)
                ->whereIn('status', ['authorized', 'cancelled'])
                ->whereNotNull('authorized_at')
                ->whereHas('events', fn ($query) => $query->whereIn('event', $providerEvents))
                ->exists(),
            'rejected' => (clone $base)
                ->where('status', 'rejected')
                ->whereHas('events', fn ($query) => $query->whereIn('event', $providerEvents))
                ->exists(),
            'cancelled' => (clone $base)
                ->where('status', 'cancelled')
                ->whereNotNull('authorized_at')
                ->whereNotNull('cancellation_requested_at')
                ->whereNotNull('cancelled_at')
                ->whereHas('events', fn ($query) => $query->whereIn('event', ['cancellation_succeeded', 'status_synchronized', 'webhook_received']))
                ->exists(),
            'reconciled' => (clone $base)
                ->whereHas('events', fn ($query) => $query->where('event', 'status_synchronized'))
                ->exists(),
            'artifact' => $xmlDownloaded && $auxiliaryDownloaded,
            'webhook' => (clone $base)
                ->whereHas('events', fn ($query) => $query->where('event', 'webhook_received'))
                ->exists(),
            'webhook_resend' => (clone $base)
                ->whereHas('events', fn ($query) => $query->where('event', 'webhook_resend_requested'))
                ->exists(),
        ];
    }

    /** @return array{ready: bool, total: int, ready_count: int, configuration_digest: string} */
    private function catalogEvidence(int $clinicId, string $type): array
    {
        $records = $type === FiscalDocumentType::NFCE
            ? Product::query()
                ->where('clinic_id', $clinicId)
                ->where('active', true)
                ->with('fiscalProfile:id,product_id,ready,configuration_version')
                ->orderBy('id')
                ->get(['id'])
            : PetShopService::query()
                ->where('clinic_id', $clinicId)
                ->where('active', true)
                ->with('fiscalProfile:id,petshop_service_id,ready,configuration_version')
                ->orderBy('id')
                ->get(['id']);
        $readyCount = $records->filter(fn ($record): bool => (bool) $record->fiscalProfile?->ready)->count();
        $signature = $records->map(fn ($record): string => implode(':', [
            $record->id,
            (int) ($record->fiscalProfile?->configuration_version ?? 0),
            $record->fiscalProfile?->ready ? 1 : 0,
        ]))->implode('|');

        return [
            'ready' => $records->isNotEmpty() && $readyCount === $records->count(),
            'total' => $records->count(),
            'ready_count' => $readyCount,
            'configuration_digest' => hash('sha256', $signature),
        ];
    }

    private function hasMixedSaleAllocation(int $clinicId): bool
    {
        return FiscalSaleAllocation::query()
            ->where('clinic_id', $clinicId)
            ->whereHas('documents', fn ($query) => $query->where('type', FiscalDocumentType::NFCE))
            ->whereHas('documents', fn ($query) => $query->where('type', FiscalDocumentType::NFSE))
            ->exists();
    }

    /** @param array{total: int, ready_count: int} $catalog */
    private function catalogDetail(array $catalog): string
    {
        return "{$catalog['ready_count']}/{$catalog['total']} cadastro(s) ativo(s) pronto(s)";
    }

    /** @return array{key: string, label: string, ready: bool, category: string, detail: string|null, guidance: string} */
    private function check(string $key, string $label, bool $ready, string $category, ?string $detail = null): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'ready' => $ready,
            'category' => $category,
            'detail' => $detail,
            'guidance' => $this->guidance($key),
        ];
    }

    private function guidance(string $key): string
    {
        return match ($key) {
            'profile' => 'Ative somente os tipos usados pela clínica, selecione Focus e salve o perfil de homologação.',
            'runtime' => 'Peça à implantação para concluir adaptador, credencial, fila, webhook e chaves independentes de tráfego de homologação.',
            'nfce_configuration' => 'Complete IE, credenciamento SEFAZ-RJ, certificado, CSC externo e sequência do modelo 65.',
            'nfce_catalog' => 'Revise com a contabilidade todos os produtos ativos na fila de tributação.',
            'nfse_configuration' => 'Complete município, inscrição, regime, certificado e sequência DPS da NFS-e.',
            'nfse_catalog' => 'Revise com a contabilidade todos os serviços ativos na fila de tributação.',
            'nfce_authorized', 'nfse_authorized' => 'Prepare, gere o payload e envie um documento revisado usando a conta real de homologação da clínica.',
            'nfce_rejected', 'nfse_rejected' => 'Execute com a contabilidade um caso controlado de rejeição em homologação e confira o motivo retornado.',
            'nfce_cancelled', 'nfse_cancelled' => 'Cancele em homologação um documento previamente autorizado e aguarde a confirmação final do provedor.',
            'nfce_reconciled', 'nfse_reconciled' => 'Use a consulta de situação em um documento processado e confirme o evento de reconciliação.',
            'nfce_artifact' => 'Baixe pelo VetFlow o XML e o DANFCe de uma NFC-e autorizada.',
            'nfse_artifact' => 'Baixe pelo VetFlow o XML e o DANFSe de uma NFS-e autorizada.',
            'nfse_webhook' => 'Registre o webhook autenticado na Focus e confirme um retorno legítimo de NFS-e.',
            'nfse_webhook_resend' => 'Solicite pelo VetFlow o reenvio do webhook e aguarde a aceitação do provedor.',
            'mixed_sale_allocation' => 'Prepare NFC-e e NFS-e de uma venda mista de teste e aprove o rateio em centavos.',
            default => 'Revise esta pendência com a implantação e a contabilidade antes de prosseguir.',
        };
    }
}
