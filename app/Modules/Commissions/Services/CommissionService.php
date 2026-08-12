<?php

namespace App\Modules\Commissions\Services;

use App\Core\Base\BaseService;
use App\Models\User;
use App\Modules\Commissions\Contracts\CommissionRuleRepositoryInterface;
use App\Modules\Commissions\Models\CommissionRule;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SalePayment;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class CommissionService extends BaseService
{
    public function __construct(CommissionRuleRepositoryInterface $repository)
    {
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

    /**
     * @return array{period: array{from: string, to: string, label: string}, rules: Collection, summary: array<string, float|int>}
     */
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

        $rows = $rules->map(function (CommissionRule $rule) use ($start, $end): array {
            $sales = $this->eligibleSales($rule, $start, $end);
            $base = $sales->sum(fn (Sale $sale) => $this->baseForSale($sale, $rule));

            return [
                'rule' => $rule,
                'sales_count' => $sales->count(),
                'base_amount' => round($base, 2),
                'commission_amount' => round($base * ((float) $rule->percentage / 100), 2),
            ];
        });

        return [
            'period' => [
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
                'label' => $start->isSameDay($end)
                    ? $start->format('d/m/Y')
                    : $start->format('d/m/Y').' a '.$end->format('d/m/Y'),
            ],
            'rules' => $rows,
            'summary' => [
                'rules_count' => $rules->count(),
                'sales_count' => $rows->sum('sales_count'),
                'base_amount' => (float) $rows->sum('base_amount'),
                'commission_amount' => (float) $rows->sum('commission_amount'),
            ],
        ];
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

    /** @return Collection<int, Sale> */
    private function eligibleSales(CommissionRule $rule, Carbon $start, Carbon $end): Collection
    {
        $effectiveStart = $start->copy()->max($rule->starts_on->copy()->startOfDay());
        $effectiveEnd = $rule->ends_on
            ? $end->copy()->min($rule->ends_on->copy()->endOfDay())
            : $end;

        if ($effectiveEnd->lt($effectiveStart)) {
            return collect();
        }

        $query = Sale::query()
            ->where('status', 'completed')
            ->where('seller_user_id', $rule->seller_user_id);

        if ($rule->recognition === 'receipt_date') {
            if ($rule->requires_paid) {
                $query->where('payment_status', 'paid')
                    ->whereHas('financialTransaction', fn ($transactionQuery) => $transactionQuery
                        ->where('status', 'paid')
                        ->whereBetween('paid_at', [$effectiveStart, $effectiveEnd]));
            } else {
                $query->with(['payments' => fn ($paymentQuery) => $paymentQuery
                    ->where('status', 'paid')
                    ->whereBetween('paid_at', [$effectiveStart, $effectiveEnd])])
                    ->whereHas('payments', fn ($paymentQuery) => $paymentQuery
                        ->where('status', 'paid')
                        ->whereBetween('paid_at', [$effectiveStart, $effectiveEnd]));
            }
        } else {
            $query->whereBetween('sold_at', [$effectiveStart, $effectiveEnd]);

            if ($rule->requires_paid) {
                $query->where('payment_status', 'paid');
            }
        }

        return $query->get();
    }

    private function baseForSale(Sale $sale, CommissionRule $rule): float
    {
        if ($rule->recognition === 'receipt_date' && ! $rule->requires_paid) {
            $received = min(
                (float) $sale->payments->sum(fn (SalePayment $payment) => (float) $payment->amount),
                max(0, (float) $sale->total - (float) $sale->return_total)
            );

            if ($rule->basis === 'sold_total') {
                return $received;
            }

            $saleBase = max(0, (float) $sale->total - (float) $sale->return_total);

            return $saleBase > 0
                ? max(0, (float) $sale->gross_profit_total) * ($received / $saleBase)
                : 0.0;
        }

        return $rule->basis === 'gross_profit'
            ? max(0, (float) $sale->gross_profit_total)
            : max(0, (float) $sale->total - (float) $sale->return_total);
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
