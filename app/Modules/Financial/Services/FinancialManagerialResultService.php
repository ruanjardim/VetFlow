<?php

namespace App\Modules\Financial\Services;

use App\Modules\Financial\Models\FinancialTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FinancialManagerialResultService
{
    public const BASIS_LABELS = [
        'cash' => 'Caixa (data do pagamento)',
        'competence' => 'Competência operacional (vencimento)',
    ];

    /**
     * @return array{
     *   filters: array{competence: string, basis: string},
     *   reference: array{start: CarbonImmutable, end: CarbonImmutable, label: string, date_label: string},
     *   stats: array{income_cents: int, expense_cents: int, balance_cents: int, income_count: int, expense_count: int, transactions_count: int, excluded_without_date: int},
     *   income_categories: Collection<int, array<string, int|string|float|null>>,
     *   expense_categories: Collection<int, array<string, int|string|float|null>>,
     *   rows: Collection<int, array<string, int|string|float|null>>
     * }
     */
    public function report(?string $competence = null, ?string $basis = null): array
    {
        $basis = array_key_exists((string) $basis, self::BASIS_LABELS) ? (string) $basis : 'cash';
        $reference = CarbonImmutable::createFromFormat(
            'Y-m-d',
            ($competence ?: now()->format('Y-m')).'-01'
        )->startOfMonth();
        $end = $reference->endOfMonth();
        $dateColumn = $basis === 'cash' ? 'paid_at' : 'due_date';

        $query = FinancialTransaction::query()
            ->with('category:id,clinic_id,type,name,sort_order')
            ->when(
                $basis === 'cash',
                fn (Builder $query): Builder => $query->where('status', 'paid'),
                fn (Builder $query): Builder => $query->where('status', '!=', 'cancelled')
            );

        $excludedWithoutDate = (clone $query)->whereNull($dateColumn)->count();
        $transactions = $query
            ->whereBetween($dateColumn, [
                $basis === 'cash' ? $reference->startOfDay() : $reference->toDateString(),
                $basis === 'cash' ? $end->endOfDay() : $end->toDateString(),
            ])
            ->get(['id', 'type', 'amount', 'financial_category_id', 'due_date', 'paid_at']);

        $incomeCents = $this->totalCents($transactions->where('type', 'income'));
        $expenseCents = $this->totalCents($transactions->where('type', 'expense'));
        $rows = $this->categoryRows($transactions, [
            'income' => $incomeCents,
            'expense' => $expenseCents,
        ]);

        return [
            'filters' => [
                'competence' => $reference->format('Y-m'),
                'basis' => $basis,
            ],
            'reference' => [
                'start' => $reference,
                'end' => $end,
                'label' => $reference->format('m/Y'),
                'date_label' => $basis === 'cash' ? 'Data do pagamento' : 'Data de vencimento',
            ],
            'stats' => [
                'income_cents' => $incomeCents,
                'expense_cents' => $expenseCents,
                'balance_cents' => $incomeCents - $expenseCents,
                'income_count' => $transactions->where('type', 'income')->count(),
                'expense_count' => $transactions->where('type', 'expense')->count(),
                'transactions_count' => $transactions->count(),
                'excluded_without_date' => $excludedWithoutDate,
            ],
            'income_categories' => $rows->where('type', 'income')->values(),
            'expense_categories' => $rows->where('type', 'expense')->values(),
            'rows' => $rows,
        ];
    }

    /**
     * @param  Collection<int, FinancialTransaction>  $transactions
     * @param  array{income: int, expense: int}  $totals
     * @return Collection<int, array<string, int|string|float|null>>
     */
    private function categoryRows(Collection $transactions, array $totals): Collection
    {
        return $transactions
            ->groupBy(fn (FinancialTransaction $transaction): string => $transaction->type.':'.($transaction->financial_category_id ?? 'none'))
            ->map(function (Collection $items) use ($totals): array {
                /** @var FinancialTransaction $first */
                $first = $items->first();
                $amountCents = $this->totalCents($items);
                $typeTotal = $totals[$first->type] ?? 0;

                return [
                    'category_id' => $first->financial_category_id,
                    'category_name' => $first->category?->name ?? 'Sem categoria',
                    'type' => $first->type,
                    'sort_order' => $first->category?->sort_order ?? 65535,
                    'transactions_count' => $items->count(),
                    'amount_cents' => $amountCents,
                    'share_percent' => $typeTotal > 0 ? round(($amountCents / $typeTotal) * 100, 2) : 0.0,
                ];
            })
            ->sortBy([
                fn (array $row): int => $row['type'] === 'income' ? 0 : 1,
                fn (array $row): int => (int) $row['sort_order'],
                fn (array $row): string => mb_strtolower((string) $row['category_name']),
            ])
            ->values();
    }

    /** @param Collection<int, FinancialTransaction> $transactions */
    private function totalCents(Collection $transactions): int
    {
        return $transactions->sum(fn (FinancialTransaction $transaction): int => $this->cents($transaction->amount));
    }

    private function cents(mixed $value): int
    {
        $normalized = trim((string) $value);
        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '+-');
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $whole = preg_replace('/\D+/', '', $whole) ?: '0';
        $fraction = substr(str_pad(preg_replace('/\D+/', '', $fraction) ?: '', 2, '0'), 0, 2);
        $cents = ((int) $whole * 100) + (int) $fraction;

        return $negative ? -$cents : $cents;
    }
}
