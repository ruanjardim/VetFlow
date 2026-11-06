<?php

namespace App\Modules\Saas\Services;

use App\Models\User;
use App\Modules\Audit\Services\AuditTrailService;
use App\Modules\Saas\Models\Coupon;
use App\Modules\Saas\Models\Plan;
use App\Modules\Saas\Models\Subscription;
use App\Modules\Saas\Models\SubscriptionPromotion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CouponService
{
    public function __construct(private readonly AuditTrailService $audit) {}

    public function paginate(): LengthAwarePaginator
    {
        return Coupon::query()
            ->with('plans')
            ->withCount('promotions')
            ->latest()
            ->paginate(25);
    }

    /** @return array{plans: Collection} */
    public function formData(): array
    {
        return [
            'plans' => Plan::query()
                ->where('active', true)
                ->where('internal', false)
                ->orderBy('display_order')
                ->get(),
        ];
    }

    public function save(array $data, User $actor, ?Coupon $coupon = null): Coupon
    {
        return DB::transaction(function () use ($data, $actor, $coupon): Coupon {
            $creating = $coupon === null;
            $coupon ??= new Coupon;
            $before = $coupon->exists ? $this->snapshot($coupon) : [];
            $planIds = collect($data['plan_ids'] ?? [])->map(fn ($id): int => (int) $id)->unique()->values()->all();

            if ($coupon->exists && $coupon->promotions()->exists()) {
                $lockedFields = ['code', 'percentage', 'valid_from', 'duration_months'];
                foreach ($lockedFields as $field) {
                    $incoming = $field === 'code'
                        ? Str::upper(trim((string) ($data[$field] ?? '')))
                        : ($data[$field] ?? null);
                    $current = $field === 'valid_from'
                        ? $coupon->valid_from?->format('Y-m-d\TH:i')
                        : $coupon->getAttribute($field);
                    if ((string) $incoming !== (string) $current) {
                        throw ValidationException::withMessages([
                            $field => 'Este campo não pode ser alterado depois que o cupom foi utilizado.',
                        ]);
                    }
                }

                $currentPlans = $coupon->plans()->pluck('saas_plans.id')->map(fn ($id): int => (int) $id)->sort()->values()->all();
                $newPlans = collect($planIds)->sort()->values()->all();
                if ($currentPlans !== $newPlans) {
                    throw ValidationException::withMessages([
                        'plan_ids' => 'Os planos não podem ser alterados depois que o cupom foi utilizado.',
                    ]);
                }
            }

            $coupon->fill(collect($data)->only([
                'code', 'percentage', 'active', 'valid_from', 'valid_until',
                'duration_months', 'max_redemptions', 'notes',
            ])->all());
            $coupon->created_by_user_id ??= $actor->id;
            $coupon->save();
            $coupon->plans()->sync($planIds);
            $coupon->load('plans');

            $this->audit->record(
                $creating ? 'saas.coupon.created' : 'saas.coupon.updated',
                $coupon,
                $before,
                $this->snapshot($coupon),
                $actor,
                [],
                null,
                $coupon->code
            );

            return $coupon;
        });
    }

    public function apply(Subscription $subscription, string $code, User $actor, ?Carbon $startsOn = null): SubscriptionPromotion
    {
        return DB::transaction(function () use ($subscription, $code, $actor, $startsOn): SubscriptionPromotion {
            $subscription = Subscription::query()->with('plan')->lockForUpdate()->findOrFail($subscription->id);
            $startsOn ??= today();
            $coupon = Coupon::query()
                ->with('plans')
                ->where('code', Str::upper(trim($code)))
                ->lockForUpdate()
                ->first();

            if (! $coupon || ! $coupon->isAvailableAt(now())) {
                throw ValidationException::withMessages(['coupon_code' => 'O cupom não existe, está inativo ou está fora da validade.']);
            }

            if (! in_array($coupon->percentage, Coupon::PERCENTAGES, true)) {
                throw ValidationException::withMessages(['coupon_code' => 'O percentual deste cupom não é permitido.']);
            }

            if ($coupon->plans->isNotEmpty() && ! $coupon->plans->contains('id', $subscription->plan_id)) {
                throw ValidationException::withMessages(['coupon_code' => 'O cupom não é válido para o plano selecionado.']);
            }

            if ($coupon->max_redemptions !== null && $coupon->promotions()->count() >= $coupon->max_redemptions) {
                throw ValidationException::withMessages(['coupon_code' => 'O limite de utilizações deste cupom foi atingido.']);
            }

            $endsOn = $startsOn->copy()->addMonthsNoOverflow($coupon->duration_months)->subDay();
            $hasOverlap = $subscription->promotions()
                ->whereNull('cancelled_at')
                ->whereDate('starts_on', '<=', $endsOn)
                ->whereDate('ends_on', '>=', $startsOn)
                ->exists();
            if ($hasOverlap) {
                throw ValidationException::withMessages(['coupon_code' => 'A assinatura já possui uma promoção no período informado.']);
            }

            if ($subscription->plan?->monthly_price === null) {
                throw ValidationException::withMessages(['coupon_code' => 'O plano selecionado não possui preço mensal configurado.']);
            }

            $basePriceCents = (int) round(((float) $subscription->plan->monthly_price) * 100);
            $discountCents = intdiv(($basePriceCents * $coupon->percentage) + 50, 100);
            $promotion = SubscriptionPromotion::query()->create([
                'coupon_id' => $coupon->id,
                'subscription_id' => $subscription->id,
                'clinic_id' => $subscription->clinic_id,
                'plan_id' => $subscription->plan_id,
                'coupon_code' => $coupon->code,
                'percentage' => $coupon->percentage,
                'billing_cycle' => 'monthly',
                'base_price_cents' => $basePriceCents,
                'discount_cents' => $discountCents,
                'final_price_cents' => $basePriceCents - $discountCents,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'applied_at' => now(),
                'applied_by_user_id' => $actor->id,
            ]);

            $this->audit->record(
                'saas.coupon.applied',
                $promotion,
                [],
                $this->promotionSnapshot($promotion),
                $actor,
                ['subscription_id' => $subscription->id],
                $subscription->clinic_id,
                $coupon->code
            );

            return $promotion;
        });
    }

    public function cancel(SubscriptionPromotion $promotion, User $actor, string $reason): SubscriptionPromotion
    {
        return DB::transaction(function () use ($promotion, $actor, $reason): SubscriptionPromotion {
            $promotion = SubscriptionPromotion::query()->lockForUpdate()->findOrFail($promotion->id);
            if ($promotion->cancelled_at !== null) {
                throw ValidationException::withMessages(['reason' => 'Esta promoção já foi cancelada.']);
            }

            $before = $this->promotionSnapshot($promotion);
            $promotion->forceFill([
                'cancelled_at' => now(),
                'cancelled_by_user_id' => $actor->id,
                'cancellation_reason' => trim($reason),
            ])->save();

            $this->audit->record(
                'saas.coupon.cancelled',
                $promotion,
                $before,
                $this->promotionSnapshot($promotion),
                $actor,
                [],
                $promotion->clinic_id,
                $promotion->coupon_code
            );

            return $promotion;
        });
    }

    public function assertPlanChangeAllowed(Subscription $subscription, int $newPlanId): void
    {
        if (! $subscription->exists || $subscription->plan_id === $newPlanId || ! Schema::hasTable('saas_subscription_promotions')) {
            return;
        }

        if ($subscription->promotions()->effectiveOn()->exists()) {
            throw ValidationException::withMessages([
                'plan_id' => 'Cancele a promoção vigente antes de alterar o plano da assinatura.',
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(Coupon $coupon): array
    {
        $coupon->loadMissing('plans');

        return [
            'code' => $coupon->code,
            'percentage' => $coupon->percentage,
            'active' => (bool) $coupon->active,
            'valid_from' => $coupon->valid_from?->toIso8601String(),
            'valid_until' => $coupon->valid_until?->toIso8601String(),
            'duration_months' => $coupon->duration_months,
            'max_redemptions' => $coupon->max_redemptions,
            'plan_ids' => $coupon->plans->pluck('id')->map(fn ($id): int => (int) $id)->sort()->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function promotionSnapshot(SubscriptionPromotion $promotion): array
    {
        return [
            'coupon_code' => $promotion->coupon_code,
            'percentage' => $promotion->percentage,
            'plan_id' => $promotion->plan_id,
            'base_price_cents' => $promotion->base_price_cents,
            'discount_cents' => $promotion->discount_cents,
            'final_price_cents' => $promotion->final_price_cents,
            'starts_on' => $promotion->starts_on?->toDateString(),
            'ends_on' => $promotion->ends_on?->toDateString(),
            'cancelled_at' => $promotion->cancelled_at?->toIso8601String(),
        ];
    }
}
