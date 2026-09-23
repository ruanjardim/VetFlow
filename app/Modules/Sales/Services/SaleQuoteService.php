<?php

namespace App\Modules\Sales\Services;

use App\Modules\PetShopServices\Models\PetShopService;
use App\Modules\Products\Models\Product;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleQuote;
use App\Modules\Sales\Models\SaleQuoteItem;
use App\Modules\Sales\Support\SaleType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * PDV quotes ("orçamentos"). A quote never touches stock, money or
 * commissions: it only stores the proposed items and prices. Converting it
 * opens the PDV prefilled, and the resulting sale marks it as converted.
 */
class SaleQuoteService
{
    public const DEFAULT_VALIDITY_DAYS = 7;

    public function create(array $data): SaleQuote
    {
        return DB::transaction(function () use ($data) {
            $items = $data['items'] ?? [];
            unset($data['items']);

            $data = SaleType::normalizeDelivery($data);
            $data['code'] = $this->nextCode();
            $data['status'] = 'open';
            $data['valid_until'] = $this->validUntil($data['valid_until'] ?? null);
            $data['seller_user_id'] = $data['seller_user_id'] ?? auth()->id();
            $data['discount_total'] = round((float) ($data['discount_total'] ?? 0), 2);
            $data['additions_total'] = round((float) ($data['additions_total'] ?? 0), 2);
            $data['metadata'] = ['created_by_user_id' => auth()->id()];

            /** @var SaleQuote $quote */
            $quote = SaleQuote::query()->create($data);

            $this->syncItems($quote, $items);
            $this->recalculateTotals($quote);

            return $quote->refresh();
        });
    }

    public function update(SaleQuote $quote, array $data): SaleQuote
    {
        $this->ensureEditable($quote);

        return DB::transaction(function () use ($quote, $data) {
            $items = $data['items'] ?? [];
            unset($data['items'], $data['clinic_id']);

            $data = SaleType::normalizeDelivery($data);
            $data['valid_until'] = $this->validUntil($data['valid_until'] ?? null, $quote->valid_until);
            $data['discount_total'] = round((float) ($data['discount_total'] ?? 0), 2);
            $data['additions_total'] = round((float) ($data['additions_total'] ?? 0), 2);
            $data['metadata'] = array_merge($quote->metadata ?? [], ['updated_by_user_id' => auth()->id()]);

            $quote->update($data);
            $quote->items()->delete();

            $this->syncItems($quote, $items);
            $this->recalculateTotals($quote);

            return $quote->refresh();
        });
    }

    public function cancel(SaleQuote $quote, ?string $reason = null): SaleQuote
    {
        $this->ensureEditable($quote);

        $quote->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => trim((string) $reason) ?: null,
        ]);

        return $quote->refresh();
    }

    /**
     * Called when a sale created from the quote is completed.
     */
    public function markConverted(Sale $sale): void
    {
        $quote = SaleQuote::query()->find($sale->sale_quote_id);

        if (! $quote || $quote->status !== 'open') {
            return;
        }

        $quote->update([
            'status' => 'converted',
            'converted_sale_id' => $sale->id,
            'converted_at' => $sale->completed_at ?? $sale->sold_at ?? now(),
        ]);
    }

    /**
     * A cancelled sale gives the quote back, so it can be converted again.
     */
    public function reopenAfterCancelledSale(Sale $sale): void
    {
        $quote = SaleQuote::query()->find($sale->sale_quote_id);

        if (
            ! $quote
            || $quote->status !== 'converted'
            || (int) $quote->converted_sale_id !== (int) $sale->id
        ) {
            return;
        }

        $quote->update([
            'status' => 'open',
            'converted_sale_id' => null,
            'converted_at' => null,
        ]);
    }

    /**
     * Cart lines used to prefill the PDV, keeping the quoted prices.
     *
     * @return array<int, array<string, mixed>>
     */
    public function cartItems(SaleQuote $quote): array
    {
        return $quote->items
            ->map(fn (SaleQuoteItem $item) => [
                'type' => $item->type,
                'product_id' => $item->product_id,
                'petshop_service_id' => $item->petshop_service_id,
                'description' => $item->description,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'discount_total' => (float) $item->discount_total,
            ])
            ->values()
            ->all();
    }

    /**
     * wa.me link with a plain-text summary. Without a phone the link opens
     * WhatsApp so the operator picks the contact.
     */
    public function whatsappUrl(SaleQuote $quote): string
    {
        $quote->loadMissing(['items', 'tutor', 'clinic']);
        $money = fn ($value) => 'R$ '.number_format((float) $value, 2, ',', '.');
        $quantity = fn ($value) => rtrim(rtrim(number_format((float) $value, 3, ',', '.'), '0'), ',');
        $clinicName = $quote->clinic?->trade_name ?: $quote->clinic?->corporate_name;

        $lines = [
            'Olá'.($quote->tutor?->name ? ', '.$quote->tutor->name : '').'!',
            'Segue o orçamento '.$quote->code.($clinicName ? ' de '.$clinicName : '').':',
        ];

        foreach ($quote->items as $item) {
            $lines[] = '• '.$quantity($item->quantity).'x '.$item->description.': '.$money($item->total);
        }

        if ((float) $quote->delivery_fee > 0) {
            $lines[] = 'Taxa de entrega: '.$money($quote->delivery_fee);
        }

        if ((float) $quote->additions_total > 0) {
            $lines[] = 'Acréscimos: '.$money($quote->additions_total);
        }

        if ((float) $quote->discount_total > 0) {
            $lines[] = 'Desconto: -'.$money($quote->discount_total);
        }

        $lines[] = 'Total: '.$money($quote->total);
        $lines[] = 'Válido até '.$quote->valid_until?->format('d/m/Y').'.';

        $digits = $quote->tutor?->whatsappDigits();

        return 'https://wa.me/'.($digits ?? '').'?text='.rawurlencode(implode("\n", $lines));
    }

    private function ensureEditable(SaleQuote $quote): void
    {
        if ($quote->status !== 'open') {
            throw ValidationException::withMessages([
                'quote' => 'Apenas orçamentos abertos podem ser alterados ou cancelados.',
            ]);
        }

        $hasActiveSale = Sale::query()
            ->where('sale_quote_id', $quote->id)
            ->where('status', '!=', 'cancelled')
            ->exists();

        if ($hasActiveSale) {
            throw ValidationException::withMessages([
                'quote' => 'Este orçamento já tem uma venda em andamento. Conclua ou cancele a venda antes de alterá-lo.',
            ]);
        }
    }

    private function validUntil(mixed $value, ?Carbon $current = null): string
    {
        if ($value !== null && $value !== '') {
            return Carbon::parse($value)->toDateString();
        }

        if ($current) {
            return $current->toDateString();
        }

        $days = max(1, (int) config('sales.quote_validity_days', self::DEFAULT_VALIDITY_DAYS));

        return today()->addDays($days)->toDateString();
    }

    private function syncItems(SaleQuote $quote, array $items): void
    {
        foreach ($items as $item) {
            $normalized = $this->normalizeItem(is_array($item) ? $item : []);

            if ($normalized !== null) {
                $quote->items()->create($normalized);
            }
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeItem(array $item): ?array
    {
        $type = in_array($item['type'] ?? null, ['product', 'service', 'custom'], true)
            ? $item['type']
            : 'product';
        $quantity = round((float) ($item['quantity'] ?? 0), 3);

        if ($quantity <= 0) {
            return null;
        }

        $productId = $type === 'product' ? ($item['product_id'] ?? null) : null;
        $serviceId = $type === 'service' ? ($item['petshop_service_id'] ?? null) : null;
        $description = trim((string) ($item['description'] ?? ''));
        $unitPrice = $item['unit_price'] ?? null;

        if ($productId) {
            $product = Product::query()->find($productId);
            $description = $description ?: (string) $product?->name;
            $unitPrice = $unitPrice !== null && $unitPrice !== '' ? $unitPrice : $product?->sale_price;
        } elseif ($serviceId) {
            $service = PetShopService::query()->find($serviceId);
            $description = $description ?: (string) $service?->name;
            $unitPrice = $unitPrice !== null && $unitPrice !== '' ? $unitPrice : $service?->base_price;
        } else {
            $type = 'custom';
        }

        if ($description === '') {
            return null;
        }

        $unitPrice = round(max(0, (float) $unitPrice), 2);
        $gross = round($quantity * $unitPrice, 2);
        $discount = round(min($gross, max(0, (float) ($item['discount_total'] ?? 0))), 2);

        return [
            'type' => $type,
            'product_id' => $productId,
            'petshop_service_id' => $serviceId,
            'description' => mb_substr($description, 0, 255),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount_total' => $discount,
            'total' => round($gross - $discount, 2),
        ];
    }

    private function recalculateTotals(SaleQuote $quote): void
    {
        $quote->load('items');

        $subtotal = round((float) $quote->items->sum(fn (SaleQuoteItem $item) => (float) $item->total), 2);
        $discount = min($subtotal, (float) $quote->discount_total);
        $total = max(0, $subtotal + (float) $quote->additions_total + (float) $quote->delivery_fee - $discount);

        $quote->update([
            'subtotal' => $subtotal,
            'discount_total' => round($discount, 2),
            'total' => round($total, 2),
        ]);
    }

    private function nextCode(): string
    {
        $nextId = ((int) SaleQuote::withTrashed()
            ->withoutGlobalScope('clinic_tenant')
            ->max('id')) + 1;

        do {
            $code = 'ORC-'.str_pad((string) $nextId, 6, '0', STR_PAD_LEFT);
            $nextId++;
        } while (SaleQuote::withTrashed()
            ->withoutGlobalScope('clinic_tenant')
            ->where('code', $code)
            ->exists());

        return $code;
    }
}
