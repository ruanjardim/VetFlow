<?php

namespace App\Modules\Fiscal\Services;

use App\Models\User;
use App\Modules\Fiscal\Models\FiscalDocument;
use App\Modules\Fiscal\Models\FiscalDocumentEvent;
use App\Modules\Fiscal\Models\FiscalProfile;
use App\Modules\Fiscal\Support\FiscalDocumentType;
use App\Modules\Sales\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FiscalDocumentService
{
    public function __construct(
        private readonly NfseMunicipalityRuleService $nfseMunicipalityRules,
        private readonly FiscalEnvironmentGuard $environment,
    ) {}

    public function prepareFromSale(Sale $sale, string $type, User $actor): FiscalDocument
    {
        $sale->loadMissing([
            'clinic',
            'tutor',
            'items.product.fiscalProfile',
            'items.petShopService.fiscalProfile',
            'payments.paymentMethod',
        ]);
        $profile = FiscalProfile::query()->where('clinic_id', $sale->clinic_id)->first();
        $this->validatePreparation($sale, $profile, $type);
        $this->validateTaxProfiles($sale, $type);

        if ($type === FiscalDocumentType::NFSE) {
            $this->nfseMunicipalityRules->validatePreparation($sale, $profile);
        }

        $idempotencyKey = hash('sha256', implode(':', [
            'fiscal-document',
            $sale->clinic_id,
            $sale->id,
            $type,
            $profile->environment,
            'v2',
        ]));

        return DB::transaction(function () use ($sale, $profile, $type, $actor, $idempotencyKey): FiscalDocument {
            $document = FiscalDocument::query()->firstOrCreate(
                ['idempotency_key' => $idempotencyKey],
                [
                    'clinic_id' => $sale->clinic_id,
                    'sale_id' => $sale->id,
                    'created_by_user_id' => $actor->id,
                    'type' => $type,
                    'environment' => $profile->environment,
                    'provider' => $profile->provider,
                    'status' => 'draft',
                    'profile_version' => $profile->configuration_version,
                    'schema_version' => 3,
                    'issuer_snapshot' => $this->issuerSnapshot($sale, $profile),
                    'recipient_snapshot' => $this->recipientSnapshot($sale),
                    'items_snapshot' => $this->itemsSnapshot($sale, $type),
                    'payments_snapshot' => $this->paymentsSnapshot($sale, $type),
                    'totals_snapshot' => $this->totalsSnapshot($sale, $type),
                ]
            );

            if (! $document->wasRecentlyCreated) {
                return $document;
            }

            FiscalDocumentEvent::query()->create([
                'clinic_id' => $sale->clinic_id,
                'fiscal_document_id' => $document->id,
                'actor_user_id' => $actor->id,
                'event' => 'prepared',
                'from_status' => null,
                'to_status' => 'draft',
                'metadata' => [
                    'source' => 'sale',
                    'sale_code' => $sale->code,
                    'automatic' => false,
                ],
                'occurred_at' => now(),
            ]);

            return $document;
        });
    }

    private function validatePreparation(Sale $sale, ?FiscalProfile $profile, string $type): void
    {
        if (! in_array($type, FiscalDocumentType::values(), true)) {
            throw ValidationException::withMessages(['type' => 'Tipo de documento fiscal inválido.']);
        }

        if ($sale->status !== 'completed') {
            throw ValidationException::withMessages(['sale' => 'Somente vendas concluídas podem gerar uma preparação fiscal.']);
        }

        if (! $profile?->enabled) {
            throw ValidationException::withMessages(['fiscal' => 'Ative a preparação fiscal deste estabelecimento antes de continuar.']);
        }

        if (! $this->environment->allowsProfile($profile)) {
            throw ValidationException::withMessages([
                'fiscal' => $profile->environment === FiscalEnvironmentGuard::PRODUCTION
                    ? 'A ativação fiscal de produção não está vigente ou não corresponde à configuração atual.'
                    : 'O ambiente fiscal configurado não é suportado.',
            ]);
        }

        $enabled = $type === FiscalDocumentType::NFCE ? $profile->nfce_enabled : $profile->nfse_enabled;

        if (! $enabled) {
            throw ValidationException::withMessages(['type' => FiscalDocumentType::labels()[$type].' não está habilitada na configuração fiscal.']);
        }
    }

    private function validateTaxProfiles(Sale $sale, string $type): void
    {
        if ($sale->items->contains(fn ($item): bool => $item->type === 'custom')) {
            throw ValidationException::withMessages([
                'fiscal_items' => 'A venda possui item avulso. Vincule cada linha a um produto ou serviço antes da preparação fiscal.',
            ]);
        }

        $items = $this->eligibleItems($sale, $type);

        if ($items->isEmpty()) {
            $label = $type === FiscalDocumentType::NFCE ? 'produto' : 'serviço';
            throw ValidationException::withMessages([
                'fiscal_items' => "A venda não possui {$label} compatível com este documento.",
            ]);
        }

        $pending = $items
            ->filter(function ($item) use ($type): bool {
                if ($type === FiscalDocumentType::NFCE) {
                    return ! $item->product_id || ! $item->product?->fiscalProfile?->ready;
                }

                return ! $item->petshop_service_id || ! $item->petShopService?->fiscalProfile?->ready;
            })
            ->pluck('description')
            ->unique()
            ->values();

        if ($pending->isNotEmpty()) {
            throw ValidationException::withMessages([
                'fiscal_items' => 'Complete o cadastro tributário antes de preparar: '.$pending->join(', ').'.',
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function issuerSnapshot(Sale $sale, FiscalProfile $profile): array
    {
        $productionActivation = $profile->environment === FiscalEnvironmentGuard::PRODUCTION
            ? $this->environment->activeProductionForClinic((int) $profile->clinic_id)
            : null;

        return [
            'corporate_name' => $sale->clinic?->corporate_name,
            'trade_name' => $sale->clinic?->trade_name,
            'document_type' => $sale->clinic?->document_type,
            'document' => $sale->clinic?->cnpj,
            'state_registration' => $profile->state_registration,
            'municipal_registration' => $profile->municipal_registration,
            'tax_regime' => $profile->tax_regime,
            'municipality_code' => $profile->municipality_code,
            'cnae' => $profile->cnae,
            'certificate_reference' => $profile->certificate_reference,
            'certificate_fingerprint' => $profile->certificate_fingerprint,
            'certificate_expires_at' => $profile->certificate_expires_at?->toDateString(),
            'provider_credential_reference' => data_get($profile->metadata, 'provider_credential_reference'),
            'production_activation' => $productionActivation ? [
                'ulid' => $productionActivation->ulid,
                'evidence_fingerprint' => $productionActivation->evidence_fingerprint,
                'activated_at' => $productionActivation->activated_at?->toIso8601String(),
            ] : null,
            'focus_nfe' => [
                'dps_series' => data_get($profile->metadata, 'focus_nfe.dps_series'),
                'simple_national_option' => data_get($profile->metadata, 'focus_nfe.simple_national_option'),
                'simple_national_tax_regime' => data_get($profile->metadata, 'focus_nfe.simple_national_tax_regime'),
                'special_tax_regime' => data_get($profile->metadata, 'focus_nfe.special_tax_regime'),
            ],
            'focus_nfce' => [
                'series' => data_get($profile->metadata, 'focus_nfce.series'),
                'csc_id' => data_get($profile->metadata, 'focus_nfce.csc_id'),
                'csc_reference' => data_get($profile->metadata, 'focus_nfce.csc_reference'),
                'pix_payment_code' => data_get($profile->metadata, 'focus_nfce.pix_payment_code'),
                'sefaz_credentialed' => (bool) data_get($profile->metadata, 'focus_nfce.sefaz_credentialed', false),
            ],
            'address' => [
                'zip_code' => $sale->clinic?->zip_code,
                'state' => $sale->clinic?->state,
                'city' => $sale->clinic?->city,
                'district' => $sale->clinic?->district,
                'street' => $sale->clinic?->street,
                'number' => $sale->clinic?->number,
                'complement' => $sale->clinic?->complement,
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    private function recipientSnapshot(Sale $sale): ?array
    {
        if (! $sale->tutor) {
            return null;
        }

        return [
            'name' => $sale->tutor->name,
            'document' => $sale->tutor->cpf,
            'email' => $sale->tutor->email,
            'phone' => $sale->tutor->phone,
            'sale_type' => $sale->sale_type,
            'consumer_final' => ! in_array($sale->sale_type, ['in_store_resale', 'delivery_resale'], true),
            'address' => [
                'zip_code' => $sale->tutor->zip_code,
                'state' => $sale->tutor->state,
                'city' => $sale->tutor->city,
                'district' => $sale->tutor->district,
                'street' => $sale->tutor->street,
                'number' => $sale->tutor->number,
                'complement' => $sale->tutor->complement,
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function itemsSnapshot(Sale $sale, string $type): array
    {
        return $this->eligibleItems($sale, $type)
            ->map(function ($item) use ($type): array {
                $fiscalProfile = $type === FiscalDocumentType::NFCE
                    ? $item->product?->fiscalProfile
                    : $item->petShopService?->fiscalProfile;

                return [
                    'source_id' => $item->id,
                    'type' => $item->type,
                    'product_id' => $item->product_id,
                    'service_id' => $item->petshop_service_id,
                    'description' => $item->description,
                    'quantity' => (string) $item->quantity,
                    'unit_price' => (string) $item->unit_price,
                    'discount_total' => (string) $item->discount_total,
                    'total' => (string) $item->total,
                    'fiscal_profile_version' => $fiscalProfile?->configuration_version,
                    'fiscal' => $this->fiscalItemSnapshot($fiscalProfile, $type),
                ];
            })
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function paymentsSnapshot(Sale $sale, string $type): array
    {
        $requiresAllocation = $this->requiresAllocation($sale, $type);

        return $sale->payments
            ->filter(fn ($payment): bool => ($payment->status ?? 'paid') === 'paid' && (float) $payment->amount > 0)
            ->map(fn ($payment): array => [
                'source_id' => $payment->id,
                'method' => $payment->method,
                'method_name' => $payment->methodLabel(),
                'amount' => (string) $payment->amount,
                'installments' => $payment->installments,
                'acquirer' => $payment->acquirer,
                'card_brand' => $payment->card_brand,
                'reference' => $payment->reference,
                'paid_at' => $payment->paid_at?->toIso8601String(),
                'allocation' => $requiresAllocation ? 'pending' : 'full_sale',
            ])->values()->all();
    }

    /** @return array<string, string> */
    private function totalsSnapshot(Sale $sale, string $type): array
    {
        $items = $this->eligibleItems($sale, $type);
        $selectedItemsTotal = round($items->sum(fn ($item): float => (float) $item->total), 2);
        $requiresAllocation = $this->requiresAllocation($sale, $type);

        return [
            'subtotal' => (string) $sale->subtotal,
            'discount_total' => (string) $sale->discount_total,
            'additions_total' => (string) $sale->additions_total,
            'delivery_fee' => (string) $sale->delivery_fee,
            'sale_total' => (string) $sale->total,
            'selected_items_total' => number_format($selectedItemsTotal, 2, '.', ''),
            'total' => $requiresAllocation ? number_format($selectedItemsTotal, 2, '.', '') : (string) $sale->total,
            'paid_total' => (string) $sale->paid_total,
            'change_total' => (string) $sale->change_total,
            'competence_date' => ($sale->completed_at ?: $sale->sold_at ?: now())->toDateString(),
            'sale_type' => $sale->sale_type,
            'delivery' => $sale->hasDelivery() ? 'yes' : 'no',
            'requires_allocation' => $requiresAllocation ? 'yes' : 'no',
        ];
    }

    private function eligibleItems(Sale $sale, string $type)
    {
        $itemType = $type === FiscalDocumentType::NFCE ? 'product' : 'service';

        return $sale->items->where('type', $itemType)->values();
    }

    private function requiresAllocation(Sale $sale, string $type): bool
    {
        return $this->eligibleItems($sale, $type)->count() !== $sale->items->count()
            || (float) $sale->discount_total !== 0.0
            || (float) $sale->additions_total !== 0.0
            || (float) $sale->delivery_fee !== 0.0;
    }

    /** @return array<string, mixed> */
    private function fiscalItemSnapshot($profile, string $type): array
    {
        if ($type === FiscalDocumentType::NFCE) {
            return $profile->only([
                'ncm', 'cest', 'origin_code', 'default_cfop', 'icms_code',
                'pis_code', 'cofins_code', 'fiscal_unit', 'tax_benefit_code',
                'ibs_cbs_tax_status', 'ibs_cbs_classification_code',
            ]);
        }

        return $profile->only([
            'national_taxation_code', 'municipal_taxation_code', 'service_list_item',
            'cnae', 'nbs_code', 'municipality_code', 'iss_rate', 'iss_withheld',
            'iss_taxation', 'ibs_cbs_tax_status', 'ibs_cbs_classification_code',
            'operation_indicator',
        ]);
    }
}
