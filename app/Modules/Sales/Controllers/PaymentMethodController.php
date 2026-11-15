<?php

namespace App\Modules\Sales\Controllers;

use App\Modules\Clinics\Models\Clinic;
use App\Modules\Sales\Models\PaymentMethod;
use App\Modules\Sales\Requests\SavePaymentMethodRequest;
use App\Modules\Sales\Services\PaymentMethodService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Payment methods of the clinic (one per card machine and type) and the
 * report of expected card settlements.
 */
class PaymentMethodController
{
    public function __construct(
        private readonly PaymentMethodService $methods,
        private readonly TenantContext $tenant
    ) {}

    public function index(Request $request): View
    {
        $clinicId = $this->selectedClinicId($request);

        return view('sales.payment-methods.index', $this->viewData([
            'methods' => $this->methods->forClinic($clinicId),
        ], $clinicId));
    }

    public function create(Request $request): View
    {
        $clinicId = $this->selectedClinicId($request);

        return view('sales.payment-methods.create', $this->viewData([
            'method' => null,
            'initialKind' => array_key_exists($request->query('kind'), PaymentMethod::KIND_LABELS)
                ? $request->query('kind')
                : 'credit_card',
        ], $clinicId));
    }

    public function store(SavePaymentMethodRequest $request): RedirectResponse
    {
        $clinicId = $this->selectedClinicId($request);
        $method = $this->methods->create($clinicId, $request->validated());

        return redirect()
            ->route('sales.payment-methods.index', $this->routeClinic($clinicId))
            ->with('success', 'Forma de pagamento "'.$method->name.'" cadastrada.');
    }

    public function edit(Request $request, int $paymentMethod): View
    {
        $clinicId = $this->selectedClinicId($request);
        $method = $this->methods->findForClinic($clinicId, $paymentMethod);

        return view('sales.payment-methods.edit', $this->viewData([
            'method' => $method,
            'initialKind' => $method->kind,
        ], $clinicId));
    }

    public function update(SavePaymentMethodRequest $request, int $paymentMethod): RedirectResponse
    {
        $clinicId = $this->selectedClinicId($request);
        $method = $this->methods->update(
            $this->methods->findForClinic($clinicId, $paymentMethod),
            $request->validated()
        );

        return redirect()
            ->route('sales.payment-methods.index', $this->routeClinic($clinicId))
            ->with('success', 'Forma de pagamento "'.$method->name.'" atualizada.');
    }

    public function receivables(Request $request): View
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'payment_method_id' => ['nullable', 'integer'],
        ]);

        $methodOptions = PaymentMethod::query()
            ->withTrashed()
            ->whereIn('kind', PaymentMethod::CARD_KINDS)
            ->with('clinic')
            ->ordered()
            ->get();
        $paymentMethodId = (int) ($validated['payment_method_id'] ?? 0);

        if ($paymentMethodId > 0 && ! $methodOptions->contains('id', $paymentMethodId)) {
            $paymentMethodId = 0;
        }

        return view('sales.receivables', [
            'summary' => $this->methods->receivables(
                $validated['from'] ?? null,
                $validated['to'] ?? null,
                $paymentMethodId ?: null
            ),
            'methodOptions' => $methodOptions,
            'selectedMethodId' => $paymentMethodId,
            'showClinic' => $this->tenant->isGlobal(),
        ]);
    }

    /** @param array<string, mixed> $data */
    private function viewData(array $data, int $clinicId): array
    {
        return $data + [
            'kinds' => PaymentMethod::KIND_LABELS,
            'installmentSettlements' => PaymentMethod::INSTALLMENT_SETTLEMENT_LABELS,
            'clinics' => $this->availableClinics(),
            'selectedClinicId' => $clinicId,
            'requiresClinic' => $this->tenant->isGlobal(),
        ];
    }

    private function selectedClinicId(Request $request): int
    {
        if (! $this->tenant->isGlobal()) {
            return (int) $this->tenant->clinicId();
        }

        $requestedId = (int) $request->input('clinic_id');
        $clinicId = Clinic::query()
            ->where('active', true)
            ->when($requestedId > 0, fn ($query) => $query->whereKey($requestedId))
            ->orderBy('trade_name')
            ->value('id');

        abort_if(! $clinicId, 404, 'Nenhum estabelecimento ativo disponível.');

        return (int) $clinicId;
    }

    private function availableClinics(): Collection
    {
        return $this->tenant->isGlobal()
            ? Clinic::query()->where('active', true)->orderBy('trade_name')->orderBy('corporate_name')->get()
            : collect();
    }

    /** @return array<string, int> */
    private function routeClinic(int $clinicId): array
    {
        return $this->tenant->isGlobal() ? ['clinic_id' => $clinicId] : [];
    }
}
