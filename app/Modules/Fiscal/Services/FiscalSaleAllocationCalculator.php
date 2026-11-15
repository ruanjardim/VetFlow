<?php

namespace App\Modules\Fiscal\Services;

use Illuminate\Validation\ValidationException;

class FiscalSaleAllocationCalculator
{
    public const SCHEMA_VERSION = 1;

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<int, array<string, mixed>>  $payments
     * @return array<string, mixed>
     */
    public function calculate(array $items, array $payments, array $saleTotals): array
    {
        $items = collect($items)
            ->sortBy(fn (array $item): string => sprintf('%s:%020d', (string) $item['type'], (int) $item['source_id']))
            ->values()
            ->all();
        $groups = collect($items)->groupBy('type');
        $types = collect(['product', 'service'])->filter(fn (string $type): bool => $groups->has($type))->values()->all();

        if ($types === [] || collect($items)->contains(fn (array $item): bool => ! in_array($item['type'] ?? null, ['product', 'service'], true))) {
            $this->fail('O rateio fiscal aceita somente linhas vinculadas a produtos ou serviços.');
        }

        $subtotal = array_sum(array_map(fn (array $item): int => (int) $item['base_total_cents'], $items));
        $discount = (int) ($saleTotals['discount_total_cents'] ?? 0);
        $additions = (int) ($saleTotals['additions_total_cents'] ?? 0);
        $delivery = (int) ($saleTotals['delivery_fee_cents'] ?? 0);
        $saleTotal = (int) ($saleTotals['total_cents'] ?? 0);
        $paidTotal = (int) ($saleTotals['paid_total_cents'] ?? 0);
        $changeTotal = (int) ($saleTotals['change_total_cents'] ?? 0);

        if ($subtotal !== (int) ($saleTotals['subtotal_cents'] ?? -1)) {
            $this->fail('O subtotal da venda não confere com a soma das linhas.');
        }
        if (min($subtotal, $discount, $additions, $delivery, $saleTotal, $paidTotal, $changeTotal) < 0) {
            $this->fail('A venda possui valor negativo e não pode ser rateada.');
        }
        if ($discount > $subtotal) {
            $this->fail('O desconto global não pode superar o subtotal das linhas.');
        }
        if ($subtotal + $additions + $delivery - $discount !== $saleTotal) {
            $this->fail('Subtotal, desconto, acréscimos, entrega e total da venda não fecham em centavos.');
        }

        $paymentSum = array_sum(array_map(fn (array $payment): int => (int) $payment['amount_cents'], $payments));
        if ($paymentSum !== $paidTotal) {
            $this->fail('Os pagamentos recebidos não conferem com o total pago da venda.');
        }
        if ($changeTotal !== max(0, $paidTotal - $saleTotal)) {
            $this->fail('O troco da venda não confere com o valor pago.');
        }

        $groupBases = [];
        foreach ($types as $type) {
            $groupBases[$type] = (int) collect($groups[$type])->sum('base_total_cents');
        }

        $discounts = $this->allocateByWeights($discount, $groupBases);
        $additionShares = $this->allocateByWeights($additions, $groupBases);
        $deliveryShares = array_fill_keys($types, 0);
        $deliveryType = in_array('product', $types, true) ? 'product' : 'service';
        $deliveryShares[$deliveryType] = $delivery;

        $documents = [];
        foreach ($types as $type) {
            $groupItems = collect($groups[$type])->values()->all();
            $itemWeights = collect($groupItems)->mapWithKeys(
                fn (array $item): array => [(string) $item['source_id'] => (int) $item['base_total_cents']]
            )->all();
            $itemDiscounts = $this->allocateByWeights($discounts[$type], $itemWeights);
            $itemAdditions = $this->allocateByWeights($additionShares[$type], $itemWeights);
            $itemDelivery = $this->allocateByWeights($deliveryShares[$type], $itemWeights);
            $allocatedItems = [];

            foreach ($groupItems as $item) {
                $key = (string) $item['source_id'];
                $final = (int) $item['base_total_cents'] - $itemDiscounts[$key] + $itemAdditions[$key] + $itemDelivery[$key];
                if ($final < 0) {
                    $this->fail('O rateio produziria uma linha fiscal com valor negativo.');
                }

                $allocatedItems[] = [
                    'source_id' => (int) $item['source_id'],
                    'sale_line_total' => $this->money((int) $item['base_total_cents']),
                    'line_discount_total' => $this->money((int) $item['line_discount_cents']),
                    'allocated_sale_discount' => $this->money($itemDiscounts[$key]),
                    'allocated_additions_total' => $this->money($itemAdditions[$key]),
                    'allocated_delivery_fee' => $this->money($itemDelivery[$key]),
                    'total' => $this->money($final),
                ];
            }

            $documentTotal = $groupBases[$type] - $discounts[$type] + $additionShares[$type] + $deliveryShares[$type];
            if ($documentTotal < 0) {
                $this->fail('O rateio produziria um documento fiscal com total negativo.');
            }

            $documents[$this->documentType($type)] = [
                'type' => $this->documentType($type),
                'item_type' => $type,
                'selected_items_total' => $this->money($groupBases[$type]),
                'allocated_sale_discount' => $this->money($discounts[$type]),
                'allocated_additions_total' => $this->money($additionShares[$type]),
                'allocated_delivery_fee' => $this->money($deliveryShares[$type]),
                'total' => $this->money($documentTotal),
                'paid_total' => '0.00',
                'change_total' => '0.00',
                'items' => $allocatedItems,
                'payments' => [],
            ];
        }

        $documents = $this->allocatePayments($documents, $payments, $paidTotal, $saleTotal, $changeTotal);
        $documentTotal = (int) collect($documents)->sum(fn (array $document): int => $this->cents($document['total']));
        if ($documentTotal !== $saleTotal) {
            $this->fail('Os documentos rateados não reconciliam com o total da venda.');
        }

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'rules' => [
                'adjustments' => 'proportional_to_sale_line_net_total',
                'delivery' => $deliveryType === 'product' ? 'products_document' : 'services_document',
                'payments' => 'proportional_to_document_total',
                'change' => in_array('product', $types, true) ? 'products_document' : 'services_document',
                'rounding' => 'largest_remainder_in_cents',
            ],
            'documents' => $documents,
            'reconciliation' => [
                'sale_total' => $this->money($saleTotal),
                'documents_total' => $this->money($documentTotal),
                'paid_total' => $this->money($paidTotal),
                'change_total' => $this->money($changeTotal),
            ],
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $documents
     * @param  array<int, array<string, mixed>>  $payments
     * @return array<string, array<string, mixed>>
     */
    private function allocatePayments(array $documents, array $payments, int $paidTotal, int $saleTotal, int $changeTotal): array
    {
        $types = array_keys($documents);
        $documentTotals = collect($documents)->map(fn (array $document): int => $this->cents($document['total']))->all();
        $covered = min($paidTotal, $saleTotal);
        $targets = $this->allocateByWeights($covered, $documentTotals);
        $changeType = array_key_exists('nfce', $documents) ? 'nfce' : $types[0];
        $targets[$changeType] += $changeTotal;
        $paymentAllocations = array_fill_keys($types, []);

        if (count($types) === 1) {
            foreach ($payments as $payment) {
                $paymentAllocations[$types[0]][] = $this->paymentSnapshot($payment, (int) $payment['amount_cents']);
            }
        } elseif ($payments !== []) {
            $firstType = $types[0];
            $secondType = $types[1];
            $paymentWeights = collect($payments)->mapWithKeys(
                fn (array $payment): array => [(string) $payment['source_id'] => (int) $payment['amount_cents']]
            )->all();
            $firstShares = $this->allocateByWeights($targets[$firstType], $paymentWeights);

            foreach ($payments as $payment) {
                $key = (string) $payment['source_id'];
                $firstAmount = $firstShares[$key];
                $secondAmount = (int) $payment['amount_cents'] - $firstAmount;
                if ($firstAmount > 0) {
                    $paymentAllocations[$firstType][] = $this->paymentSnapshot($payment, $firstAmount);
                }
                if ($secondAmount > 0) {
                    $paymentAllocations[$secondType][] = $this->paymentSnapshot($payment, $secondAmount);
                }
            }
        }

        foreach ($types as $type) {
            $allocated = array_sum(array_map(fn (array $payment): int => $this->cents($payment['amount']), $paymentAllocations[$type]));
            if ($allocated !== $targets[$type]) {
                $this->fail('Os pagamentos não puderam ser reconciliados entre os documentos.');
            }

            $documents[$type]['paid_total'] = $this->money($allocated);
            $documents[$type]['change_total'] = $this->money($type === $changeType ? $changeTotal : 0);
            $documents[$type]['payments'] = $paymentAllocations[$type];
        }

        return $documents;
    }

    /** @param array<string, mixed> $payment @return array<string, mixed> */
    private function paymentSnapshot(array $payment, int $amount): array
    {
        return [
            'source_id' => (int) $payment['source_id'],
            'method' => $payment['method'],
            'method_name' => $payment['method_name'],
            'source_amount' => $this->money((int) $payment['amount_cents']),
            'amount' => $this->money($amount),
            'installments' => $payment['installments'],
            'acquirer' => $payment['acquirer'],
            'card_brand' => $payment['card_brand'],
            'reference' => $payment['reference'],
            'paid_at' => $payment['paid_at'],
            'allocation' => 'approved_fiscal_sale_allocation',
        ];
    }

    /**
     * @param  array<string, int>  $weights
     * @return array<string, int>
     */
    private function allocateByWeights(int $amount, array $weights): array
    {
        $keys = array_keys($weights);
        $result = array_fill_keys($keys, 0);
        if ($amount === 0) {
            return $result;
        }

        $weightTotal = array_sum($weights);
        if ($weightTotal <= 0) {
            $this->fail('Não há base positiva para distribuir os valores da venda.');
        }

        $remainders = [];
        $allocated = 0;
        foreach ($weights as $key => $weight) {
            [$share, $remainder] = $this->multiplyDivide($amount, $weight, $weightTotal);
            $result[$key] = $share;
            $allocated += $share;
            $remainders[$key] = $remainder;
        }

        $remaining = $amount - $allocated;
        $order = array_keys($weights);
        usort($order, function (string|int $left, string|int $right) use ($remainders): int {
            $comparison = $remainders[$right] <=> $remainders[$left];

            return $comparison !== 0 ? $comparison : strcmp((string) $left, (string) $right);
        });

        for ($index = 0; $index < $remaining; $index++) {
            $result[$order[$index % count($order)]]++;
        }

        return $result;
    }

    /** @return array{int, int} Exact quotient and remainder of ($left * $right) / $divisor without overflowing. */
    private function multiplyDivide(int $left, int $right, int $divisor): array
    {
        $quotient = 0;
        $remainder = 0;
        $baseQuotient = intdiv($left, $divisor);
        $baseRemainder = $left % $divisor;

        while ($right > 0) {
            if (($right & 1) === 1) {
                $quotient += $baseQuotient;
                $remainder += $baseRemainder;
                if ($remainder >= $divisor) {
                    $quotient += intdiv($remainder, $divisor);
                    $remainder %= $divisor;
                }
            }

            $right = intdiv($right, 2);
            if ($right === 0) {
                break;
            }

            $baseQuotient *= 2;
            $baseRemainder *= 2;
            if ($baseRemainder >= $divisor) {
                $baseQuotient += intdiv($baseRemainder, $divisor);
                $baseRemainder %= $divisor;
            }
        }

        return [$quotient, $remainder];
    }

    private function documentType(string $itemType): string
    {
        return $itemType === 'product' ? 'nfce' : 'nfse';
    }

    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private function cents(string|int|float $value): int
    {
        return (int) round((float) $value * 100);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['allocation' => $message]);
    }
}
