<?php

namespace App\Modules\Commissions\Services;

use App\Core\Base\BaseService;
use App\Models\User;
use App\Modules\Audit\Services\AuditTrailService;
use App\Modules\Commissions\Contracts\CommissionRuleRepositoryInterface;
use App\Modules\Commissions\Models\CommissionRule;
use App\Modules\Commissions\Models\SalesCommissionSettlement;
use App\Modules\Commissions\Models\SalesCommissionSettlementItem;
use App\Modules\Financial\Models\FinancialCategory;
use App\Modules\Financial\Models\FinancialTransaction;
use App\Modules\Financial\Services\FinancialCategoryService;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SalePayment;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommissionService extends BaseService
{
    public function __construct(
        CommissionRuleRepositoryInterface $repository,
        private readonly FinancialCategoryService $financialCategories,
        private readonly AuditTrailService $audit,
    ) {
        $this->repository = $repository;
    }

    public function create(array $data): Model
    {
        $data['clinic_id'] = $this->clinicIdFor($data);
        $this->ensureSellerBelongsToClinic((int) $data['seller_user_id'], (int) $data['clinic_id']);
        $this->ensureNoOverlap($data);
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        return parent::create($data);
    }

    public function update(int $id, array $data): Model
    {
        /** @var CommissionRule $rule */
        $rule = $this->repository->findOrFail($id);
        $data['clinic_id'] = (int) $rule->clinic_id;
        $this->ensureSellerBelongsToClinic((int) $data['seller_user_id'], (int) $data['clinic_id']);
        $this->ensureNoOverlap($data, $rule->id);
        $data['updated_by'] = auth()->id();

        return parent::update($rule->id, $data);
    }

    /** @return array<string, mixed> */
    public function preview(?string $from = null, ?string $to = null): array
    {
        [$start, $end] = $this->range($from, $to);
        $rules = CommissionRule::query()
            ->with('seller.clinic')
            ->where('active', true)
            ->whereDate('starts_on', '<=', $end)
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $start))
            ->orderBy('seller_user_id')
            ->orderByDesc('starts_on')
            ->get();
        $rows = $this->rowsForRules($rules, $start, $end);
        $sellerRows = $rows
            ->groupBy(fn (array $row): int => (int) $row['rule']->seller_user_id)
            ->map(function (SupportCollection $sellerRules): array {
                $contributions = $sellerRules->pluck('contributions')->flatten(1);

                return [
                    'seller' => $sellerRules->first()['rule']->seller,
                    'rules_count' => $sellerRules->count(),
                    'sales_count' => $contributions->where('expected_amount_cents', '!=', 0)->pluck('sale_id')->unique()->count(),
                    'base_amount' => $this->amount($contributions->sum('expected_base_cents')),
                    'commission_amount' => $this->amount($contributions->sum('expected_amount_cents')),
                    'pending_amount' => $this->amount($contributions->sum('pending_amount_cents')),
                    'pending_count' => $contributions->where('pending_amount_cents', '!=', 0)->count(),
                    'adjustments_count' => $contributions->where('pending_amount_cents', '<', 0)->count(),
                ];
            })
            ->sortBy(fn (array $row): string => (string) $row['seller']?->name)
            ->values();
        $pendingContributions = $rows
            ->pluck('contributions')
            ->flatten(1)
            ->where('pending_amount_cents', '!=', 0)
            ->sortBy([
                ['source_date', 'asc'],
                ['sale_code', 'asc'],
            ])
            ->values();

        return [
            'period' => [
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
                'label' => $start->isSameDay($end)
                    ? $start->format('d/m/Y')
                    : $start->format('d/m/Y').' a '.$end->format('d/m/Y'),
            ],
            'rules' => $rows,
            'sellers' => $sellerRows,
            'contributions' => $pendingContributions,
            'summary' => [
                'rules_count' => $rules->count(),
                'sales_count' => $sellerRows->sum('sales_count'),
                'base_amount' => (float) $sellerRows->sum('base_amount'),
                'commission_amount' => (float) $sellerRows->sum('commission_amount'),
                'pending_amount' => (float) $sellerRows->sum('pending_amount'),
                'pending_count' => $sellerRows->sum('pending_count'),
                'adjustments_count' => $sellerRows->sum('adjustments_count'),
            ],
        ];
    }

    /** @return Collection<int, SalesCommissionSettlement> */
    public function recentSettlements(): Collection
    {
        return SalesCommissionSettlement::query()
            ->with(['seller.clinic', 'financialTransaction', 'createdBy'])
            ->latest()
            ->limit(20)
            ->get();
    }

    public function settle(
        User $actor,
        int $sellerId,
        string $from,
        string $to,
        string $dueDate,
        ?string $notes = null,
    ): SalesCommissionSettlement {
        [$start, $end] = $this->range($from, $to);

        if ($start->diffInDays($end) > 366) {
            throw ValidationException::withMessages([
                'to' => 'O fechamento pode abranger no máximo 367 dias.',
            ]);
        }

        /** @var User $seller */
        $seller = User::query()->active()->whereNotNull('clinic_id')->findOrFail($sellerId);

        if ($actor->clinic_id !== null && (int) $actor->clinic_id !== (int) $seller->clinic_id) {
            abort(404);
        }

        return DB::transaction(function () use ($actor, $seller, $start, $end, $dueDate, $notes): SalesCommissionSettlement {
            $rules = CommissionRule::query()
                ->withoutGlobalScope('clinic_tenant')
                ->with('seller.clinic')
                ->where('clinic_id', $seller->clinic_id)
                ->where('seller_user_id', $seller->id)
                ->where('active', true)
                ->whereDate('starts_on', '<=', $end)
                ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $start))
                ->lockForUpdate()
                ->get();
            $contributions = $this->rowsForRules($rules, $start, $end)
                ->pluck('contributions')
                ->flatten(1)
                ->where('pending_amount_cents', '!=', 0)
                ->values();
            $totalCents = (int) $contributions->sum('pending_amount_cents');

            if ($contributions->isEmpty()) {
                throw ValidationException::withMessages([
                    'seller_user_id' => 'Não há novas comissões ou ajustes para fechar neste período.',
                ]);
            }

            if ($totalCents <= 0) {
                throw ValidationException::withMessages([
                    'seller_user_id' => 'Os ajustes do período deixam o saldo zerado ou negativo. Revise as devoluções antes do fechamento.',
                ]);
            }

            $snapshot = $contributions
                ->map(fn (array $row): array => [
                    'rule_id' => $row['rule']->id,
                    'source_key' => $row['source_key'],
                    'revision' => $row['revision'] + 1,
                    'sale_id' => $row['sale_id'],
                    'payment_id' => $row['sale_payment_id'],
                    'source_date' => $row['source_date'],
                    'base_cents' => $row['pending_base_cents'],
                    'amount_cents' => $row['pending_amount_cents'],
                ])
                ->sortBy(fn (array $row): string => $row['rule_id'].'|'.$row['source_key'].'|'.$row['revision'])
                ->values()
                ->all();
            $snapshotHash = hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $transaction = FinancialTransaction::query()->withoutGlobalScope('clinic_tenant')->create([
                'clinic_id' => $seller->clinic_id,
                'financial_category_id' => $this->financialCategories->idForCode((int) $seller->clinic_id, FinancialCategory::CODE_COMMISSIONS),
                'type' => 'expense',
                'description' => sprintf(
                    'Comissão de vendas — %s (%s a %s)',
                    $seller->name,
                    $start->format('d/m/Y'),
                    $end->format('d/m/Y')
                ),
                'amount' => $this->amount($totalCents),
                'due_date' => Carbon::parse($dueDate)->toDateString(),
                'status' => 'pending',
                'reference' => 'COMISSAO-VENDAS',
                'notes' => filled($notes) ? trim((string) $notes) : null,
            ]);
            $settlement = SalesCommissionSettlement::query()->withoutGlobalScope('clinic_tenant')->create([
                'clinic_id' => $seller->clinic_id,
                'seller_user_id' => $seller->id,
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'total' => $this->amount($totalCents),
                'sales_count' => $contributions->pluck('sale_id')->unique()->count(),
                'entries_count' => $contributions->count(),
                'financial_transaction_id' => $transaction->id,
                'due_date' => Carbon::parse($dueDate)->toDateString(),
                'notes' => filled($notes) ? trim((string) $notes) : null,
                'snapshot_hash' => $snapshotHash,
                'created_by' => $actor->id,
            ]);

            foreach ($contributions as $row) {
                SalesCommissionSettlementItem::query()->withoutGlobalScope('clinic_tenant')->create([
                    'settlement_id' => $settlement->id,
                    'clinic_id' => $seller->clinic_id,
                    'commission_rule_id' => $row['rule']->id,
                    'seller_user_id' => $seller->id,
                    'sale_id' => $row['sale_id'],
                    'sale_payment_id' => $row['sale_payment_id'],
                    'source_key' => $row['source_key'],
                    'revision' => $row['revision'] + 1,
                    'source_date' => $row['source_date'],
                    'sale_code' => $row['sale_code'],
                    'rule_name' => $row['rule']->name,
                    'basis' => $row['rule']->basis,
                    'recognition' => $row['rule']->recognition,
                    'base_amount' => $this->amount($row['pending_base_cents']),
                    'percentage' => $row['rule']->percentage,
                    'amount' => $this->amount($row['pending_amount_cents']),
                    'snapshot' => [
                        'expected_base_cents' => $row['expected_base_cents'],
                        'expected_amount_cents' => $row['expected_amount_cents'],
                        'settled_base_cents' => $row['settled_base_cents'],
                        'settled_amount_cents' => $row['settled_amount_cents'],
                        'source_label' => $row['source_label'],
                    ],
                ]);
            }

            $this->audit->record(
                'commission.sales-settlement.created',
                $settlement,
                [],
                [],
                $actor,
                [
                    'period' => ['from' => $start->toDateString(), 'to' => $end->toDateString()],
                    'total_cents' => $totalCents,
                    'sales_count' => $settlement->sales_count,
                    'entries_count' => $settlement->entries_count,
                    'financial_transaction_id' => $transaction->id,
                    'snapshot_hash' => $snapshotHash,
                ],
                (int) $seller->clinic_id,
                'Comissão de vendas — '.$seller->name,
            );

            return $settlement->load(['seller', 'financialTransaction', 'items']);
        });
    }

    /** @return Collection<int, User> */
    public function sellers(?int $clinicId = null): Collection
    {
        $clinicId = app(TenantContext::class)->clinicId() ?? $clinicId;

        return User::query()
            ->active()
            ->when($clinicId !== null, fn ($query) => $query->where('clinic_id', $clinicId))
            ->orderBy('name')
            ->get();
    }

    /** @param Collection<int, CommissionRule> $rules */
    private function rowsForRules(Collection $rules, Carbon $start, Carbon $end): SupportCollection
    {
        return $rules->map(function (CommissionRule $rule) use ($start, $end): array {
            $contributions = $this->contributionsForRule($rule, $start, $end);

            return [
                'rule' => $rule,
                'sales_count' => $contributions->where('expected_amount_cents', '!=', 0)->pluck('sale_id')->unique()->count(),
                'base_amount' => $this->amount($contributions->sum('expected_base_cents')),
                'commission_amount' => $this->amount($contributions->sum('expected_amount_cents')),
                'pending_amount' => $this->amount($contributions->sum('pending_amount_cents')),
                'contributions' => $contributions,
            ];
        });
    }

    /** @return SupportCollection<int, array<string, mixed>> */
    private function contributionsForRule(CommissionRule $rule, Carbon $start, Carbon $end): SupportCollection
    {
        $effectiveStart = $start->copy()->max($rule->starts_on->copy()->startOfDay());
        $effectiveEnd = $rule->ends_on
            ? $end->copy()->min($rule->ends_on->copy()->endOfDay())
            : $end;

        if ($effectiveEnd->lt($effectiveStart)) {
            return collect();
        }

        $current = $this->currentContributions($rule, $effectiveStart, $effectiveEnd)->keyBy('source_key');
        $settled = SalesCommissionSettlementItem::query()
            ->withoutGlobalScope('clinic_tenant')
            ->where('clinic_id', $rule->clinic_id)
            ->where('commission_rule_id', $rule->id)
            ->whereDate('source_date', '>=', $effectiveStart->toDateString())
            ->whereDate('source_date', '<=', $effectiveEnd->toDateString())
            ->with('sale')
            ->get()
            ->groupBy('source_key');

        return $current->keys()
            ->merge($settled->keys())
            ->unique()
            ->map(function (string $sourceKey) use ($current, $settled, $rule): array {
                $expected = $current->get($sourceKey);
                /** @var SupportCollection<int, SalesCommissionSettlementItem> $closed */
                $closed = collect($settled->get($sourceKey, collect()));
                $last = $closed->sortByDesc('revision')->first();
                $expectedBaseCents = (int) ($expected['base_cents'] ?? 0);
                $expectedAmountCents = (int) ($expected['amount_cents'] ?? 0);
                $settledBaseCents = $closed->sum(fn (SalesCommissionSettlementItem $item): int => $this->cents($item->base_amount));
                $settledAmountCents = $closed->sum(fn (SalesCommissionSettlementItem $item): int => $this->cents($item->amount));

                return [
                    'rule' => $rule,
                    'source_key' => $sourceKey,
                    'revision' => (int) $closed->max('revision'),
                    'sale_id' => (int) ($expected['sale_id'] ?? $last?->sale_id),
                    'sale_payment_id' => $expected['sale_payment_id'] ?? $last?->sale_payment_id,
                    'sale_code' => (string) ($expected['sale_code'] ?? $last?->sale_code),
                    'source_date' => (string) ($expected['source_date'] ?? $last?->source_date?->toDateString()),
                    'source_label' => (string) ($expected['source_label'] ?? data_get($last?->snapshot, 'source_label', 'Origem encerrada ou cancelada')),
                    'expected_base_cents' => $expectedBaseCents,
                    'expected_amount_cents' => $expectedAmountCents,
                    'settled_base_cents' => $settledBaseCents,
                    'settled_amount_cents' => $settledAmountCents,
                    'pending_base_cents' => $expectedBaseCents - $settledBaseCents,
                    'pending_amount_cents' => $expectedAmountCents - $settledAmountCents,
                    'base_amount' => $this->amount($expectedBaseCents),
                    'commission_amount' => $this->amount($expectedAmountCents),
                    'settled_amount' => $this->amount($settledAmountCents),
                    'pending_amount' => $this->amount($expectedAmountCents - $settledAmountCents),
                ];
            })
            ->values();
    }

    /** @return SupportCollection<int, array<string, mixed>> */
    private function currentContributions(CommissionRule $rule, Carbon $start, Carbon $end): SupportCollection
    {
        $query = Sale::query()
            ->withoutGlobalScope('clinic_tenant')
            ->where('clinic_id', $rule->clinic_id)
            ->where('status', 'completed')
            ->where('seller_user_id', $rule->seller_user_id);

        if ($rule->recognition === 'sale_date') {
            $sales = $query
                ->whereBetween('sold_at', [$start, $end])
                ->when($rule->requires_paid, fn ($builder) => $builder->where('payment_status', 'paid'))
                ->get();

            return $sales->map(fn (Sale $sale): array => $this->saleContribution(
                $rule,
                $sale,
                'sale:'.$sale->id,
                $sale->sold_at?->toDateString() ?? $start->toDateString(),
                'Venda reconhecida na data da venda',
            ));
        }

        if ($rule->requires_paid) {
            $sales = $query
                ->with('financialTransaction')
                ->where('payment_status', 'paid')
                ->whereHas('financialTransaction', fn ($transactionQuery) => $transactionQuery
                    ->where('status', 'paid')
                    ->whereBetween('paid_at', [$start, $end]))
                ->get();

            return $sales->map(fn (Sale $sale): array => $this->saleContribution(
                $rule,
                $sale,
                'sale-paid:'.$sale->id,
                $sale->financialTransaction?->paid_at?->toDateString() ?? $start->toDateString(),
                'Venda reconhecida na quitação integral',
            ));
        }

        $sales = $query
            ->with(['payments' => fn ($paymentQuery) => $paymentQuery
                ->where('status', 'paid')
                ->whereNotNull('paid_at')
                ->where('paid_at', '<=', $end)
                ->orderBy('paid_at')
                ->orderBy('id')])
            ->whereHas('payments', fn ($paymentQuery) => $paymentQuery
                ->where('status', 'paid')
                ->whereBetween('paid_at', [$start, $end]))
            ->get();

        return $sales->flatMap(function (Sale $sale) use ($rule, $start, $end): array {
            $saleNetCents = max(0, $this->cents($sale->total) - $this->cents($sale->return_total));
            $profitCents = max(0, $this->cents($sale->gross_profit_total));
            $remainingCents = $saleNetCents;
            $rows = [];

            foreach ($sale->payments as $payment) {
                $allocatedCents = min($remainingCents, max(0, $this->cents($payment->amount)));
                $remainingCents -= $allocatedCents;

                if ($allocatedCents <= 0 || ! $payment->paid_at?->between($start, $end)) {
                    continue;
                }

                $baseCents = $rule->basis === 'gross_profit' && $saleNetCents > 0
                    ? (int) round($profitCents * ($allocatedCents / $saleNetCents))
                    : $allocatedCents;
                $rows[] = $this->contribution(
                    $rule,
                    $sale,
                    'payment:'.$payment->id,
                    $payment->paid_at->toDateString(),
                    'Recebimento parcial #'.$payment->id,
                    $baseCents,
                    $payment,
                );
            }

            return $rows;
        })->values();
    }

    /** @return array<string, mixed> */
    private function saleContribution(
        CommissionRule $rule,
        Sale $sale,
        string $sourceKey,
        string $sourceDate,
        string $sourceLabel,
    ): array {
        $baseCents = $rule->basis === 'gross_profit'
            ? max(0, $this->cents($sale->gross_profit_total))
            : max(0, $this->cents($sale->total) - $this->cents($sale->return_total));

        return $this->contribution($rule, $sale, $sourceKey, $sourceDate, $sourceLabel, $baseCents);
    }

    /** @return array<string, mixed> */
    private function contribution(
        CommissionRule $rule,
        Sale $sale,
        string $sourceKey,
        string $sourceDate,
        string $sourceLabel,
        int $baseCents,
        ?SalePayment $payment = null,
    ): array {
        $percentageHundredths = (int) round((float) $rule->percentage * 100);

        return [
            'source_key' => $sourceKey,
            'sale_id' => $sale->id,
            'sale_payment_id' => $payment?->id,
            'sale_code' => $sale->code,
            'source_date' => $sourceDate,
            'source_label' => $sourceLabel,
            'base_cents' => $baseCents,
            'amount_cents' => (int) round($baseCents * ($percentageHundredths / 10000)),
        ];
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function range(?string $from, ?string $to): array
    {
        $start = $this->parseDate($from)?->startOfDay() ?? now()->startOfMonth();
        $end = $this->parseDate($to)?->endOfDay() ?? now()->endOfMonth();

        if ($end->lt($start)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        return [$start, $end];
    }

    private function parseDate(?string $date): ?Carbon
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

    private function amount(int $cents): float
    {
        return round($cents / 100, 2);
    }

    private function clinicIdFor(array $data): int
    {
        $clinicId = app(TenantContext::class)->clinicId() ?? ($data['clinic_id'] ?? null);

        if (! $clinicId) {
            throw ValidationException::withMessages([
                'clinic_id' => 'Selecione a clinica da regra de comissao.',
            ]);
        }

        return (int) $clinicId;
    }

    private function ensureSellerBelongsToClinic(int $sellerId, int $clinicId): void
    {
        $validSeller = User::query()
            ->active()
            ->whereKey($sellerId)
            ->where('clinic_id', $clinicId)
            ->exists();

        if (! $validSeller) {
            throw ValidationException::withMessages([
                'seller_user_id' => 'Selecione um vendedor ativo da mesma clinica.',
            ]);
        }
    }

    private function ensureNoOverlap(array $data, ?int $ignoringRuleId = null): void
    {
        if (! ($data['active'] ?? false)) {
            return;
        }

        $startsOn = Carbon::parse($data['starts_on'])->startOfDay();
        $endsOn = ! empty($data['ends_on']) ? Carbon::parse($data['ends_on'])->endOfDay() : null;
        $overlap = CommissionRule::query()
            ->where('clinic_id', $data['clinic_id'])
            ->where('seller_user_id', $data['seller_user_id'])
            ->where('active', true)
            ->when($ignoringRuleId, fn ($query) => $query->whereKeyNot($ignoringRuleId))
            ->whereDate('starts_on', '<=', $endsOn ?? '9999-12-31')
            ->where(fn ($query) => $query
                ->whereNull('ends_on')
                ->orWhereDate('ends_on', '>=', $startsOn))
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'starts_on' => 'Ja existe uma regra ativa para este vendedor no periodo informado.',
            ]);
        }
    }
}
