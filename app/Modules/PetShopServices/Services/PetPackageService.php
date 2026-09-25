<?php

namespace App\Modules\PetShopServices\Services;

use App\Modules\Patients\Models\Patient;
use App\Modules\PetShopServices\Models\PetPackage;
use App\Modules\PetShopServices\Models\PetPackageBalance;
use App\Modules\PetShopServices\Models\PetPackageUsage;
use App\Modules\PetShopServices\Models\PetshopPackage;
use App\Modules\PetShopServices\Models\PetShopService;
use App\Modules\Sales\Models\Sale;
use App\Modules\ServiceOrders\Models\ServiceOrder;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Pacotes de banho e tosa (ex.: Clubinho): modelo no catalogo, venda para um
 * pet com saldo por servico e consumo automatico nas comandas.
 */
class PetPackageService
{
    public const PACKAGE_SUFFIX = ' · pacote ';

    /** @param array<string, mixed> $data */
    public function saveTemplate(array $data, ?PetshopPackage $package = null): PetshopPackage
    {
        return DB::transaction(function () use ($data, $package): PetshopPackage {
            $items = collect($data['items'] ?? [])
                ->filter(fn ($item) => ! empty($item['petshop_service_id']) && (int) ($item['quantity'] ?? 0) > 0)
                ->groupBy('petshop_service_id')
                ->map(fn (Collection $group, $serviceId) => [
                    'petshop_service_id' => (int) $serviceId,
                    'quantity' => (int) $group->sum(fn ($item) => (int) $item['quantity']),
                ])
                ->values();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Inclua pelo menos um serviço com quantidade no pacote.',
                ]);
            }

            $clinicId = (int) (auth()->user()?->clinic_id ?? $data['clinic_id'] ?? $package?->clinic_id);
            $serviceIds = $items->pluck('petshop_service_id')->all();
            $servicesInClinic = PetShopService::query()
                ->withoutGlobalScopes()
                ->where('clinic_id', $clinicId)
                ->whereIn('id', $serviceIds)
                ->count();

            if ($clinicId <= 0 || $servicesInClinic !== count($serviceIds)) {
                throw ValidationException::withMessages([
                    'items' => 'Todos os serviços do pacote devem pertencer à clínica selecionada.',
                ]);
            }

            $attributes = [
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'price' => (float) $data['price'],
                'validity_days' => ! empty($data['validity_days']) ? (int) $data['validity_days'] : null,
                'active' => (bool) ($data['active'] ?? true),
                'clinic_id' => $clinicId,
            ];

            if ($package) {
                $package->update($attributes);
                $package->items()->delete();
            } else {
                $package = PetshopPackage::query()->create($attributes);
            }

            $package->items()->createMany($items->all());

            return $package->refresh()->load('items.service');
        });
    }

    /**
     * Vende o pacote para o pet. Fica "aguardando pagamento" ate a venda no
     * PDV ser concluida (ou ser ativado manualmente).
     *
     * @param  array<string, mixed>  $data
     */
    public function sell(array $data): PetPackage
    {
        return DB::transaction(function () use ($data): PetPackage {
            /** @var PetshopPackage $template */
            $template = PetshopPackage::query()->with('items.service')->findOrFail($data['petshop_package_id']);
            /** @var Patient $patient */
            $patient = Patient::query()->findOrFail($data['patient_id']);

            if ((int) $template->clinic_id !== (int) $patient->clinic_id) {
                throw ValidationException::withMessages([
                    'patient_id' => 'O pet e o pacote devem pertencer à mesma clínica.',
                ]);
            }

            if ($template->items->contains(fn ($item) => (int) $item->service?->clinic_id !== (int) $template->clinic_id)) {
                throw ValidationException::withMessages([
                    'petshop_package_id' => 'O pacote possui um serviço de outra clínica e precisa ser corrigido antes da venda.',
                ]);
            }

            $startsOn = CarbonImmutable::parse($data['starts_on'] ?? today());
            $price = array_key_exists('price', $data) && $data['price'] !== null && $data['price'] !== ''
                ? (float) $data['price']
                : (float) $template->price;
            $sessions = max(1, $template->totalSessions());

            $package = PetPackage::query()->create([
                'clinic_id' => $patient->clinic_id,
                'petshop_package_id' => $template->id,
                'patient_id' => $patient->id,
                'tutor_id' => $patient->tutor_id,
                'code' => 'PCT-TMP-'.(string) Str::uuid(),
                'name' => $template->name,
                'price' => $price,
                'status' => 'pending_payment',
                'starts_on' => $startsOn->toDateString(),
                'expires_on' => $template->validity_days ? $startsOn->addDays($template->validity_days - 1)->toDateString() : null,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);
            $package->update(['code' => $this->codeForId((int) $package->id)]);

            $allocated = 0.0;
            $lastIndex = $template->items->count() - 1;

            foreach ($template->items->values() as $index => $item) {
                // Valor unitario usado como base de comissao: rateio igual por sessao.
                $unitValue = round($price / $sessions, 2);

                if ($index === $lastIndex) {
                    $unitValue = round(($price - $allocated) / max(1, $item->quantity), 2);
                }

                $allocated += $unitValue * $item->quantity;

                $package->balances()->create([
                    'petshop_service_id' => $item->petshop_service_id,
                    'service_name' => (string) ($item->service?->name ?? 'Serviço'),
                    'quantity' => $item->quantity,
                    'unit_value' => max(0, $unitValue),
                ]);
            }

            if (! empty($data['activate_now'])) {
                $this->activate($package);
            }

            return $package->refresh()->load('balances');
        });
    }

    public function activate(PetPackage $package, ?Sale $sale = null): PetPackage
    {
        if (in_array($package->status, ['cancelled'], true)) {
            return $package;
        }

        $package->update([
            'status' => 'active',
            'activated_at' => $package->activated_at ?? now(),
            'sale_id' => $sale?->id ?? $package->sale_id,
        ]);

        return $package->refresh();
    }

    public function cancel(PetPackage $package, ?string $reason = null): PetPackage
    {
        if ($package->status === 'cancelled') {
            return $package;
        }

        $package->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'notes' => trim(implode("\n", array_filter([(string) $package->notes, $reason ? 'Cancelado: '.$reason : 'Cancelado']))),
        ]);

        return $package->refresh();
    }

    /**
     * Saldos utilizaveis do pet na data (pacote ativo, dentro da validade).
     *
     * @return Collection<int, PetPackageBalance>
     */
    public function usableBalances(int $patientId, CarbonInterface $onDate, bool $lockForUpdate = false): Collection
    {
        $date = $onDate->toDateString();

        $query = PetPackageBalance::query()
            ->whereHas('package', fn ($query) => $query
                ->where('patient_id', $patientId)
                ->where('status', 'active')
                ->whereDate('starts_on', '<=', $date)
                ->where(fn ($validity) => $validity->whereNull('expires_on')->orWhereDate('expires_on', '>=', $date)))
            ->orderBy('id');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->get()
            ->load(['package', 'usages'])
            ->filter(fn (PetPackageBalance $balance) => $balance->remaining() > 0)
            ->sortBy(fn (PetPackageBalance $balance) => $balance->package->expires_on?->timestamp ?? PHP_INT_MAX)
            ->values();
    }

    /** Libera o saldo reservado pela comanda. */
    public function releaseForOrder(ServiceOrder $order): void
    {
        DB::transaction(function () use ($order): void {
            $balanceIds = PetPackageUsage::query()
                ->where('service_order_id', $order->id)
                ->pluck('pet_package_balance_id');

            if ($balanceIds->isNotEmpty()) {
                PetPackageBalance::query()
                    ->whereIn('id', $balanceIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
            }

            PetPackageUsage::query()->where('service_order_id', $order->id)->delete();
            $order->items()->whereNotNull('pet_package_balance_id')->update(['pet_package_balance_id' => null]);
        });
    }

    /**
     * Abate do saldo do pet os servicos da comanda cobertos por pacote.
     * Retorna true quando algum item passou a ser coberto.
     */
    public function consumeForOrder(ServiceOrder $order): bool
    {
        return DB::transaction(function () use ($order): bool {
            $this->releaseForOrder($order);

            if (
                $order->use_package_balance === false
                || ! $order->patient_id
                || in_array($order->status, ['cancelled', 'no_show'], true)
            ) {
                return false;
            }

            $onDate = CarbonImmutable::parse($order->scheduled_at ?? $order->opened_at ?? now());
            $balances = $this->usableBalances((int) $order->patient_id, $onDate, true);

            if ($balances->isEmpty()) {
                return false;
            }

            $covered = false;
            $order->load('items');

            foreach ($order->items as $item) {
                $quantity = (float) $item->quantity;

                if ($item->type !== 'service' || ! $item->petshop_service_id || $quantity <= 0 || floor($quantity) != $quantity) {
                    continue;
                }

                /** @var PetPackageBalance|null $balance */
                $balance = $balances->first(fn (PetPackageBalance $candidate) => (int) $candidate->petshop_service_id === (int) $item->petshop_service_id
                    && $candidate->remaining() >= (int) $quantity);

                if (! $balance) {
                    continue;
                }

                $usage = $balance->usages()->create([
                    'pet_package_id' => $balance->pet_package_id,
                    'service_order_id' => $order->id,
                    'service_order_item_id' => $item->id,
                    'quantity' => (int) $quantity,
                    'unit_value' => $balance->unit_value,
                    'used_at' => now(),
                ]);
                $balance->setRelation('usages', $balance->usages->push($usage));

                $item->update([
                    'pet_package_balance_id' => $balance->id,
                    'unit_price' => 0,
                    'total' => 0,
                    'description' => $this->stripSuffix((string) $item->description).self::PACKAGE_SUFFIX.$balance->package->code,
                ]);

                $covered = true;
            }

            return $covered;
        });
    }

    public function stripSuffix(string $description): string
    {
        $position = mb_strpos($description, self::PACKAGE_SUFFIX);

        return $position === false ? $description : mb_substr($description, 0, $position);
    }

    /** Expira pacotes ativos fora da validade (chamado sob demanda nas telas). */
    public function refreshExpired(): void
    {
        PetPackage::query()
            ->where('status', 'active')
            ->whereNotNull('expires_on')
            ->whereDate('expires_on', '<', today()->toDateString())
            ->update(['status' => 'expired']);
    }

    private function codeForId(int $id): string
    {
        return 'PCT-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }
}
