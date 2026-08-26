<?php

namespace App\Modules\PurchaseEntries\Services;

use App\Models\User;
use App\Modules\Products\Models\Product;
use App\Modules\PurchaseEntries\Models\ReplenishmentReviewEvent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReplenishmentReviewService
{
    public const DECISIONS = [
        'reviewed' => 'Revisada',
        'held' => 'Em espera',
    ];

    public function __construct(
        private readonly ReplenishmentSuggestionService $replenishmentSuggestions,
    ) {}

    /**
     * @param  Collection<int, array<string, mixed>>  $suggestions
     * @return Collection<int, array<string, mixed>>
     */
    public function attachLatest(User $user, Collection $suggestions): Collection
    {
        $productIds = $suggestions
            ->pluck('product.id')
            ->filter()
            ->map(fn ($productId): int => (int) $productId)
            ->values();

        $latest = $this->scopedQuery($user)
            ->with('actor:id,name')
            ->whereIn('product_id', $productIds)
            ->latest('reviewed_at')
            ->latest('id')
            ->get()
            ->unique('product_id')
            ->keyBy('product_id');

        return $suggestions->map(function (array $suggestion) use ($latest): array {
            $event = $latest->get($suggestion['product']->id);
            $evidenceHash = $this->hash($this->snapshot($suggestion));
            $current = $event !== null && hash_equals($event->evidence_hash, $evidenceHash);

            return array_merge($suggestion, [
                'review' => $event === null ? null : [
                    'decision' => $event->decision,
                    'label' => self::DECISIONS[$event->decision] ?? 'Revisão',
                    'note' => $event->note,
                    'actor' => $event->actor?->name,
                    'reviewed_at' => $event->reviewed_at,
                    'current' => $current,
                ],
                'review_status' => $this->status($event, $current),
            ]);
        });
    }

    public function record(User $user, Product $product, string $decision, ?string $note): ReplenishmentReviewEvent
    {
        if (! $product->active || (float) $product->minimum_stock <= 0 || (float) $product->stock_quantity > (float) $product->minimum_stock) {
            throw ValidationException::withMessages([
                'review' => 'Este produto não integra mais a lista atual de reposição.',
            ]);
        }

        $suggestion = $this->replenishmentSuggestions->suggestionFor($product);
        $snapshot = $this->snapshot($suggestion);

        return DB::transaction(fn (): ReplenishmentReviewEvent => ReplenishmentReviewEvent::query()->create([
            'clinic_id' => $product->clinic_id,
            'product_id' => $product->id,
            'actor_user_id' => $user->id,
            'product_name_snapshot' => $product->name,
            'decision' => $decision,
            'evidence_snapshot' => $snapshot,
            'evidence_hash' => $this->hash($snapshot),
            'note' => filled($note) ? trim((string) $note) : null,
            'reviewed_at' => now(),
        ]));
    }

    public function history(User $user, ?string $decision = null, ?string $search = null): LengthAwarePaginator
    {
        $currentHashes = $this->replenishmentSuggestions
            ->suggestions()
            ->mapWithKeys(fn (array $suggestion): array => [
                $suggestion['product']->id => $this->hash($this->snapshot($suggestion)),
            ]);
        $query = $this->scopedQuery($user)->with('actor:id,name');

        if (array_key_exists((string) $decision, self::DECISIONS)) {
            $query->where('decision', $decision);
        }

        if (filled($search)) {
            $query->where('product_name_snapshot', 'like', '%'.trim((string) $search).'%');
        }

        $events = $query
            ->latest('reviewed_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $events->through(function (ReplenishmentReviewEvent $event) use ($currentHashes): array {
            $currentHash = $event->product_id === null ? null : $currentHashes->get($event->product_id);

            return [
                'id' => $event->id,
                'product_id' => $event->product_id,
                'product_name' => $event->product_name_snapshot,
                'decision' => $event->decision,
                'decision_label' => self::DECISIONS[$event->decision] ?? 'Revisão',
                'decision_tone' => $event->decision === 'reviewed' ? 'success' : 'warning',
                'note' => $event->note,
                'actor' => $event->actor?->name,
                'reviewed_at' => $event->reviewed_at,
                'evidence_current' => is_string($currentHash) && hash_equals($event->evidence_hash, $currentHash),
                'snapshot' => $event->evidence_snapshot,
            ];
        });

        return $events;
    }

    /** @return array<string, mixed> */
    private function snapshot(array $suggestion): array
    {
        return [
            'version' => 1,
            'clinic_id' => (int) $suggestion['product']->clinic_id,
            'product_id' => (int) $suggestion['product']->id,
            'stock_quantity' => (float) $suggestion['stock_quantity'],
            'minimum_stock' => (float) $suggestion['minimum_stock'],
            'suggested_quantity' => (float) $suggestion['suggested_quantity'],
            'unit_cost' => (float) $suggestion['unit_cost'],
            'supplier_id' => $suggestion['last_supplier_id'] === null ? null : (int) $suggestion['last_supplier_id'],
            'demand_window_days' => (int) $suggestion['demand_window_days'],
            'net_demand_quantity' => (float) $suggestion['net_demand_quantity'],
            'average_monthly_demand' => (float) $suggestion['average_monthly_demand'],
            'lead_time_days' => $suggestion['coverage_lead_time_days'],
            'coverage_days' => $suggestion['coverage_days'],
            'coverage_margin_days' => $suggestion['coverage_margin_days'],
            'coverage_risk' => $suggestion['coverage_risk'],
        ];
    }

    private function hash(array $snapshot): string
    {
        return hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
    }

    private function scopedQuery(User $user)
    {
        return ReplenishmentReviewEvent::query()
            ->when(
                $user->clinic_id !== null,
                fn ($query) => $query->where('clinic_id', $user->clinic_id),
            );
    }

    /** @return array{key: string, label: string, tone: string} */
    private function status(?ReplenishmentReviewEvent $event, bool $current): array
    {
        if ($event === null) {
            return ['key' => 'pending', 'label' => 'Sem revisão', 'tone' => 'muted-badge'];
        }

        if (! $current) {
            return ['key' => 'stale', 'label' => 'Revisão superada', 'tone' => 'warning'];
        }

        return $event->decision === 'reviewed'
            ? ['key' => 'reviewed', 'label' => 'Revisada', 'tone' => 'success']
            : ['key' => 'held', 'label' => 'Em espera', 'tone' => 'warning'];
    }
}
