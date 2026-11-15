<?php

namespace App\Modules\ServiceOrders\Services;

use App\Core\Base\BaseService;
use App\Modules\Commissions\Services\GroomingCommissionService;
use App\Modules\Patients\Models\Patient;
use App\Modules\Patients\Support\PatientSize;
use App\Modules\PetShopServices\Models\PetShopService;
use App\Modules\PetShopServices\Services\PetPackageService;
use App\Modules\Products\Models\Product;
use App\Modules\ServiceOrders\Contracts\ServiceOrderRepositoryInterface;
use App\Modules\ServiceOrders\Models\ServiceOrder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ServiceOrderService extends BaseService
{
    public function __construct(
        ServiceOrderRepositoryInterface $repository,
        private readonly GroomingAgendaService $agenda,
        private readonly PetPackageService $packages,
        private readonly GroomingCommissionService $commissions,
    ) {
        $this->repository = $repository;
    }

    public function create(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            $items = $data['items'] ?? [];
            $frequency = $data['recurrence_frequency'] ?? null;
            $count = (int) ($data['recurrence_count'] ?? 0);
            unset($data['items'], $data['recurrence_frequency'], $data['recurrence_count'], $data['allow_overlap']);

            $repeatDates = ! empty($data['scheduled_at'])
                ? $this->agenda->recurrenceDates(CarbonImmutable::parse($data['scheduled_at']), $frequency, $count)
                : collect();

            if ($repeatDates->isNotEmpty()) {
                $data['recurrence_group'] = (string) Str::uuid();
            }

            $order = $this->createSingle($data, $items);

            foreach ($repeatDates as $date) {
                $this->createSingle(array_merge($data, [
                    'scheduled_at' => $date,
                    'status' => 'scheduled',
                    'opened_at' => now(),
                    'closed_at' => null,
                ]), $items);
            }

            return $order;
        });
    }

    /** @param array<int, array<string, mixed>> $items */
    private function createSingle(array $data, array $items): ServiceOrder
    {
        $data['code'] = $this->nextCode();
        $data['opened_at'] = $data['opened_at'] ?? now();
        $data = $this->withOperationalTimestamps($data);

        /** @var ServiceOrder $order */
        $order = $this->repository->create($data);

        $this->syncItems($order, $items);
        $this->packages->consumeForOrder($order);
        $this->recalculateTotals($order);
        $this->fillDurationFromServices($order);
        $this->commissions->syncForOrder($order);

        return $order->refresh();
    }

    public function update(int $id, array $data): Model
    {
        return DB::transaction(function () use ($id, $data) {
            $items = $data['items'] ?? [];
            unset($data['items'], $data['recurrence_frequency'], $data['recurrence_count'], $data['allow_overlap']);

            /** @var ServiceOrder $order */
            $order = $this->repository->findOrFail($id);
            $data = $this->withOperationalTimestamps($data, $order);

            $this->repository->update($order, $data);
            $this->packages->releaseForOrder($order);
            $order->items()->delete();

            $this->syncItems($order->refresh(), $items);
            $this->packages->consumeForOrder($order);
            $this->recalculateTotals($order);
            $this->fillDurationFromServices($order);
            $this->commissions->syncForOrder($order);

            return $order->refresh();
        });
    }

    /**
     * @return array{date: CarbonImmutable, columns: Collection<string, array{label: string, orders: Collection}>}
     */
    public function board(?string $date = null): array
    {
        $selectedDate = CarbonImmutable::parse($date ?: now()->toDateString())->startOfDay();
        $activeStatuses = ['open', 'in_service', 'waiting_pickup'];

        $orders = ServiceOrder::query()
            ->with(['clinic', 'tutor', 'patient', 'assignedUser', 'items'])
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->where(function ($query) use ($selectedDate, $activeStatuses): void {
                $query
                    ->where(function ($scheduled) use ($selectedDate): void {
                        $scheduled
                            ->whereIn('status', ServiceOrder::PRE_ARRIVAL_STATUSES)
                            ->whereDate('scheduled_at', $selectedDate->toDateString());
                    })
                    ->orWhere(function ($active) use ($selectedDate, $activeStatuses): void {
                        $active
                            ->whereIn('status', $activeStatuses)
                            ->where(function ($scheduled) use ($selectedDate): void {
                                $scheduled
                                    ->whereNull('scheduled_at')
                                    ->orWhereDate('scheduled_at', '<=', $selectedDate->toDateString());
                            });
                    })
                    ->orWhere(function ($finished) use ($selectedDate): void {
                        $finished
                            ->where('status', 'finished')
                            ->where(function ($completedOnDate) use ($selectedDate): void {
                                $completedOnDate
                                    ->whereDate('closed_at', $selectedDate->toDateString())
                                    ->orWhereDate('scheduled_at', $selectedDate->toDateString());
                            });
                    });
            })
            ->orderByRaw('CASE WHEN scheduled_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('scheduled_at')
            ->orderBy('opened_at')
            ->get();

        $columns = collect(ServiceOrder::BOARD_STATUSES)
            ->mapWithKeys(fn (string $status): array => [
                $status => [
                    'label' => ServiceOrder::BOARD_LABELS[$status],
                    'orders' => $status === 'scheduled'
                        ? $orders->whereIn('status', ServiceOrder::PRE_ARRIVAL_STATUSES)->values()
                        : $orders->where('status', $status)->values(),
                ],
            ]);

        return [
            'date' => $selectedDate,
            'columns' => $columns,
        ];
    }

    public function updateStatus(int $id, string $status): ServiceOrder
    {
        return DB::transaction(function () use ($id, $status): ServiceOrder {
            /** @var ServiceOrder $order */
            $order = $this->repository->findOrFail($id);

            if (
                $status !== 'finished'
                && $order->sales()->whereIn('status', ['completed', 'returned'])->exists()
            ) {
                throw ValidationException::withMessages([
                    'status' => 'A comanda já possui uma venda concluída e deve permanecer finalizada.',
                ]);
            }

            $previousStatus = $order->status;

            $this->repository->update($order, $this->withOperationalTimestamps([
                'status' => $status,
            ], $order));
            $order->refresh();

            if (in_array($status, ['cancelled', 'no_show'], true)) {
                $this->packages->releaseForOrder($order);
            } elseif (in_array($previousStatus, ['cancelled', 'no_show'], true)) {
                $this->packages->consumeForOrder($order);
                $this->recalculateTotals($order);
            }

            $this->commissions->syncForOrder($order);

            return $order->refresh();
        });
    }

    private function syncItems(ServiceOrder $order, array $items): void
    {
        $size = $this->patientSize($order);

        foreach ($items as $item) {
            $normalized = $this->normalizeItem($item, $size);

            if ($normalized === null) {
                continue;
            }

            $order->items()->create($normalized);
        }
    }

    private function normalizeItem(array $item, ?string $size = null): ?array
    {
        $type = $item['type'] ?? 'service';
        $quantity = (float) ($item['quantity'] ?? 0);

        if ($quantity <= 0) {
            return null;
        }

        $productId = $type === 'product' ? ($item['product_id'] ?? null) : null;
        $serviceId = $type === 'service' ? ($item['petshop_service_id'] ?? null) : null;
        $description = trim((string) ($item['description'] ?? ''));
        $unitPrice = $item['unit_price'] ?? null;

        if (! empty($item['from_package'])) {
            // Item que vinha de pacote: recalcula o preco; o consumo e refeito depois.
            $description = trim($this->packages->stripSuffix($description));
            $unitPrice = null;
        }

        if ($type === 'product' && $productId) {
            $product = Product::query()->find($productId);
            $description = $description ?: (string) $product?->name;
            $unitPrice = $unitPrice !== null && $unitPrice !== ''
                ? (float) $unitPrice
                : (float) ($product?->sale_price ?? 0);
        }

        if ($type === 'service' && $serviceId) {
            $service = PetShopService::query()->find($serviceId);
            $description = $description ?: (string) $service?->name;
            $unitPrice = $unitPrice !== null && $unitPrice !== ''
                ? (float) $unitPrice
                : $this->servicePriceForSize($service, $size);
        }

        if ($description === '') {
            return null;
        }

        $unitPrice = (float) ($unitPrice ?: 0);

        return [
            'type' => $type,
            'product_id' => $productId,
            'petshop_service_id' => $serviceId,
            'description' => $description,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total' => round($quantity * $unitPrice, 2),
        ];
    }

    private function patientSize(ServiceOrder $order): ?string
    {
        if (! $order->patient_id) {
            return null;
        }

        $patient = Patient::query()->find($order->patient_id);

        return $patient ? PatientSize::resolve($patient->size, $patient->weight) : null;
    }

    /** Preco do servico para o porte do pet, com fallback para o preco base. */
    public function servicePriceForSize(?PetShopService $service, ?string $size): float
    {
        if (! $service) {
            return 0.0;
        }

        $column = PatientSize::priceColumn($size);

        if ($column !== null && $service->{$column} !== null && (float) $service->{$column} > 0) {
            return (float) $service->{$column};
        }

        return (float) ($service->base_price ?? 0);
    }

    /** Sem duracao informada, soma a duracao estimada dos servicos. */
    private function fillDurationFromServices(ServiceOrder $order): void
    {
        if ($order->duration_minutes) {
            return;
        }

        $order->loadMissing('items');
        $serviceIds = $order->items->pluck('petshop_service_id')->filter()->all();

        if ($serviceIds === []) {
            return;
        }

        $minutes = (int) PetShopService::query()
            ->whereIn('id', $serviceIds)
            ->sum('duration_minutes');

        if ($minutes > 0) {
            $order->update(['duration_minutes' => min(600, $minutes)]);
        }
    }

    private function recalculateTotals(ServiceOrder $order): void
    {
        $order->load('items');

        $servicesTotal = (float) $order->items
            ->where('type', 'service')
            ->sum('total');

        $productsTotal = (float) $order->items
            ->where('type', 'product')
            ->sum('total');

        $customTotal = (float) $order->items
            ->where('type', 'custom')
            ->sum('total');

        $discount = (float) ($order->discount_total ?? 0);

        $order->update([
            'services_total' => $servicesTotal + $customTotal,
            'products_total' => $productsTotal,
            'total' => max(0, $servicesTotal + $productsTotal + $customTotal - $discount),
        ]);
    }

    private function nextCode(): string
    {
        $nextId = ((int) ServiceOrder::withTrashed()->max('id')) + 1;

        return 'CMD-'.str_pad((string) $nextId, 6, '0', STR_PAD_LEFT);
    }

    /** @param array<string, mixed> $data */
    private function withOperationalTimestamps(array $data, ?ServiceOrder $order = null): array
    {
        $status = $data['status'] ?? $order?->status ?? 'open';
        $now = now();
        $startedAt = $order?->started_at ?? $order?->opened_at ?? $data['opened_at'] ?? $now;

        if (in_array($status, ServiceOrder::PRE_ARRIVAL_STATUSES, true)) {
            $data['checked_in_at'] = null;
            $data['started_at'] = null;
            $data['ready_at'] = null;
            $data['closed_at'] = null;
        } elseif ($status === 'no_show') {
            $data['checked_in_at'] = null;
            $data['started_at'] = null;
            $data['ready_at'] = null;
            $data['closed_at'] = $data['closed_at'] ?? $order?->closed_at ?? $now;
        } elseif ($status === 'open') {
            $data['checked_in_at'] = $order?->checked_in_at ?? $now;
            $data['started_at'] = null;
            $data['ready_at'] = null;
            $data['closed_at'] = null;
        } elseif ($status === 'in_service') {
            $data['checked_in_at'] = $order?->checked_in_at ?? $now;
            $data['started_at'] = $order?->started_at ?? $now;
            $data['ready_at'] = null;
            $data['closed_at'] = null;
        } elseif ($status === 'waiting_pickup') {
            $data['started_at'] = $startedAt;
            $data['ready_at'] = $order?->ready_at ?? $now;
            $data['closed_at'] = null;
        } elseif ($status === 'finished') {
            $data['started_at'] = $startedAt;
            $data['ready_at'] = $order?->ready_at ?? $now;
            $data['closed_at'] = $data['closed_at'] ?? $order?->closed_at ?? $now;
        } elseif ($status === 'cancelled') {
            $data['closed_at'] = $data['closed_at'] ?? $order?->closed_at ?? $now;
        }

        return $data;
    }
}
