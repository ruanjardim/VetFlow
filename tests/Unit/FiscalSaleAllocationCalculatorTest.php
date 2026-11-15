<?php

namespace Tests\Unit;

use App\Modules\Fiscal\Services\FiscalSaleAllocationCalculator;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FiscalSaleAllocationCalculatorTest extends TestCase
{
    #[Test]
    public function it_allocates_mixed_adjustments_payments_and_change_with_exact_cents(): void
    {
        $result = app(FiscalSaleAllocationCalculator::class)->calculate(
            [
                ['source_id' => 10, 'type' => 'product', 'base_total_cents' => 10001, 'line_discount_cents' => 0],
                ['source_id' => 11, 'type' => 'product', 'base_total_cents' => 4999, 'line_discount_cents' => 0],
                ['source_id' => 20, 'type' => 'service', 'base_total_cents' => 5000, 'line_discount_cents' => 0],
            ],
            [
                $this->payment(1, 12000, 'cash'),
                $this->payment(2, 8500, 'pix'),
            ],
            [
                'subtotal_cents' => 20000,
                'discount_total_cents' => 1000,
                'additions_total_cents' => 400,
                'delivery_fee_cents' => 600,
                'total_cents' => 20000,
                'paid_total_cents' => 20500,
                'change_total_cents' => 500,
            ]
        );

        $this->assertSame('151.50', data_get($result, 'documents.nfce.total'));
        $this->assertSame('48.50', data_get($result, 'documents.nfse.total'));
        $this->assertSame('156.50', data_get($result, 'documents.nfce.paid_total'));
        $this->assertSame('48.50', data_get($result, 'documents.nfse.paid_total'));
        $this->assertSame('5.00', data_get($result, 'documents.nfce.change_total'));
        $this->assertSame('0.00', data_get($result, 'documents.nfse.change_total'));
        $this->assertSame('200.00', data_get($result, 'reconciliation.documents_total'));

        $nfcePayments = collect(data_get($result, 'documents.nfce.payments'))->sum(fn (array $payment): float => (float) $payment['amount']);
        $nfsePayments = collect(data_get($result, 'documents.nfse.payments'))->sum(fn (array $payment): float => (float) $payment['amount']);
        $this->assertEqualsWithDelta(156.50, $nfcePayments, 0.001);
        $this->assertEqualsWithDelta(48.50, $nfsePayments, 0.001);
    }

    #[Test]
    public function it_assigns_rounding_remainders_deterministically(): void
    {
        $result = app(FiscalSaleAllocationCalculator::class)->calculate(
            [
                ['source_id' => 1, 'type' => 'product', 'base_total_cents' => 1, 'line_discount_cents' => 0],
                ['source_id' => 2, 'type' => 'product', 'base_total_cents' => 1, 'line_discount_cents' => 0],
                ['source_id' => 3, 'type' => 'service', 'base_total_cents' => 1, 'line_discount_cents' => 0],
            ],
            [],
            [
                'subtotal_cents' => 3,
                'discount_total_cents' => 1,
                'additions_total_cents' => 0,
                'delivery_fee_cents' => 0,
                'total_cents' => 2,
                'paid_total_cents' => 0,
                'change_total_cents' => 0,
            ]
        );

        $this->assertSame('0.01', data_get($result, 'documents.nfce.total'));
        $this->assertSame('0.01', data_get($result, 'documents.nfse.total'));
        $this->assertSame('0.01', data_get($result, 'documents.nfce.items.0.allocated_sale_discount'));
        $this->assertSame('0.00', data_get($result, 'documents.nfce.items.1.allocated_sale_discount'));
    }

    #[Test]
    public function it_rejects_a_sale_whose_totals_do_not_close(): void
    {
        $this->expectException(ValidationException::class);

        app(FiscalSaleAllocationCalculator::class)->calculate(
            [['source_id' => 1, 'type' => 'product', 'base_total_cents' => 1000, 'line_discount_cents' => 0]],
            [],
            [
                'subtotal_cents' => 1000,
                'discount_total_cents' => 0,
                'additions_total_cents' => 0,
                'delivery_fee_cents' => 0,
                'total_cents' => 999,
                'paid_total_cents' => 0,
                'change_total_cents' => 0,
            ]
        );
    }

    #[Test]
    public function it_keeps_large_cent_allocations_inside_integer_arithmetic(): void
    {
        $result = app(FiscalSaleAllocationCalculator::class)->calculate(
            [
                ['source_id' => 1, 'type' => 'product', 'base_total_cents' => 5_000_000_000, 'line_discount_cents' => 0],
                ['source_id' => 2, 'type' => 'service', 'base_total_cents' => 4_000_000_000, 'line_discount_cents' => 0],
            ],
            [],
            [
                'subtotal_cents' => 9_000_000_000,
                'discount_total_cents' => 1_000_000_000,
                'additions_total_cents' => 0,
                'delivery_fee_cents' => 0,
                'total_cents' => 8_000_000_000,
                'paid_total_cents' => 0,
                'change_total_cents' => 0,
            ]
        );

        $this->assertSame('44444444.44', data_get($result, 'documents.nfce.total'));
        $this->assertSame('35555555.56', data_get($result, 'documents.nfse.total'));
        $this->assertSame('80000000.00', data_get($result, 'reconciliation.documents_total'));
    }

    /** @return array<string, mixed> */
    private function payment(int $id, int $amount, string $method): array
    {
        return [
            'source_id' => $id,
            'method' => $method,
            'method_name' => ucfirst($method),
            'amount_cents' => $amount,
            'installments' => 1,
            'acquirer' => null,
            'card_brand' => null,
            'reference' => null,
            'paid_at' => null,
        ];
    }
}
