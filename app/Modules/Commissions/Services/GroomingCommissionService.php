<?php

namespace App\Modules\Commissions\Services;

use App\Models\User;
use App\Modules\Commissions\Models\GroomingCommission;
use App\Modules\Commissions\Models\GroomingCommissionSettlement;
use App\Modules\Financial\Models\FinancialTransaction;
use App\Modules\PetShopServices\Models\PetPackageUsage;
use App\Modules\PetShopServices\Models\PetShopService;
use App\Modules\ServiceOrders\Models\ServiceOrder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Comissao do profissional de banho e tosa.
 *
 * Regra: a comissao nasce quando a comanda e finalizada (pelo PDV ou pelo
 * quadro), por servico, para o profissional responsavel da comanda.
 * Percentual: o do servico; na falta, o padrao do profissional.
 * Base: total do item menos o rateio do desconto da comanda; servico coberto
 * por pacote usa o valor unitario rateado do pacote.
 * Se a comanda sair de "finalizada" (ex.: venda cancelada), comissoes a pagar
 * sao canceladas e as ja fechadas recebem um estorno negativo no proximo
 * fechamento.
 */
class GroomingCommissionService
{
    public function syncForOrder(ServiceOrder|int $order): void
    {
        $order = $order instanceof ServiceOrder
            ? $order->fresh(['items', 'assignedUser'])
            : ServiceOrder::query()->withoutGlobalScopes()->with(['items', 'assignedUser'])->find($order);

        if (! $order) {
            return;
        }

        DB::transaction(function () use ($order): void {
            if ($order->status === 'finished') {
                $this->generate($order);

                return;
            }

            $this->revert($order);
        });
    }

    private function generate(ServiceOrder $order): void
    {
        $hasActive = GroomingCommission::query()
            ->withoutGlobalScopes()
            ->where('service_order_id', $order->id)
            ->where('kind', 'earning')
            ->whereIn('status', ['pending', 'settled'])
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('grooming_commissions as reversal')
                    ->whereColumn('reversal.reverses_id', 'grooming_commissions.id');
            })
            ->exists();

        if ($hasActive || ! $order->assigned_user_id || ! $order->assignedUser) {
            return;
        }

        $serviceItems = $order->items->where('type', 'service')->filter(fn ($item) => $item->petshop_service_id);

        if ($serviceItems->isEmpty()) {
            return;
        }

        $services = PetShopService::query()->withoutGlobalScopes()->whereIn('id', $serviceItems->pluck('petshop_service_id'))->get()->keyBy('id');
        $usages = PetPackageUsage::query()->where('service_order_id', $order->id)->get()->keyBy('service_order_item_id');

        $itemsTotal = (float) $order->items->sum('total');
        $discount = (float) ($order->discount_total ?? 0);
        $earnedAt = $order->closed_at ?? now();

        foreach ($serviceItems as $item) {
            $service = $services->get($item->petshop_service_id);
            $percentage = $service?->commission_percent !== null
                ? (float) $service->commission_percent
                : (float) ($order->assignedUser->grooming_commission_percent ?? 0);

            if ($percentage <= 0) {
                continue;
            }

            $usage = $usages->get($item->id);

            if ($usage) {
                $base = round((float) $usage->unit_value * (int) $usage->quantity, 2);
            } else {
                $share = $itemsTotal > 0 ? ((float) $item->total / $itemsTotal) : 0;
                $base = round(max(0, (float) $item->total - ($discount * $share)), 2);
            }

            if ($base <= 0) {
                continue;
            }

            GroomingCommission::query()->create([
                'clinic_id' => $order->clinic_id,
                'user_id' => $order->assigned_user_id,
                'service_order_id' => $order->id,
                'service_order_item_id' => $item->id,
                'petshop_service_id' => $item->petshop_service_id,
                'kind' => 'earning',
                'description' => $item->description.' — '.$order->code,
                'base_amount' => $base,
                'percentage' => $percentage,
                'amount' => round($base * $percentage / 100, 2),
                'status' => 'pending',
                'earned_at' => $earnedAt,
            ]);
        }
    }

    private function revert(ServiceOrder $order): void
    {
        $earnings = GroomingCommission::query()
            ->withoutGlobalScopes()
            ->where('service_order_id', $order->id)
            ->where('kind', 'earning')
            ->whereIn('status', ['pending', 'settled'])
            ->get();

        foreach ($earnings as $earning) {
            if ($earning->status === 'pending') {
                $earning->update(['status' => 'cancelled']);

                continue;
            }

            $alreadyReversed = GroomingCommission::query()
                ->withoutGlobalScopes()
                ->where('reverses_id', $earning->id)
                ->exists();

            if ($alreadyReversed) {
                continue;
            }

            GroomingCommission::query()->create([
                'clinic_id' => $earning->clinic_id,
                'user_id' => $earning->user_id,
                'service_order_id' => $earning->service_order_id,
                'service_order_item_id' => $earning->service_order_item_id,
                'petshop_service_id' => $earning->petshop_service_id,
                'reverses_id' => $earning->id,
                'kind' => 'reversal',
                'description' => 'Estorno: '.$earning->description,
                'base_amount' => -1 * (float) $earning->base_amount,
                'percentage' => $earning->percentage,
                'amount' => -1 * (float) $earning->amount,
                'status' => 'pending',
                'earned_at' => now(),
            ]);
        }
    }

    /**
     * Resumo por profissional e lancamentos do periodo.
     *
     * @return array<string, mixed>
     */
    public function overview(CarbonImmutable $from, CarbonImmutable $to, ?int $userId = null): array
    {
        $entries = GroomingCommission::query()
            ->with(['user', 'serviceOrder.patient', 'settlement'])
            ->whereBetween('earned_at', [$from->startOfDay(), $to->endOfDay()])
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->orderByDesc('earned_at')
            ->get();

        $pendingAll = GroomingCommission::query()
            ->with('user')
            ->where('status', 'pending')
            ->whereDate('earned_at', '<=', $to->toDateString())
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->get();

        $byProfessional = $entries->concat($pendingAll)->unique('id')->groupBy('user_id')
            ->map(function (Collection $rows) use ($from, $to): array {
                $inPeriod = $rows->filter(fn (GroomingCommission $row) => $row->earned_at->between($from->startOfDay(), $to->endOfDay()));

                return [
                    'user' => $rows->first()->user,
                    'services' => $inPeriod->where('kind', 'earning')->where('status', '!=', 'cancelled')->count(),
                    'base' => (float) $inPeriod->where('status', '!=', 'cancelled')->sum('base_amount'),
                    'earned' => (float) $inPeriod->where('status', '!=', 'cancelled')->sum('amount'),
                    'pending' => (float) $rows->where('status', 'pending')->sum('amount'),
                    'settled' => (float) $inPeriod->where('status', 'settled')->sum('amount'),
                ];
            })
            ->sortBy(fn (array $row) => $row['user']?->name)
            ->values();

        return [
            'entries' => $entries,
            'byProfessional' => $byProfessional,
            'totals' => [
                'earned' => (float) $byProfessional->sum('earned'),
                'pending' => (float) $byProfessional->sum('pending'),
                'settled' => (float) $byProfessional->sum('settled'),
            ],
            'settlements' => GroomingCommissionSettlement::query()
                ->with(['user', 'financialTransaction'])
                ->when($userId, fn ($query) => $query->where('user_id', $userId))
                ->latest()
                ->limit(20)
                ->get(),
        ];
    }

    /**
     * Fecha as comissoes a pagar do profissional ate a data e gera a conta a
     * pagar no financeiro.
     */
    public function settle(int $userId, CarbonImmutable $until, CarbonImmutable $dueDate, ?string $notes = null): GroomingCommissionSettlement
    {
        return DB::transaction(function () use ($userId, $until, $dueDate, $notes): GroomingCommissionSettlement {
            $entries = GroomingCommission::query()
                ->where('user_id', $userId)
                ->where('status', 'pending')
                ->whereDate('earned_at', '<=', $until->toDateString())
                ->lockForUpdate()
                ->get();

            if ($entries->isEmpty()) {
                throw ValidationException::withMessages([
                    'user_id' => 'Não há comissões a pagar para este profissional até a data informada.',
                ]);
            }

            $total = round((float) $entries->sum('amount'), 2);

            if ($total <= 0) {
                throw ValidationException::withMessages([
                    'user_id' => 'O saldo de comissões está zerado ou negativo (estornos). Aguarde novos atendimentos para fechar.',
                ]);
            }

            /** @var User $user */
            $user = User::query()->findOrFail($userId);
            $periodStart = CarbonImmutable::parse($entries->min('earned_at'))->startOfDay();

            $transaction = FinancialTransaction::query()->create([
                'clinic_id' => $entries->first()->clinic_id,
                'type' => 'expense',
                'description' => sprintf('Comissão banho e tosa — %s (%s a %s)', $user->name, $periodStart->format('d/m/Y'), $until->format('d/m/Y')),
                'amount' => $total,
                'due_date' => $dueDate->toDateString(),
                'status' => 'pending',
                'reference' => 'COMISSAO-BT',
                'notes' => $notes,
            ]);

            $settlement = GroomingCommissionSettlement::query()->create([
                'clinic_id' => $entries->first()->clinic_id,
                'user_id' => $userId,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $until->toDateString(),
                'total' => $total,
                'entries_count' => $entries->count(),
                'financial_transaction_id' => $transaction->id,
                'due_date' => $dueDate->toDateString(),
                'notes' => $notes,
                'created_by' => auth()->id(),
            ]);

            GroomingCommission::query()
                ->whereIn('id', $entries->pluck('id'))
                ->update(['status' => 'settled', 'settlement_id' => $settlement->id]);

            return $settlement->refresh();
        });
    }
}
