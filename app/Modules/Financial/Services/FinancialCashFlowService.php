<?php

namespace App\Modules\Financial\Services;

use App\Modules\Financial\Models\FinancialTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FinancialCashFlowService
{
    public const HORIZON_LABELS = [
        15 => '15 dias',
        30 => '30 dias',
        60 => '60 dias',
        90 => '90 dias',
    ];

    /** @return array<string, mixed> */
    public function report(
        int $clinicId,
        ?string $from = null,
        ?int $days = null,
        bool $includeOverdue = true,
    ): array {
        $days = array_key_exists((int) $days, self::HORIZON_LABELS) ? (int) $days : 30;
        $start = CarbonImmutable::createFromFormat('Y-m-d', $from ?: today()->toDateString())->startOfDay();
        $end = $start->addDays($days - 1)->endOfDay();
        $query = $this->query($clinicId);

        $opening = (clone $query)
            ->where('status', 'paid')
            ->whereNotNull('paid_at')
            ->where('paid_at', '<', $start)
            ->get(['id', 'type', 'amount']);
        $actual = (clone $query)
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$start, $end])
            ->get(['id', 'type', 'amount', 'paid_at']);
        $scheduled = (clone $query)
            ->whereIn('status', ['pending', 'overdue'])
            ->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])
            ->get(['id', 'type', 'amount', 'due_date']);
        $overdue = (clone $query)
            ->whereIn('status', ['pending', 'overdue'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $start->toDateString())
            ->get(['id', 'type', 'amount', 'due_date']);
        $undated = (clone $query)
            ->whereIn('status', ['pending', 'overdue'])
            ->whereNull('due_date')
            ->get(['id', 'type', 'amount']);

        $openingIncomeCents = $this->totalCents($opening->where('type', 'income'));
        $openingExpenseCents = $this->totalCents($opening->where('type', 'expense'));
        $openingBalanceCents = $openingIncomeCents - $openingExpenseCents;
        $actualByDate = $actual->groupBy(fn (FinancialTransaction $transaction): string => $transaction->paid_at->toDateString());
        $scheduledByDate = $scheduled->groupBy(fn (FinancialTransaction $transaction): string => $transaction->due_date->toDateString());
        $overdueIncomeCents = $this->totalCents($overdue->where('type', 'income'));
        $overdueExpenseCents = $this->totalCents($overdue->where('type', 'expense'));
        $runningBalanceCents = $openingBalanceCents;
        $rows = collect();

        for ($offset = 0; $offset < $days; $offset++) {
            $date = $start->addDays($offset);
            $dateKey = $date->toDateString();
            $actualItems = $actualByDate->get($dateKey, collect());
            $scheduledItems = $scheduledByDate->get($dateKey, collect());
            $includedOverdueIncomeCents = $includeOverdue && $offset === 0 ? $overdueIncomeCents : 0;
            $includedOverdueExpenseCents = $includeOverdue && $offset === 0 ? $overdueExpenseCents : 0;
            $actualIncomeCents = $this->totalCents($actualItems->where('type', 'income'));
            $actualExpenseCents = $this->totalCents($actualItems->where('type', 'expense'));
            $scheduledIncomeCents = $this->totalCents($scheduledItems->where('type', 'income'));
            $scheduledExpenseCents = $this->totalCents($scheduledItems->where('type', 'expense'));
            $incomeCents = $actualIncomeCents + $scheduledIncomeCents + $includedOverdueIncomeCents;
            $expenseCents = $actualExpenseCents + $scheduledExpenseCents + $includedOverdueExpenseCents;
            $netCents = $incomeCents - $expenseCents;
            $runningBalanceCents += $netCents;

            $rows->push([
                'date' => $date,
                'actual_income_cents' => $actualIncomeCents,
                'actual_expense_cents' => $actualExpenseCents,
                'scheduled_income_cents' => $scheduledIncomeCents,
                'scheduled_expense_cents' => $scheduledExpenseCents,
                'overdue_income_cents' => $includedOverdueIncomeCents,
                'overdue_expense_cents' => $includedOverdueExpenseCents,
                'income_cents' => $incomeCents,
                'expense_cents' => $expenseCents,
                'net_cents' => $netCents,
                'balance_cents' => $runningBalanceCents,
                'transactions_count' => $actualItems->count()
                    + $scheduledItems->count()
                    + ($includeOverdue && $offset === 0 ? $overdue->count() : 0),
                'includes_overdue' => $includeOverdue && $offset === 0 && $overdue->isNotEmpty(),
            ]);
        }

        $negativeRows = $rows->where('balance_cents', '<', 0);
        $lowestRow = $rows->sortBy('balance_cents')->first();

        return [
            'filters' => [
                'from' => $start->toDateString(),
                'days' => $days,
                'include_overdue' => $includeOverdue,
            ],
            'reference' => [
                'start' => $start,
                'end' => $end,
                'label' => $start->format('d/m/Y').' a '.$end->format('d/m/Y'),
            ],
            'stats' => [
                'opening_income_cents' => $openingIncomeCents,
                'opening_expense_cents' => $openingExpenseCents,
                'opening_balance_cents' => $openingBalanceCents,
                'income_cents' => $rows->sum('income_cents'),
                'expense_cents' => $rows->sum('expense_cents'),
                'projected_balance_cents' => $runningBalanceCents,
                'actual_income_cents' => $this->totalCents($actual->where('type', 'income')),
                'actual_expense_cents' => $this->totalCents($actual->where('type', 'expense')),
                'scheduled_income_cents' => $this->totalCents($scheduled->where('type', 'income')),
                'scheduled_expense_cents' => $this->totalCents($scheduled->where('type', 'expense')),
                'overdue_income_cents' => $overdueIncomeCents,
                'overdue_expense_cents' => $overdueExpenseCents,
                'overdue_count' => $overdue->count(),
                'undated_income_cents' => $this->totalCents($undated->where('type', 'income')),
                'undated_expense_cents' => $this->totalCents($undated->where('type', 'expense')),
                'undated_count' => $undated->count(),
                'negative_days' => $negativeRows->count(),
                'first_negative_date' => $negativeRows->first()['date'] ?? null,
                'lowest_balance_cents' => $lowestRow['balance_cents'] ?? $openingBalanceCents,
                'lowest_balance_date' => $lowestRow['date'] ?? $start,
            ],
            'rows' => $rows,
            'weekly_rows' => $this->weeklyRows($rows),
        ];
    }

    /** @return Collection<int, FinancialTransaction> */
    public function transactionsForDate(
        int $clinicId,
        string $date,
        string $from,
        int $days,
        bool $includeOverdue,
    ): Collection {
        $report = $this->report($clinicId, $from, $days, $includeOverdue);
        $selected = CarbonImmutable::createFromFormat('Y-m-d', $date)->startOfDay();
        abort_unless(
            $selected->betweenIncluded($report['reference']['start'], $report['reference']['end']),
            404,
            'A data não pertence ao período projetado.'
        );

        $query = $this->query($clinicId)->with($this->relations());
        $actual = (clone $query)
            ->where('status', 'paid')
            ->whereDate('paid_at', $selected->toDateString())
            ->get()
            ->each->setAttribute('cash_flow_kind', 'actual');
        $scheduled = (clone $query)
            ->whereIn('status', ['pending', 'overdue'])
            ->whereDate('due_date', $selected->toDateString())
            ->get()
            ->each->setAttribute('cash_flow_kind', 'scheduled');
        $overdue = collect();

        if ($includeOverdue && $selected->isSameDay($report['reference']['start'])) {
            $overdue = (clone $query)
                ->whereIn('status', ['pending', 'overdue'])
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', $report['reference']['start']->toDateString())
                ->get()
                ->each->setAttribute('cash_flow_kind', 'overdue');
        }

        return $actual
            ->concat($scheduled)
            ->concat($overdue)
            ->sortBy([
                fn (FinancialTransaction $transaction): int => match ($transaction->cash_flow_kind) {
                    'overdue' => 0,
                    'scheduled' => 1,
                    default => 2,
                },
                fn (FinancialTransaction $transaction): int => $transaction->type === 'income' ? 0 : 1,
                fn (FinancialTransaction $transaction): int => $transaction->id,
            ])
            ->values();
    }

    /** @return Collection<int, FinancialTransaction> */
    public function exportTransactions(
        int $clinicId,
        string $from,
        int $days,
        bool $includeOverdue,
    ): Collection {
        $report = $this->report($clinicId, $from, $days, $includeOverdue);
        $query = $this->query($clinicId)->with($this->relations());
        $actual = (clone $query)
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$report['reference']['start'], $report['reference']['end']])
            ->get()
            ->each->setAttribute('cash_flow_kind', 'actual');
        $scheduled = (clone $query)
            ->whereIn('status', ['pending', 'overdue'])
            ->whereBetween('due_date', [
                $report['reference']['start']->toDateString(),
                $report['reference']['end']->toDateString(),
            ])
            ->get()
            ->each->setAttribute('cash_flow_kind', 'scheduled');
        $overdue = collect();

        if ($includeOverdue) {
            $overdue = (clone $query)
                ->whereIn('status', ['pending', 'overdue'])
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', $report['reference']['start']->toDateString())
                ->get()
                ->each->setAttribute('cash_flow_kind', 'overdue');
        }

        return $actual
            ->concat($scheduled)
            ->concat($overdue)
            ->sortBy([
                fn (FinancialTransaction $transaction): string => match ($transaction->cash_flow_kind) {
                    'actual' => $transaction->paid_at->toDateString(),
                    'overdue' => $report['reference']['start']->toDateString(),
                    default => $transaction->due_date->toDateString(),
                },
                fn (FinancialTransaction $transaction): int => $transaction->type === 'income' ? 0 : 1,
                fn (FinancialTransaction $transaction): int => $transaction->id,
            ])
            ->values();
    }

    private function query(int $clinicId): Builder
    {
        return FinancialTransaction::query()
            ->withoutGlobalScope('clinic_tenant')
            ->where('clinic_id', $clinicId);
    }

    /** @return array<int, string> */
    private function relations(): array
    {
        return [
            'category:id,clinic_id,name',
            'supplier:id,clinic_id,name',
            'sale:id,financial_transaction_id,code',
            'purchaseEntry:id,clinic_id,code',
            'salesCommissionSettlement:id,financial_transaction_id,seller_user_id',
            'salesCommissionSettlement.seller:id,name',
            'groomingCommissionSettlement:id,financial_transaction_id,user_id',
            'groomingCommissionSettlement.user:id,name',
        ];
    }

    /** @param Collection<int, array<string, mixed>> $rows */
    private function weeklyRows(Collection $rows): Collection
    {
        return $rows
            ->groupBy(fn (array $row): string => $row['date']->startOfWeek()->toDateString())
            ->map(function (Collection $week): array {
                $first = $week->first();
                $last = $week->last();

                return [
                    'start' => $first['date'],
                    'end' => $last['date'],
                    'label' => $first['date']->format('d/m').' a '.$last['date']->format('d/m'),
                    'income_cents' => $week->sum('income_cents'),
                    'expense_cents' => $week->sum('expense_cents'),
                    'net_cents' => $week->sum('net_cents'),
                    'ending_balance_cents' => $last['balance_cents'],
                    'negative_days' => $week->where('balance_cents', '<', 0)->count(),
                ];
            })
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
