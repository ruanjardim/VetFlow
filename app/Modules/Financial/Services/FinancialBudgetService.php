<?php

namespace App\Modules\Financial\Services;

use App\Modules\Audit\Services\AuditTrailService;
use App\Modules\Financial\Models\FinancialBudget;
use App\Modules\Financial\Models\FinancialCategory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FinancialBudgetService
{
    public function __construct(
        private readonly FinancialCategoryService $categories,
        private readonly FinancialManagerialResultService $results,
        private readonly AuditTrailService $audit
    ) {}

    /**
     * @return array{
     *   filters: array{competence: string, basis: string, clinic_id: int},
     *   reference: array{start: CarbonImmutable, end: CarbonImmutable, label: string},
     *   stats: array<string, int>,
     *   income_rows: Collection<int, array<string, mixed>>,
     *   expense_rows: Collection<int, array<string, mixed>>,
     *   expense_alerts: Collection<int, array<string, mixed>>,
     *   excluded_without_date: int
     * }
     */
    public function report(int $clinicId, ?string $competence = null, ?string $basis = null): array
    {
        $actual = $this->results->report($competence, $basis, $clinicId);
        $reference = $actual['reference']['start'];
        $budgets = FinancialBudget::query()
            ->withoutGlobalScope('clinic_tenant')
            ->where('clinic_id', $clinicId)
            ->whereDate('competence', $reference->toDateString())
            ->get()
            ->keyBy('financial_category_id');
        $actualByCategory = $actual['rows']
            ->whereNotNull('category_id')
            ->keyBy('category_id');

        $rows = $this->categories->forClinic($clinicId)
            ->map(function (FinancialCategory $category) use ($actualByCategory, $budgets): array {
                $actualRow = $actualByCategory->get($category->id);
                $plannedCents = $this->cents($budgets->get($category->id)?->amount ?? 0);
                $actualCents = (int) ($actualRow['amount_cents'] ?? 0);

                return $this->row(
                    categoryId: $category->id,
                    categoryName: $category->name,
                    type: $category->type,
                    active: $category->active,
                    editable: true,
                    plannedCents: $plannedCents,
                    actualCents: $actualCents,
                );
            });

        foreach ($actual['rows']->whereNull('category_id')->groupBy('type') as $type => $unclassified) {
            $rows->push($this->row(
                categoryId: null,
                categoryName: 'Sem categoria',
                type: (string) $type,
                active: false,
                editable: false,
                plannedCents: 0,
                actualCents: (int) $unclassified->sum('amount_cents'),
            ));
        }

        $incomeRows = $rows->where('type', 'income')->values();
        $expenseRows = $rows->where('type', 'expense')->values();
        $plannedIncome = (int) $incomeRows->sum('planned_cents');
        $actualIncome = (int) $incomeRows->sum('actual_cents');
        $plannedExpense = (int) $expenseRows->sum('planned_cents');
        $actualExpense = (int) $expenseRows->sum('actual_cents');
        $plannedBalance = $plannedIncome - $plannedExpense;
        $actualBalance = $actualIncome - $actualExpense;
        $expenseAlerts = $expenseRows
            ->filter(fn (array $row): bool => $row['actual_cents'] > $row['planned_cents'])
            ->map(fn (array $row): array => [
                'category_id' => $row['category_id'],
                'category_name' => $row['category_name'],
                'planned_cents' => $row['planned_cents'],
                'actual_cents' => $row['actual_cents'],
                'excess_cents' => $row['actual_cents'] - $row['planned_cents'],
                'unplanned' => $row['planned_cents'] === 0,
            ])
            ->values();

        return [
            'filters' => [
                'competence' => $reference->format('Y-m'),
                'basis' => $actual['filters']['basis'],
                'clinic_id' => $clinicId,
            ],
            'reference' => [
                'start' => $reference,
                'end' => $actual['reference']['end'],
                'label' => $actual['reference']['label'],
            ],
            'stats' => [
                'planned_income_cents' => $plannedIncome,
                'actual_income_cents' => $actualIncome,
                'income_variance_cents' => $actualIncome - $plannedIncome,
                'planned_expense_cents' => $plannedExpense,
                'actual_expense_cents' => $actualExpense,
                'expense_variance_cents' => $actualExpense - $plannedExpense,
                'planned_balance_cents' => $plannedBalance,
                'actual_balance_cents' => $actualBalance,
                'balance_variance_cents' => $actualBalance - $plannedBalance,
                'expense_alerts_count' => $expenseAlerts->count(),
                'expense_overrun_cents' => (int) $expenseAlerts->sum('excess_cents'),
            ],
            'income_rows' => $incomeRows,
            'expense_rows' => $expenseRows,
            'expense_alerts' => $expenseAlerts,
            'excluded_without_date' => $actual['stats']['excluded_without_date'],
        ];
    }

    /** @return array{source_label: string, copied_count: int, preserved_count: int} */
    public function copyPrevious(int $clinicId, string $competence): array
    {
        $target = CarbonImmutable::createFromFormat('Y-m-d', $competence.'-01')->startOfMonth();
        $source = $target->subMonth();
        $categories = $this->categories->forClinic($clinicId)->keyBy('id');
        $sourceBudgets = FinancialBudget::query()
            ->withoutGlobalScope('clinic_tenant')
            ->where('clinic_id', $clinicId)
            ->whereDate('competence', $source->toDateString())
            ->whereIn('financial_category_id', $categories->keys())
            ->get()
            ->filter(fn (FinancialBudget $budget): bool => $this->cents($budget->amount) > 0);
        $existingCategoryIds = FinancialBudget::query()
            ->withoutGlobalScope('clinic_tenant')
            ->where('clinic_id', $clinicId)
            ->whereDate('competence', $target->toDateString())
            ->whereIn('financial_category_id', $sourceBudgets->pluck('financial_category_id'))
            ->pluck('financial_category_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $copiedCount = 0;

        DB::transaction(function () use (
            $clinicId,
            $target,
            $source,
            $categories,
            $sourceBudgets,
            $existingCategoryIds,
            &$copiedCount
        ): void {
            foreach ($sourceBudgets as $sourceBudget) {
                if (in_array((int) $sourceBudget->financial_category_id, $existingCategoryIds, true)) {
                    continue;
                }

                $category = $categories->get((int) $sourceBudget->financial_category_id);
                if (! $category instanceof FinancialCategory) {
                    continue;
                }

                $budget = FinancialBudget::query()->withoutGlobalScope('clinic_tenant')->create([
                    'clinic_id' => $clinicId,
                    'financial_category_id' => $category->id,
                    'competence' => $target->toDateString(),
                    'amount' => $this->money($this->cents($sourceBudget->amount)),
                ]);
                $copiedCount++;

                $this->audit->record(
                    'financial.budget.copied',
                    $budget,
                    [],
                    ['amount' => $budget->amount],
                    metadata: [
                        'source_competence' => $source->format('Y-m'),
                        'competence' => $target->format('Y-m'),
                        'category_id' => $category->id,
                        'category_type' => $category->type,
                    ],
                    clinicId: $clinicId,
                    subjectLabel: $category->name.' • '.$target->format('m/Y')
                );
            }
        });

        return [
            'source_label' => $source->locale('pt_BR')->translatedFormat('F/Y'),
            'copied_count' => $copiedCount,
            'preserved_count' => count($existingCategoryIds),
        ];
    }

    /** @param array<int|string, mixed> $amounts */
    public function save(int $clinicId, string $competence, array $amounts): void
    {
        $reference = CarbonImmutable::createFromFormat('Y-m-d', $competence.'-01')->startOfMonth();
        $categories = $this->categories->forClinic($clinicId)->keyBy('id');

        DB::transaction(function () use ($clinicId, $reference, $amounts, $categories): void {
            foreach ($amounts as $categoryId => $amount) {
                $category = $categories->get((int) $categoryId);
                if (! $category instanceof FinancialCategory) {
                    continue;
                }

                $budget = FinancialBudget::query()
                    ->withoutGlobalScope('clinic_tenant')
                    ->where('clinic_id', $clinicId)
                    ->where('financial_category_id', $category->id)
                    ->whereDate('competence', $reference->toDateString())
                    ->first();
                $amountCents = $this->cents($amount);
                if ($budget === null && $amountCents === 0) {
                    continue;
                }

                $before = $budget ? ['amount' => $budget->amount] : [];

                $budget ??= new FinancialBudget([
                    'clinic_id' => $clinicId,
                    'financial_category_id' => $category->id,
                    'competence' => $reference->toDateString(),
                ]);
                $budget->amount = $this->money($amountCents);
                $budget->save();

                $after = ['amount' => $budget->amount];
                if (($before['amount'] ?? null) !== $after['amount']) {
                    $this->audit->record(
                        'financial.budget.updated',
                        $budget,
                        $before,
                        $after,
                        metadata: [
                            'competence' => $reference->format('Y-m'),
                            'category_id' => $category->id,
                            'category_type' => $category->type,
                        ],
                        clinicId: $clinicId,
                        subjectLabel: $category->name.' • '.$reference->format('m/Y')
                    );
                }
            }
        });
    }

    /** @return array<string, mixed> */
    private function row(
        ?int $categoryId,
        string $categoryName,
        string $type,
        bool $active,
        bool $editable,
        int $plannedCents,
        int $actualCents,
    ): array {
        $executionPercent = $plannedCents > 0
            ? round(($actualCents / $plannedCents) * 100, 2)
            : null;
        [$statusLabel, $statusClass] = $this->status($type, $editable, $plannedCents, $actualCents);

        return [
            'category_id' => $categoryId,
            'category_name' => $categoryName,
            'type' => $type,
            'active' => $active,
            'editable' => $editable,
            'planned_cents' => $plannedCents,
            'actual_cents' => $actualCents,
            'variance_cents' => $actualCents - $plannedCents,
            'execution_percent' => $executionPercent,
            'status_label' => $statusLabel,
            'status_class' => $statusClass,
        ];
    }

    /** @return array{string, string} */
    private function status(string $type, bool $editable, int $plannedCents, int $actualCents): array
    {
        if (! $editable) {
            return ['Classificar lançamento', 'danger'];
        }

        if ($plannedCents === 0) {
            return $actualCents === 0
                ? ['Sem movimento', 'muted-badge']
                : ['Movimento não previsto', 'warning'];
        }

        if ($type === 'expense') {
            return $actualCents <= $plannedCents
                ? ['Dentro do limite', 'success']
                : ['Acima do previsto', 'danger'];
        }

        return $actualCents >= $plannedCents
            ? ['Meta atingida', 'success']
            : ['Abaixo do previsto', 'warning'];
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

    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
