<?php

namespace App\Modules\Saas\Services;

use App\Models\User;
use App\Modules\Audit\Services\AuditTrailService;
use App\Modules\Saas\Models\LegalAcceptance;
use App\Modules\Saas\Models\LegalDocument;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LegalDocumentService
{
    public function __construct(
        private readonly AuditTrailService $audit,
        private readonly SubscriptionPriceService $prices
    ) {}

    public function documents(): Collection
    {
        if (! Schema::hasTable('saas_legal_documents')) {
            return new Collection;
        }

        return LegalDocument::query()
            ->withCount('acceptances')
            ->orderBy('type')
            ->latest('created_at')
            ->get();
    }

    public function saveDraft(array $data, User $actor): LegalDocument
    {
        $body = trim((string) $data['body']);
        $document = LegalDocument::query()->create([
            'type' => $data['type'],
            'title' => $data['title'],
            'version' => $data['version'],
            'status' => 'draft',
            'body' => $body,
            'content_hash' => LegalDocument::hashBody($body),
            'effective_at' => $data['effective_at'] ?? null,
        ]);

        $this->audit->record(
            'saas.legal-document.created',
            $document,
            [],
            $this->snapshot($document),
            $actor,
            [],
            null,
            $document->title
        );

        return $document;
    }

    public function publish(LegalDocument $document, User $actor, string $reviewerName): LegalDocument
    {
        return DB::transaction(function () use ($document, $actor, $reviewerName): LegalDocument {
            $document = LegalDocument::query()->lockForUpdate()->findOrFail($document->id);
            if ($document->status !== 'draft') {
                throw ValidationException::withMessages(['legal_review_confirmed' => 'Somente uma minuta pode ser publicada.']);
            }

            if ($document->hasUnresolvedPublicationMarkers()) {
                throw ValidationException::withMessages([
                    'legal_review_confirmed' => 'Esta versão ainda contém marcações de minuta, ausência de vigência ou campos pendentes. Crie uma nova versão final revisada antes de publicar.',
                ]);
            }

            $currentHash = LegalDocument::hashBody($document->body);
            if (! hash_equals($document->content_hash, $currentHash)) {
                throw ValidationException::withMessages(['legal_review_confirmed' => 'O conteúdo não corresponde ao hash congelado da minuta.']);
            }

            LegalDocument::query()
                ->where('type', $document->type)
                ->where('status', 'published')
                ->update(['status' => 'retired']);

            $before = $this->snapshot($document);
            $document->forceFill([
                'status' => 'published',
                'reviewed_at' => now(),
                'reviewed_by_name' => trim($reviewerName),
                'reviewed_by_user_id' => $actor->id,
                'published_at' => now(),
                'published_by_user_id' => $actor->id,
                'effective_at' => $document->effective_at ?? now(),
            ])->save();

            $this->audit->record(
                'saas.legal-document.published',
                $document,
                $before,
                $this->snapshot($document),
                $actor,
                [],
                null,
                $document->title
            );

            return $document;
        });
    }

    public function publicDocument(string $type): ?LegalDocument
    {
        if (! array_key_exists($type, LegalDocument::TYPES) || ! Schema::hasTable('saas_legal_documents')) {
            return null;
        }

        return LegalDocument::query()->where('type', $type)->published()->latest('published_at')->first()
            ?? LegalDocument::query()->where('type', $type)->where('status', 'draft')->latest()->first();
    }

    /**
     * @return array{documents: Collection, subscription: mixed, quote: array<string, mixed>, accepted_document_ids: array<int, int>, accepted_package_uuid: string|null, package_ready: bool}
     */
    public function acceptanceData(User $user): array
    {
        $clinic = $user->clinic?->load('subscription.plan');
        $documents = $this->publishedPackage();
        $subscription = $clinic?->subscription;
        $quote = $subscription
            ? $this->prices->quote($subscription)
            : ['billing_cycle' => 'monthly', 'base_price_cents' => null, 'discount_cents' => 0, 'final_price_cents' => null, 'coupon_code' => null, 'discount_percentage' => null, 'promotion' => null];
        $acceptedPackageUuid = $clinic && $subscription && Schema::hasTable('saas_legal_acceptances')
            ? $this->currentPackageUuid($user, $documents, $quote)
            : null;
        $accepted = $acceptedPackageUuid
            ? LegalAcceptance::query()
                ->where('acceptance_uuid', $acceptedPackageUuid)
                ->whereIn('legal_document_id', $documents->pluck('id'))
                ->pluck('legal_document_id')
                ->map(fn ($id): int => (int) $id)
                ->all()
            : [];

        return [
            'documents' => $documents,
            'subscription' => $subscription,
            'quote' => $quote,
            'accepted_document_ids' => $accepted,
            'accepted_package_uuid' => $acceptedPackageUuid,
            'package_ready' => $documents->count() === count(LegalDocument::TYPES),
        ];
    }

    public function acceptCurrentPackage(User $user, ?string $ipAddress, ?string $userAgent): string
    {
        return DB::transaction(function () use ($user, $ipAddress, $userAgent): string {
            $user->load('clinic.subscription.plan');
            $clinic = $user->clinic;
            if (! $clinic || ! $clinic->subscription) {
                throw ValidationException::withMessages(['acceptance_confirmed' => 'O usuário não possui uma assinatura de estabelecimento.']);
            }

            $documents = $this->publishedPackage();
            if ($documents->count() !== count(LegalDocument::TYPES)) {
                throw ValidationException::withMessages(['acceptance_confirmed' => 'O pacote jurídico ainda não foi integralmente revisado e publicado.']);
            }

            $quote = $this->prices->quote($clinic->subscription);
            $existingPackageUuid = $this->currentPackageUuid($user, $documents, $quote);
            if ($existingPackageUuid) {
                return (string) $existingPackageUuid;
            }

            $acceptanceUuid = (string) Str::uuid();
            $created = collect();

            foreach ($documents as $document) {
                $acceptance = LegalAcceptance::query()->create([
                    'acceptance_uuid' => $acceptanceUuid,
                    'legal_document_id' => $document->id,
                    'clinic_id' => $clinic->id,
                    'user_id' => $user->id,
                    'signer_name' => $user->name,
                    'signer_email' => $user->email,
                    'company_document' => preg_replace('/\D+/', '', (string) $clinic->cnpj),
                    'document_type' => $document->type,
                    'document_version' => $document->version,
                    'document_hash' => $document->content_hash,
                    'plan_id' => $clinic->subscription->plan_id,
                    'plan_name' => $clinic->subscription->plan?->name,
                    'billing_cycle' => 'monthly',
                    'base_price_cents' => $quote['base_price_cents'],
                    'coupon_code' => $quote['coupon_code'],
                    'discount_percentage' => $quote['discount_percentage'],
                    'final_price_cents' => $quote['final_price_cents'],
                    'promotion_starts_on' => $quote['promotion']?->starts_on,
                    'promotion_ends_on' => $quote['promotion']?->ends_on,
                    'evidence' => [
                        'clinic_name' => $clinic->trade_name ?: $clinic->corporate_name,
                        'document_title' => $document->title,
                        'acceptance_confirmed' => true,
                        'authority_confirmed' => true,
                        'promotion_starts_on' => $quote['promotion']?->starts_on?->toDateString(),
                        'promotion_ends_on' => $quote['promotion']?->ends_on?->toDateString(),
                    ],
                    'ip_address' => $ipAddress,
                    'user_agent' => Str::limit((string) $userAgent, 500, ''),
                    'accepted_at' => now(),
                ]);
                $created->push($acceptance);
            }

            if ($created->isNotEmpty()) {
                $first = $created->first();
                $this->audit->record(
                    'saas.legal-package.accepted',
                    $first,
                    [],
                    ['acceptance_uuid' => $acceptanceUuid, 'documents' => $created->count()],
                    $user,
                    ['document_versions' => $documents->pluck('version', 'type')->all()],
                    $clinic->id,
                    $clinic->trade_name ?: $clinic->corporate_name
                );
            }

            return $acceptanceUuid;
        });
    }

    private function publishedPackage(): Collection
    {
        if (! Schema::hasTable('saas_legal_documents')) {
            return new Collection;
        }

        return LegalDocument::query()
            ->published()
            ->whereIn('type', array_keys(LegalDocument::TYPES))
            ->orderBy('type')
            ->get()
            ->unique('type')
            ->values();
    }

    /** @param array<string, mixed> $quote */
    private function currentPackageUuid(User $user, Collection $documents, array $quote): ?string
    {
        $subscription = $user->clinic?->subscription;
        if (! $subscription || $documents->isEmpty()) {
            return null;
        }

        return LegalAcceptance::query()
            ->where('clinic_id', $user->clinic_id)
            ->where('user_id', $user->id)
            ->where('plan_id', $subscription->plan_id)
            ->where('billing_cycle', $quote['billing_cycle'])
            ->where('base_price_cents', $quote['base_price_cents'])
            ->where('coupon_code', $quote['coupon_code'])
            ->where('discount_percentage', $quote['discount_percentage'])
            ->where('final_price_cents', $quote['final_price_cents'])
            ->where('promotion_starts_on', $quote['promotion']?->starts_on?->toDateString())
            ->where('promotion_ends_on', $quote['promotion']?->ends_on?->toDateString())
            ->whereIn('legal_document_id', $documents->pluck('id'))
            ->select('acceptance_uuid')
            ->groupBy('acceptance_uuid')
            ->havingRaw('COUNT(DISTINCT legal_document_id) = ?', [$documents->count()])
            ->value('acceptance_uuid');
    }

    /** @return array<string, mixed> */
    private function snapshot(LegalDocument $document): array
    {
        return [
            'type' => $document->type,
            'version' => $document->version,
            'status' => $document->status,
            'content_hash' => $document->content_hash,
            'effective_at' => $document->effective_at?->toIso8601String(),
            'reviewed_at' => $document->reviewed_at?->toIso8601String(),
            'published_at' => $document->published_at?->toIso8601String(),
        ];
    }
}
