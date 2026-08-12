<?php

namespace App\Modules\Sales\Services;

use App\Core\Base\BaseService;
use App\Modules\Financial\Models\FinancialTransaction;
use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Inventory\Services\InventoryMovementService;
use App\Modules\Inventory\Services\ProductLotService;
use App\Modules\PetShopServices\Models\PetShopService;
use App\Modules\Products\Models\Product;
use App\Modules\Sales\Contracts\SaleRepositoryInterface;
use App\Modules\Sales\Models\CashRegisterClosure;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleEvent;
use App\Modules\Sales\Models\SaleItem;
use App\Modules\Sales\Models\SalePayment;
use App\Modules\ServiceOrders\Models\ServiceOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleService extends BaseService
{
    public function __construct(
        SaleRepositoryInterface $repository,
        private readonly InventoryMovementService $inventoryMovementService,
        private readonly ProductLotService $lotService
    ) {
        $this->repository = $repository;
    }

    public function create(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            $items = $data['items'] ?? [];
            $payments = $data['payments'] ?? [];
            unset($data['items'], $data['payments']);

            $serviceOrder = $this->serviceOrderWithItems($data['service_order_id'] ?? null);

            if ($serviceOrder && ! $this->hasBillableItems($items)) {
                $items = $this->itemsFromServiceOrder($serviceOrder);

                if ((float) ($data['discount_total'] ?? 0) <= 0) {
                    $data['discount_total'] = (float) $serviceOrder->discount_total;
                }
            }

            $data['code'] = $this->nextCode();
            $data['sold_at'] = $data['sold_at'] ?? now();
            $data['source'] = $data['source'] ?? 'pdv';
            $data['seller_user_id'] = $data['seller_user_id'] ?? auth()->id();
            $data['discount_total'] = (float) ($data['discount_total'] ?? 0);
            $data['additions_total'] = (float) ($data['additions_total'] ?? 0);

            /** @var Sale $sale */
            $sale = $this->repository->create($data);

            $this->syncItems($sale, $items);
            $this->syncPayments($sale, $payments);
            $this->recalculateTotals($sale);
            $this->applyCompletionEffects($sale->refresh());

            return $sale->refresh();
        });
    }

    public function update(int $id, array $data): Model
    {
        return DB::transaction(function () use ($id, $data) {
            $items = $data['items'] ?? [];
            $payments = $data['payments'] ?? [];
            unset($data['items'], $data['payments']);

            /** @var Sale $sale */
            $sale = $this->repository->findOrFail($id);
            $effectsAlreadyApplied = $sale->stock_applied || $sale->financial_applied;

            if ($effectsAlreadyApplied) {
                unset($data['discount_total'], $data['status']);
            }

            $serviceOrder = $this->serviceOrderWithItems($data['service_order_id'] ?? null);

            if (! $effectsAlreadyApplied && $serviceOrder && ! $this->hasBillableItems($items)) {
                $items = $this->itemsFromServiceOrder($serviceOrder);

                if ((float) ($data['discount_total'] ?? 0) <= 0) {
                    $data['discount_total'] = (float) $serviceOrder->discount_total;
                }
            }

            $data['discount_total'] = (float) ($data['discount_total'] ?? $sale->discount_total ?? 0);
            $data['additions_total'] = (float) ($data['additions_total'] ?? $sale->additions_total ?? 0);
            $data['source'] = $data['source'] ?? $sale->source ?? 'pdv';
            $data['seller_user_id'] = $data['seller_user_id'] ?? $sale->seller_user_id;

            $this->repository->update($sale, $data);

            if (! $effectsAlreadyApplied) {
                $sale->items()->delete();
                $sale->payments()->delete();

                $this->syncItems($sale->refresh(), $items);
                $this->syncPayments($sale->refresh(), $payments);
                $this->recalculateTotals($sale->refresh());
                $this->applyCompletionEffects($sale->refresh());
            }

            return $sale->refresh();
        });
    }

    public function cancelSale(int $id, array $data = []): Sale
    {
        return DB::transaction(function () use ($id, $data) {
            /** @var Sale $sale */
            $sale = Sale::query()
                ->with(['items.product', 'payments', 'financialTransaction', 'serviceOrder'])
                ->findOrFail($id);

            if ($sale->status === 'cancelled') {
                return $sale->refresh();
            }

            if (! in_array($sale->status, ['completed', 'draft'], true)) {
                throw ValidationException::withMessages([
                    'sale' => 'Apenas vendas em rascunho ou concluidas podem ser canceladas.',
                ]);
            }

            $reason = trim((string) ($data['reason'] ?? '')) ?: 'Cancelamento operacional';

            if ($sale->stock_applied) {
                foreach ($sale->items as $item) {
                    $quantityToRestore = max(0, (float) $item->quantity - (float) $item->returned_quantity);

                    if ($quantityToRestore <= 0) {
                        continue;
                    }

                    $this->restoreStockForItem(
                        $sale,
                        $item,
                        $quantityToRestore,
                        'sale_cancellation',
                        $reason,
                        'stock_reversal'
                    );
                }
            }

            foreach ($sale->items as $item) {
                $item->update([
                    'returned_quantity' => (float) $item->quantity,
                    'refunded_total' => $this->saleItemNetTotal($item),
                ]);
            }

            $sale->payments()->update(['status' => 'cancelled']);

            if ($sale->financialTransaction) {
                $sale->financialTransaction->update([
                    'status' => 'cancelled',
                    'paid_at' => null,
                    'notes' => trim(implode("\n", array_filter([
                        (string) $sale->financialTransaction->notes,
                        'Cancelado pela venda '.$sale->code.': '.$reason,
                    ]))),
                ]);
            }

            if ($sale->service_order_id) {
                ServiceOrder::query()
                    ->whereKey($sale->service_order_id)
                    ->where('status', 'finished')
                    ->update([
                        'status' => 'open',
                        'closed_at' => null,
                    ]);
            }

            $sale->update([
                'status' => 'cancelled',
                'payment_status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
                'return_total' => (float) $sale->total,
                'refunded_total' => min((float) $sale->paid_total, (float) $sale->total),
            ]);

            $this->recordSaleEvent(
                $sale->refresh(),
                'cancelled',
                null,
                null,
                (float) $sale->total,
                $reason,
                null,
                [
                    'cancelled_by_user_id' => auth()->id(),
                    'paid_total' => (float) $sale->paid_total,
                ],
                false
            );

            return $sale->refresh();
        });
    }

    public function returnItems(int $id, array $data): Sale
    {
        return DB::transaction(function () use ($id, $data) {
            /** @var Sale $sale */
            $sale = Sale::query()
                ->with(['items.product', 'payments'])
                ->findOrFail($id);

            if ($sale->status !== 'completed') {
                throw ValidationException::withMessages([
                    'sale' => 'Apenas vendas concluidas podem receber devolucao.',
                ]);
            }

            $reason = trim((string) ($data['reason'] ?? '')) ?: 'Devolucao de venda';
            $itemsData = $data['items'] ?? [];
            $returnedValue = 0.0;
            $returnedQuantityTotal = 0.0;
            $returnedQuantityByItem = [];

            foreach ($sale->items as $item) {
                $quantity = (float) ($itemsData[$item->id]['quantity'] ?? 0);

                if ($quantity <= 0) {
                    continue;
                }

                $available = max(0, (float) $item->quantity - (float) $item->returned_quantity);
                $quantity = min($quantity, $available);

                if ($quantity <= 0) {
                    continue;
                }

                $unitValue = (float) $item->quantity > 0
                    ? round($this->saleItemNetTotal($item) / (float) $item->quantity, 2)
                    : 0.0;
                $lineReturnValue = round($quantity * $unitValue, 2);
                $returnedValue = round($returnedValue + $lineReturnValue, 2);
                $returnedQuantityTotal = round($returnedQuantityTotal + $quantity, 3);
                $returnedQuantityByItem[$item->id] = $quantity;

                if ($sale->stock_applied && $item->type === 'product' && $item->product_id) {
                    $this->restoreStockForItem(
                        $sale,
                        $item,
                        $quantity,
                        'sale_return',
                        $reason,
                        'stock_return'
                    );
                }

                $item->update([
                    'returned_quantity' => round((float) $item->returned_quantity + $quantity, 3),
                    'refunded_total' => round((float) $item->refunded_total + $lineReturnValue, 2),
                ]);

                $this->recordSaleEvent(
                    $sale,
                    'item_returned',
                    $item->id,
                    null,
                    $lineReturnValue,
                    $reason,
                    $quantity,
                    [
                        'description' => $item->description,
                        'product_id' => $item->product_id,
                    ],
                    false
                );
            }

            if ($returnedQuantityTotal <= 0) {
                throw ValidationException::withMessages([
                    'items' => 'Informe pelo menos uma quantidade valida para devolver.',
                ]);
            }

            $requestedRefund = array_key_exists('refund_amount', $data)
                ? (float) $data['refund_amount']
                : $returnedValue;
            $refundAmount = max(0, min($requestedRefund, $returnedValue));
            $refundMethod = $data['refund_method'] ?? 'cash';

            if ($refundAmount > 0) {
                FinancialTransaction::query()->create([
                    'clinic_id' => $sale->clinic_id,
                    'type' => 'expense',
                    'description' => 'Estorno venda '.$sale->code,
                    'amount' => $refundAmount,
                    'due_date' => today(),
                    'paid_at' => now(),
                    'status' => 'paid',
                    'payment_method' => $refundMethod,
                    'reference' => $data['reference'] ?? null,
                    'notes' => $reason,
                ]);

                $this->recordSaleEvent(
                    $sale,
                    'refund',
                    null,
                    null,
                    $refundAmount,
                    $reason,
                    null,
                    [
                        'refund_method' => $refundMethod,
                        'returned_value' => $returnedValue,
                        'items' => $returnedQuantityByItem,
                    ],
                    false
                );
            }

            $sale->refresh()->load('items');
            $allReturned = $sale->items->every(
                fn (SaleItem $item) => (float) $item->returned_quantity >= (float) $item->quantity
            );
            $newReturnTotal = round((float) $sale->return_total + $returnedValue, 2);
            $newRefundedTotal = round((float) $sale->refunded_total + $refundAmount, 2);

            $sale->update([
                'status' => $allReturned ? 'returned' : 'completed',
                'payment_status' => $allReturned && $newRefundedTotal >= min((float) $sale->paid_total, (float) $sale->total)
                    ? 'refunded'
                    : $sale->payment_status,
                'return_total' => $newReturnTotal,
                'refunded_total' => $newRefundedTotal,
            ]);

            $this->recordSaleEvent(
                $sale->refresh(),
                $allReturned ? 'returned' : 'partial_return',
                null,
                null,
                $returnedValue,
                $reason,
                null,
                [
                    'refund_amount' => $refundAmount,
                    'refund_method' => $refundMethod,
                    'items' => $returnedQuantityByItem,
                ],
                false
            );

            return $sale->refresh();
        });
    }

    public function addPayment(int $id, array $data): Sale
    {
        return DB::transaction(function () use ($id, $data) {
            /** @var Sale $sale */
            $sale = Sale::query()
                ->with(['items', 'payments', 'financialTransaction'])
                ->findOrFail($id);

            if ($sale->status !== 'completed' || (float) $sale->return_total > 0) {
                throw ValidationException::withMessages([
                    'sale' => 'Registre recebimentos apenas para vendas concluidas sem devolucoes.',
                ]);
            }

            $outstanding = round(max(0, (float) $sale->total - (float) $sale->paid_total), 2);
            $amount = round((float) $data['amount'], 2);

            if ($outstanding <= 0) {
                throw ValidationException::withMessages([
                    'sale' => 'Esta venda ja esta totalmente quitada.',
                ]);
            }

            if ($amount > $outstanding) {
                throw ValidationException::withMessages([
                    'amount' => 'O recebimento nao pode ser maior que o saldo pendente de R$ '.number_format($outstanding, 2, ',', '.').'.',
                ]);
            }

            $payment = $sale->payments()->create([
                'method' => $data['method'],
                'amount' => $amount,
                'installments' => max(1, (int) ($data['installments'] ?? 1)),
                'card_brand' => $data['card_brand'] ?? null,
                'acquirer' => $data['acquirer'] ?? null,
                'paid_at' => $data['paid_at'] ?? now(),
                'reference' => $data['reference'] ?? null,
                'transaction_reference' => $data['transaction_reference'] ?? $data['reference'] ?? null,
                'status' => 'paid',
                'notes' => $data['notes'] ?? null,
            ]);

            $this->recalculateTotals($sale->refresh());

            $sale = $sale->refresh()->load('financialTransaction');
            $this->syncFinancialTransactionPaymentStatus($sale, $payment->paid_at);

            $this->recordSaleEvent(
                $sale,
                'payment_received',
                null,
                null,
                $amount,
                'Recebimento registrado',
                null,
                [
                    'payment_id' => $payment->id,
                    'method' => $payment->method,
                    'reference' => $payment->reference,
                ],
                false
            );

            return $sale->refresh();
        });
    }

    public function cashierSummary(?string $from = null, ?string $to = null): array
    {
        [$start, $end] = $this->cashierRange($from, $to);

        $sales = Sale::query()
            ->with(['items', 'payments', 'tutor', 'patient', 'serviceOrder'])
            ->where('status', 'completed')
            ->whereBetween('sold_at', [$start, $end])
            ->latest('sold_at')
            ->get();

        $payments = SalePayment::query()
            ->with('sale')
            ->whereBetween('paid_at', [$start, $end])
            ->whereHas('sale', fn ($query) => $query->where('status', 'completed'))
            ->where('status', 'paid')
            ->get();

        $refundEvents = SaleEvent::query()
            ->where('event_type', 'refund')
            ->whereHas('sale')
            ->whereBetween('occurred_at', [$start, $end])
            ->get();

        $openSales = Sale::query()
            ->with(['tutor', 'patient'])
            ->where('status', 'completed')
            ->whereIn('payment_status', ['pending', 'partial'])
            ->latest('sold_at')
            ->limit(10)
            ->get();

        $draftSales = Sale::query()
            ->where('status', 'draft')
            ->whereBetween('sold_at', [$start, $end])
            ->count();

        $total = $sales->sum(fn (Sale $sale) => (float) $sale->total);
        $paid = $sales->sum(fn (Sale $sale) => (float) $sale->paid_total);
        $received = $payments->sum(fn (SalePayment $payment) => (float) $payment->amount);
        $cashReceived = $payments
            ->where('method', 'cash')
            ->sum(fn (SalePayment $payment) => (float) $payment->amount);
        $cashRefunds = $refundEvents
            ->filter(fn (SaleEvent $event) => ($event->metadata['refund_method'] ?? null) === 'cash')
            ->sum(fn (SaleEvent $event) => (float) $event->amount);
        $refunds = $refundEvents->sum(fn (SaleEvent $event) => (float) $event->amount);
        $change = $sales->sum(fn (Sale $sale) => (float) $sale->change_total);
        $pending = $sales->sum(fn (Sale $sale) => max(0, (float) $sale->total - (float) $sale->paid_total));

        return [
            'period' => [
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
                'label' => $start->isSameDay($end)
                    ? $start->format('d/m/Y')
                    : $start->format('d/m/Y').' a '.$end->format('d/m/Y'),
            ],
            'stats' => [
                'sales_count' => $sales->count(),
                'draft_sales_count' => $draftSales,
                'subtotal' => $sales->sum(fn (Sale $sale) => (float) $sale->subtotal),
                'discount' => $sales->sum(fn (Sale $sale) => (float) $sale->discount_total),
                'additions' => $sales->sum(fn (Sale $sale) => (float) $sale->additions_total),
                'total' => $total,
                'returns' => $sales->sum(fn (Sale $sale) => (float) $sale->return_total),
                'refunds' => $refunds,
                'net_received' => max(0, $received - $refunds),
                'paid_on_sales' => $paid,
                'received' => $received,
                'cash_received' => $cashReceived,
                'cash_refunds' => $cashRefunds,
                'non_cash_received' => max(0, $received - $cashReceived),
                'change' => $change,
                'cash_drawer' => max(0, $cashReceived - $change - $cashRefunds),
                'pending' => $pending,
                'average_ticket' => $sales->count() > 0 ? $total / $sales->count() : 0,
            ],
            'payments_by_method' => $this->paymentsByMethod($payments),
            'recent_sales' => $sales->take(20)->values(),
            'open_sales' => $openSales,
            'top_items' => $this->topSoldItems($sales),
            'closures' => CashRegisterClosure::query()
                ->latest('closed_at')
                ->limit(8)
                ->get(),
        ];
    }

    public function closeCashier(array $data): CashRegisterClosure
    {
        return DB::transaction(function () use ($data) {
            $summary = $this->cashierSummary($data['period_from'] ?? null, $data['period_to'] ?? null);
            $period = $summary['period'];
            $stats = $summary['stats'];

            $periodFrom = Carbon::parse($period['from'])->startOfDay();
            $periodTo = Carbon::parse($period['to'])->endOfDay();
            $expectedCash = round((float) $stats['cash_drawer'], 2);
            $expectedTotal = round((float) $stats['net_received'], 2);
            $countedCash = round((float) ($data['counted_cash'] ?? 0), 2);
            $countedTotal = round((float) ($data['counted_total'] ?? $expectedTotal), 2);
            $cashDifference = round($countedCash - $expectedCash, 2);
            $totalDifference = round($countedTotal - $expectedTotal, 2);

            return CashRegisterClosure::query()->create([
                'clinic_id' => $data['clinic_id'] ?? null,
                'unit_id' => $data['unit_id'] ?? null,
                'closed_by_user_id' => auth()->id(),
                'period_from' => $periodFrom,
                'period_to' => $periodTo,
                'closed_at' => now(),
                'expected_cash' => $expectedCash,
                'counted_cash' => $countedCash,
                'cash_difference' => $cashDifference,
                'expected_total' => $expectedTotal,
                'counted_total' => $countedTotal,
                'total_difference' => $totalDifference,
                'status' => abs($cashDifference) < 0.01 && abs($totalDifference) < 0.01
                    ? 'balanced'
                    : 'difference',
                'notes' => $data['notes'] ?? null,
                'metadata' => [
                    'period_label' => $period['label'],
                    'sales_count' => $stats['sales_count'],
                    'received' => $stats['received'],
                    'refunds' => $stats['refunds'],
                    'cash_received' => $stats['cash_received'],
                    'cash_refunds' => $stats['cash_refunds'],
                    'change' => $stats['change'],
                ],
            ]);
        });
    }

    private function syncItems(Sale $sale, array $items): void
    {
        foreach ($items as $item) {
            $normalized = $this->normalizeItem($item);

            if ($normalized === null) {
                continue;
            }

            $sale->items()->create($normalized);
        }
    }

    private function normalizeItem(array $item): ?array
    {
        $type = $item['type'] ?? 'product';
        $quantity = (float) ($item['quantity'] ?? 0);

        if ($quantity <= 0) {
            return null;
        }

        $productId = $type === 'product' ? ($item['product_id'] ?? null) : null;
        $serviceId = $type === 'service' ? ($item['petshop_service_id'] ?? null) : null;
        $description = trim((string) ($item['description'] ?? ''));
        $unitPrice = $item['unit_price'] ?? null;
        $product = null;
        $service = null;

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
                : (float) ($service?->base_price ?? 0);
        }

        if ($description === '') {
            return null;
        }

        $unitPrice = (float) ($unitPrice ?: 0);
        $originalUnitPrice = (float) ($item['original_unit_price'] ?? $product?->sale_price ?? $service?->base_price ?? $unitPrice);
        $costUnitPrice = $type === 'product'
            ? (float) ($product?->cost_price ?? 0)
            : 0.0;
        $grossTotal = round($quantity * $originalUnitPrice, 2);
        $itemDiscount = (float) ($item['discount_total'] ?? max(0, $grossTotal - round($quantity * $unitPrice, 2)));
        $netTotal = max(0, $grossTotal - $itemDiscount);
        $costTotal = round($quantity * $costUnitPrice, 2);
        $grossProfit = round($netTotal - $costTotal, 2);
        $grossMargin = $netTotal > 0 ? round(($grossProfit / $netTotal) * 100, 2) : null;

        return [
            'type' => $type,
            'product_id' => $productId,
            'petshop_service_id' => $serviceId,
            'service_order_item_id' => $item['service_order_item_id'] ?? null,
            'description' => $description,
            'barcode' => $product?->gtin ?: $product?->barcode,
            'sku' => $product?->sku,
            'product_name_snapshot' => $type === 'product' ? ($product?->name ?: $description) : $description,
            'brand_snapshot' => $product?->brand,
            'category_snapshot' => $type === 'service' ? 'Servico PetShop' : $product?->category,
            'manufacturer_snapshot' => $product?->manufacturer,
            'unit_snapshot' => $product?->unit ?: 'un',
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'cost_unit_price' => $costUnitPrice,
            'original_unit_price' => $originalUnitPrice,
            'discount_total' => $itemDiscount,
            'gross_total' => $grossTotal,
            'net_total' => $netTotal,
            'gross_profit_total' => $grossProfit,
            'gross_margin_percent' => $grossMargin,
            'total' => $netTotal,
            'metadata' => array_filter([
                'source' => $item['source'] ?? null,
                'manual_description' => $type === 'custom',
            ], fn ($value) => $value !== null),
        ];
    }

    private function saleItemNetTotal(SaleItem $item): float
    {
        $netTotal = (float) $item->net_total;

        return $netTotal > 0 ? $netTotal : (float) $item->total;
    }

    private function hasBillableItems(array $items): bool
    {
        foreach ($items as $item) {
            $quantity = (float) ($item['quantity'] ?? 0);
            $description = trim((string) ($item['description'] ?? ''));

            if (
                $quantity > 0
                && ($description !== '' || ! empty($item['product_id']) || ! empty($item['petshop_service_id']))
            ) {
                return true;
            }
        }

        return false;
    }

    private function serviceOrderWithItems(mixed $serviceOrderId): ?ServiceOrder
    {
        if (! $serviceOrderId) {
            return null;
        }

        return ServiceOrder::query()
            ->with('items')
            ->find($serviceOrderId);
    }

    private function itemsFromServiceOrder(ServiceOrder $serviceOrder): array
    {
        return $serviceOrder->items
            ->map(fn ($item) => [
                'type' => $item->type,
                'product_id' => $item->product_id,
                'petshop_service_id' => $item->petshop_service_id,
                'service_order_item_id' => $item->id,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
            ])
            ->toArray();
    }

    private function syncPayments(Sale $sale, array $payments): void
    {
        foreach ($payments as $payment) {
            $method = $payment['method'] ?? null;
            $amount = (float) ($payment['amount'] ?? 0);

            if (! $method || $amount <= 0) {
                continue;
            }

            $status = $payment['status'] ?? 'paid';

            $sale->payments()->create([
                'method' => $method,
                'amount' => $amount,
                'installments' => max(1, (int) ($payment['installments'] ?? 1)),
                'card_brand' => $payment['card_brand'] ?? null,
                'acquirer' => $payment['acquirer'] ?? null,
                'paid_at' => $status === 'paid' ? ($payment['paid_at'] ?? now()) : null,
                'reference' => $payment['reference'] ?? null,
                'transaction_reference' => $payment['transaction_reference'] ?? $payment['reference'] ?? null,
                'status' => $status,
                'notes' => $payment['notes'] ?? null,
            ]);
        }
    }

    private function recalculateTotals(Sale $sale): void
    {
        $sale->load(['items', 'payments']);

        $subtotal = (float) $sale->items->sum(fn (SaleItem $item) => $this->saleItemNetTotal($item));
        $discount = (float) ($sale->discount_total ?? 0);
        $additions = (float) ($sale->additions_total ?? 0);
        $total = max(0, $subtotal + $additions - $discount);
        $paid = (float) $sale->payments
            ->filter(fn (SalePayment $payment) => ($payment->status ?? 'paid') === 'paid')
            ->sum('amount');
        $itemCostTotal = (float) $sale->items->sum(fn ($item) => (float) $item->cost_unit_price * (float) $item->quantity);
        $grossProfit = round($total - $itemCostTotal, 2);

        $updates = [
            'subtotal' => $subtotal,
            'discount_total' => $discount,
            'additions_total' => $additions,
            'total' => $total,
            'paid_total' => $paid,
            'change_total' => max(0, $paid - $total),
            'cost_total' => round($itemCostTotal, 2),
            'gross_profit_total' => $grossProfit,
            'gross_margin_percent' => $total > 0 ? round(($grossProfit / $total) * 100, 2) : null,
            'payment_status' => $this->paymentStatus($total, $paid),
        ];

        if ($sale->status === 'completed' && ! $sale->completed_at) {
            $updates['completed_at'] = $sale->sold_at ?? now();
        }

        if ($sale->status === 'cancelled' && ! $sale->cancelled_at) {
            $updates['cancelled_at'] = now();
        }

        $sale->update($updates);
    }

    private function paymentStatus(float $total, float $paid): string
    {
        if ($total <= 0 || $paid >= $total) {
            return 'paid';
        }

        if ($paid > 0) {
            return 'partial';
        }

        return 'pending';
    }

    private function applyCompletionEffects(Sale $sale): void
    {
        if ($sale->status !== 'completed') {
            return;
        }

        if (! $sale->stock_applied) {
            $this->applyStockMovements($sale);
            $sale->update(['stock_applied' => true]);
        }

        if (! $sale->financial_applied) {
            $financialTransaction = FinancialTransaction::query()->create([
                'clinic_id' => $sale->clinic_id,
                'type' => 'income',
                'description' => 'Venda '.$sale->code,
                'amount' => $sale->total,
                'due_date' => optional($sale->sold_at)->toDateString() ?: today(),
                'paid_at' => $sale->payment_status === 'paid' ? ($sale->sold_at ?? now()) : null,
                'status' => $sale->payment_status === 'paid' ? 'paid' : 'pending',
            ]);

            $sale->update([
                'financial_transaction_id' => $financialTransaction->id,
                'financial_applied' => true,
            ]);
        }

        if ($sale->service_order_id) {
            ServiceOrder::query()
                ->whereKey($sale->service_order_id)
                ->whereNotIn('status', ['finished', 'cancelled'])
                ->update([
                    'status' => 'finished',
                    'closed_at' => $sale->sold_at ?? now(),
                ]);
        }

        $this->recordSaleEvent($sale, 'completed', null, null, (float) $sale->total, 'Venda concluida');
    }

    private function syncFinancialTransactionPaymentStatus(Sale $sale, mixed $paidAt): void
    {
        $financialTransaction = $sale->financialTransaction;

        if (! $financialTransaction) {
            return;
        }

        $isPaid = $sale->payment_status === 'paid';

        $financialTransaction->update([
            'status' => $isPaid ? 'paid' : 'pending',
            'paid_at' => $isPaid ? $paidAt : null,
        ]);
    }

    private function applyStockMovements(Sale $sale): void
    {
        $sale->loadMissing('items.product');

        foreach ($sale->items as $item) {
            if ($item->type !== 'product' || ! $item->product_id) {
                continue;
            }

            $product = $item->product;
            $allocations = $product
                ? $this->lotService->allocateForSale($product, (float) $item->quantity)
                : [];

            if ($allocations === []) {
                $allocations[] = [
                    'quantity' => (float) $item->quantity,
                    'lot_number' => null,
                    'expires_at' => null,
                ];
            }

            foreach ($allocations as $allocation) {
                $movement = $this->inventoryMovementService->create([
                    'clinic_id' => $sale->clinic_id,
                    'product_id' => $item->product_id,
                    'sale_id' => $sale->id,
                    'sale_item_id' => $item->id,
                    'type' => 'exit',
                    'quantity' => $allocation['quantity'],
                    'unit_cost' => $item->cost_unit_price,
                    'lot_number' => $allocation['lot_number'],
                    'expires_at' => $allocation['expires_at'],
                    'occurred_at' => $sale->sold_at ?? now(),
                    'reason' => 'Venda '.$sale->code,
                    'source' => 'sale',
                    'notes' => $item->description,
                    'metadata' => [
                        'sale_code' => $sale->code,
                        'sale_item_description' => $item->description,
                    ],
                ]);

                $this->recordSaleEvent(
                    $sale,
                    'stock_exit',
                    $item->id,
                    $movement->id,
                    round((float) $allocation['quantity'] * (float) $item->unit_price, 2),
                    'Baixa de estoque da venda',
                    (float) $allocation['quantity'],
                    [
                        'lot_number' => $allocation['lot_number'],
                        'expires_at' => $allocation['expires_at'],
                        'product_id' => $item->product_id,
                    ]
                );
            }
        }
    }

    private function restoreStockForItem(
        Sale $sale,
        SaleItem $item,
        float $quantity,
        string $source,
        string $reason,
        string $eventType
    ): void {
        if ($item->type !== 'product' || ! $item->product_id || $quantity <= 0) {
            return;
        }

        $originalMovements = InventoryMovement::query()
            ->where('sale_id', $sale->id)
            ->where('sale_item_id', $item->id)
            ->where('type', 'exit')
            ->where('source', 'sale')
            ->oldest('occurred_at')
            ->get();

        if ($originalMovements->isEmpty()) {
            $movement = $this->inventoryMovementService->create([
                'clinic_id' => $sale->clinic_id,
                'product_id' => $item->product_id,
                'sale_id' => $sale->id,
                'sale_item_id' => $item->id,
                'type' => 'entry',
                'quantity' => $quantity,
                'unit_cost' => $item->cost_unit_price,
                'occurred_at' => now(),
                'reason' => $reason,
                'source' => $source,
                'notes' => $item->description,
                'metadata' => [
                    'sale_code' => $sale->code,
                    'sale_item_description' => $item->description,
                    'fallback_restore' => true,
                ],
            ]);

            $this->recordSaleEvent(
                $sale,
                $eventType,
                $item->id,
                $movement->id,
                round($quantity * (float) $item->unit_price, 2),
                $reason,
                $quantity,
                ['source' => $source]
            );

            return;
        }

        $remaining = round($quantity, 3);
        $restoredByMovement = $this->restoredQuantitiesByOriginalMovement($sale, $item);

        foreach ($originalMovements as $originalMovement) {
            if ($remaining <= 0) {
                break;
            }

            $alreadyRestored = (float) ($restoredByMovement[$originalMovement->id] ?? 0);
            $available = max(0, (float) $originalMovement->quantity - $alreadyRestored);
            $take = min($remaining, $available);

            if ($take <= 0) {
                continue;
            }

            $movement = $this->inventoryMovementService->create([
                'clinic_id' => $sale->clinic_id,
                'product_id' => $item->product_id,
                'sale_id' => $sale->id,
                'sale_item_id' => $item->id,
                'type' => 'entry',
                'quantity' => $take,
                'unit_cost' => $originalMovement->unit_cost ?: $item->cost_unit_price,
                'lot_number' => $originalMovement->lot_number,
                'expires_at' => $originalMovement->expires_at,
                'occurred_at' => now(),
                'reason' => $reason,
                'source' => $source,
                'notes' => $item->description,
                'metadata' => [
                    'sale_code' => $sale->code,
                    'sale_item_description' => $item->description,
                    'original_movement_id' => $originalMovement->id,
                    'original_source' => $originalMovement->source,
                ],
            ]);

            $this->recordSaleEvent(
                $sale,
                $eventType,
                $item->id,
                $movement->id,
                round($take * (float) $item->unit_price, 2),
                $reason,
                $take,
                [
                    'source' => $source,
                    'lot_number' => $originalMovement->lot_number,
                    'expires_at' => optional($originalMovement->expires_at)->format('Y-m-d'),
                    'original_movement_id' => $originalMovement->id,
                ]
            );

            $remaining = round($remaining - $take, 3);
        }
    }

    private function restoredQuantitiesByOriginalMovement(Sale $sale, SaleItem $item): array
    {
        return InventoryMovement::query()
            ->where('sale_id', $sale->id)
            ->where('sale_item_id', $item->id)
            ->where('type', 'entry')
            ->whereIn('source', ['sale_return', 'sale_cancellation'])
            ->get()
            ->reduce(function (array $carry, InventoryMovement $movement) {
                $originalMovementId = $movement->metadata['original_movement_id'] ?? null;

                if (! $originalMovementId) {
                    return $carry;
                }

                $carry[$originalMovementId] = ($carry[$originalMovementId] ?? 0) + (float) $movement->quantity;

                return $carry;
            }, []);
    }

    private function recordSaleEvent(
        Sale $sale,
        string $eventType,
        ?int $saleItemId = null,
        ?int $inventoryMovementId = null,
        ?float $amount = null,
        ?string $reason = null,
        ?float $quantity = null,
        array $metadata = [],
        bool $deduplicate = true
    ): void {
        $exists = $deduplicate && $sale->events()
            ->where('event_type', $eventType)
            ->where('sale_item_id', $saleItemId)
            ->where('inventory_movement_id', $inventoryMovementId)
            ->exists();

        if ($exists) {
            return;
        }

        $sale->events()->create([
            'sale_item_id' => $saleItemId,
            'inventory_movement_id' => $inventoryMovementId,
            'event_type' => $eventType,
            'quantity' => $quantity,
            'amount' => $amount,
            'reason' => $reason,
            'metadata' => $metadata,
            'occurred_at' => in_array($eventType, ['completed', 'stock_exit'], true)
                ? ($sale->sold_at ?? now())
                : now(),
        ]);
    }

    private function nextCode(): string
    {
        $nextId = ((int) Sale::withTrashed()
            ->withoutGlobalScope('clinic_tenant')
            ->max('id')) + 1;

        do {
            $code = 'VEN-'.str_pad((string) $nextId, 6, '0', STR_PAD_LEFT);
            $nextId++;
        } while (Sale::withTrashed()
            ->withoutGlobalScope('clinic_tenant')
            ->where('code', $code)
            ->exists());

        return $code;
    }

    private function cashierRange(?string $from, ?string $to): array
    {
        $start = $this->parseCashierDate($from)?->startOfDay() ?? today()->startOfDay();
        $end = $this->parseCashierDate($to)?->endOfDay() ?? $start->copy()->endOfDay();

        if ($end->lt($start)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        return [$start, $end];
    }

    private function parseCashierDate(?string $date): ?Carbon
    {
        if (! $date) {
            return null;
        }

        try {
            return Carbon::parse($date);
        } catch (\Throwable) {
            return null;
        }
    }

    private function paymentsByMethod($payments): array
    {
        return $payments
            ->groupBy('method')
            ->map(fn ($items, string $method) => [
                'method' => $method,
                'label' => $this->paymentMethodLabel($method),
                'amount' => $items->sum(fn (SalePayment $payment) => (float) $payment->amount),
                'count' => $items->count(),
            ])
            ->sortByDesc('amount')
            ->values()
            ->all();
    }

    private function topSoldItems($sales): array
    {
        return $sales
            ->flatMap(fn (Sale $sale) => $sale->items)
            ->groupBy(fn ($item) => $item->type.'|'.$item->description)
            ->map(fn ($items) => [
                'type' => $items->first()->type,
                'description' => $items->first()->description,
                'quantity' => $items->sum(fn ($item) => (float) $item->quantity),
                'total' => $items->sum(fn ($item) => (float) $item->total),
            ])
            ->sortByDesc('total')
            ->take(8)
            ->values()
            ->all();
    }

    private function paymentMethodLabel(string $method): string
    {
        return match ($method) {
            'cash' => 'Dinheiro',
            'pix' => 'Pix',
            'debit_card' => 'Cartao debito',
            'credit_card' => 'Cartao credito',
            'transfer' => 'Transferencia',
            default => 'Outro',
        };
    }
}
