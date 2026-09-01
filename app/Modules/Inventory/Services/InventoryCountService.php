<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Clinics\Models\Clinic;
use App\Modules\Inventory\Models\InventoryCount;
use App\Modules\Inventory\Models\InventoryCountItem;
use App\Modules\Products\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InventoryCountService
{
    private const QUANTITY_TOLERANCE = 0.0005;

    public function __construct(
        private readonly InventoryMovementService $movementService
    ) {}

    public function indexData(array $filters): array
    {
        $query = InventoryCount::query()
            ->with(['clinic', 'createdBy', 'finalizedBy', 'cancelledBy'])
            ->withCount([
                'items',
                'items as counted_items_count' => fn ($query) => $query->whereNotNull('counted_quantity'),
            ]);

        if ($search = trim((string) ($filters['q'] ?? ''))) {
            $query->where(function ($query) use ($search): void {
                $query->where('code', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        if ($status = $filters['status'] ?? null) {
            $query->where('status', $status);
        }

        /** @var LengthAwarePaginator $counts */
        $counts = $query->latest('opened_at')->paginate(20)->withQueryString();

        $statusTotals = InventoryCount::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'counts' => $counts,
            'filters' => $filters,
            'statusLabels' => InventoryCount::STATUS_LABELS,
            'stats' => [
                'total' => (int) $statusTotals->sum(),
                'draft' => (int) ($statusTotals['draft'] ?? 0),
                'finalized' => (int) ($statusTotals['finalized'] ?? 0),
                'cancelled' => (int) ($statusTotals['cancelled'] ?? 0),
            ],
        ];
    }

    public function createData(): array
    {
        return [
            'clinics' => auth()->user()?->clinic_id
                ? collect()
                : Clinic::query()->active()->orderBy('trade_name')->get(),
            'categories' => Product::query()
                ->active()
                ->whereNotNull('category')
                ->where('category', '<>', '')
                ->distinct()
                ->orderBy('category')
                ->pluck('category'),
        ];
    }

    public function create(array $data): InventoryCount
    {
        $clinicId = (int) (auth()->user()?->clinic_id ?: $data['clinic_id']);
        $category = trim((string) ($data['category'] ?? '')) ?: null;

        return DB::transaction(function () use ($data, $clinicId, $category): InventoryCount {
            $products = Product::query()
                ->active()
                ->where('clinic_id', $clinicId)
                ->when($category, fn ($query) => $query->where('category', $category))
                ->orderBy('name')
                ->lockForUpdate()
                ->get();

            if ($products->isEmpty()) {
                throw ValidationException::withMessages([
                    'category' => 'Nenhum produto ativo foi encontrado para o escopo selecionado.',
                ]);
            }

            $ulid = (string) Str::ulid();

            $count = InventoryCount::query()->create([
                'ulid' => $ulid,
                'clinic_id' => $clinicId,
                'created_by_user_id' => auth()->id(),
                'code' => 'CNT-'.Str::upper(substr($ulid, -8)),
                'title' => trim($data['title']),
                'category' => $category,
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
                'opened_at' => now(),
            ]);

            $count->items()->createMany($products->map(fn (Product $product): array => [
                'product_id' => $product->id,
                'expected_quantity' => $product->stock_quantity,
                'unit_cost_snapshot' => $product->cost_price,
            ])->all());

            return $count->load(['clinic', 'createdBy', 'items.product']);
        });
    }

    public function showData(int $id): array
    {
        $count = $this->findOrFail($id);
        $items = $count->items;

        return [
            'count' => $count,
            'statusLabels' => InventoryCount::STATUS_LABELS,
            'summary' => [
                'items' => $items->count(),
                'counted' => $items->whereNotNull('counted_quantity')->count(),
                'divergences' => $items->filter(
                    fn (InventoryCountItem $item): bool => $item->variance_quantity !== null
                        && abs((float) $item->variance_quantity) >= self::QUANTITY_TOLERANCE
                )->count(),
                'expected_value' => $items->sum(
                    fn (InventoryCountItem $item): float => (float) $item->expected_quantity * (float) $item->unit_cost_snapshot
                ),
                'counted_value' => $items->whereNotNull('counted_quantity')->sum(
                    fn (InventoryCountItem $item): float => (float) $item->counted_quantity * (float) $item->unit_cost_snapshot
                ),
            ],
        ];
    }

    public function update(int $id, array $data): InventoryCount
    {
        return DB::transaction(function () use ($id, $data): InventoryCount {
            $count = InventoryCount::query()->lockForUpdate()->findOrFail($id);
            $this->assertDraft($count);

            $submittedCounts = $data['counts'];

            foreach ($count->items()->get() as $item) {
                if (! array_key_exists((string) $item->id, $submittedCounts)
                    && ! array_key_exists($item->id, $submittedCounts)) {
                    continue;
                }

                $value = $submittedCounts[$item->id] ?? $submittedCounts[(string) $item->id];
                $item->update([
                    'counted_quantity' => $value === null || $value === '' ? null : round((float) $value, 3),
                ]);
            }

            $count->update(['notes' => $data['notes'] ?? null]);

            return $this->findOrFail($count->id);
        });
    }

    public function finalize(int $id): InventoryCount
    {
        return DB::transaction(function () use ($id): InventoryCount {
            $count = InventoryCount::query()->lockForUpdate()->findOrFail($id);
            $this->assertDraft($count);

            $items = $count->items()->with('product')->get();
            $missing = $items->whereNull('counted_quantity');

            if ($missing->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'inventory_count' => 'Preencha a quantidade física de todos os produtos antes de finalizar.',
                ]);
            }

            $products = Product::query()
                ->whereIn('id', $items->pluck('product_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($products->count() !== $items->count()) {
                throw ValidationException::withMessages([
                    'inventory_count' => 'Um ou mais produtos da contagem não estão mais disponíveis.',
                ]);
            }

            $staleItems = $items->filter(function (InventoryCountItem $item) use ($products): bool {
                $product = $products->get($item->product_id);

                return abs((float) $product->stock_quantity - (float) $item->expected_quantity) >= self::QUANTITY_TOLERANCE;
            });

            if ($staleItems->isNotEmpty()) {
                $productNames = $staleItems
                    ->take(3)
                    ->map(fn (InventoryCountItem $item): string => $item->product?->name ?? "Produto #{$item->product_id}")
                    ->implode(', ');

                throw ValidationException::withMessages([
                    'inventory_count' => "O estoque mudou depois da abertura da contagem ({$productNames}). Cancele esta contagem e abra uma nova para evitar sobrescrever movimentações legítimas.",
                ]);
            }

            foreach ($items as $item) {
                $variance = round((float) $item->counted_quantity - (float) $item->expected_quantity, 3);
                $movement = null;

                if (abs($variance) >= self::QUANTITY_TOLERANCE) {
                    $movement = $this->movementService->create([
                        'clinic_id' => $count->clinic_id,
                        'product_id' => $item->product_id,
                        'type' => $variance > 0 ? 'entry' : 'exit',
                        'quantity' => abs($variance),
                        'unit_cost' => $item->unit_cost_snapshot,
                        'source' => 'inventory_count',
                        'occurred_at' => now(),
                        'reason' => "Ajuste da contagem {$count->code}",
                        'metadata' => [
                            'inventory_count_id' => $count->id,
                            'inventory_count_item_id' => $item->id,
                            'expected_quantity' => (float) $item->expected_quantity,
                            'counted_quantity' => (float) $item->counted_quantity,
                        ],
                    ]);
                }

                $item->update([
                    'variance_quantity' => $variance,
                    'adjustment_movement_id' => $movement?->id,
                ]);
            }

            $count->update([
                'status' => 'finalized',
                'finalized_by_user_id' => auth()->id(),
                'finalized_at' => now(),
            ]);

            return $this->findOrFail($count->id);
        });
    }

    public function cancel(int $id, string $reason): InventoryCount
    {
        return DB::transaction(function () use ($id, $reason): InventoryCount {
            $count = InventoryCount::query()->lockForUpdate()->findOrFail($id);
            $this->assertDraft($count);

            $count->update([
                'status' => 'cancelled',
                'cancellation_reason' => trim($reason),
                'cancelled_by_user_id' => auth()->id(),
                'cancelled_at' => now(),
            ]);

            return $this->findOrFail($count->id);
        });
    }

    public function findOrFail(int $id): InventoryCount
    {
        return InventoryCount::query()
            ->with([
                'clinic',
                'createdBy',
                'finalizedBy',
                'cancelledBy',
                'items.product',
                'items.adjustmentMovement',
            ])
            ->findOrFail($id);
    }

    private function assertDraft(InventoryCount $count): void
    {
        if (! $count->isDraft()) {
            throw ValidationException::withMessages([
                'inventory_count' => 'Somente contagens em andamento podem ser alteradas.',
            ]);
        }
    }
}
