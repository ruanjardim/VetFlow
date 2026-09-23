<?php

namespace App\Modules\PetShopServices\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Patients\Models\Patient;
use App\Modules\PetShopServices\Models\PetPackage;
use App\Modules\PetShopServices\Models\PetshopPackage;
use App\Modules\PetShopServices\Services\PetPackageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Pacotes vendidos por pet (saldo, validade, consumo). */
class PetPackageController extends Controller
{
    public function __construct(private readonly PetPackageService $packages)
    {
    }

    public function index(Request $request): View
    {
        $this->packages->refreshExpired();

        $filter = in_array($request->query('status'), array_keys(PetPackage::STATUS_LABELS), true)
            ? $request->query('status')
            : null;
        $search = trim((string) $request->query('q'));

        $all = PetPackage::query()
            ->with(['patient', 'tutor', 'balances', 'usages'])
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('code', 'like', "%{$search}%")
                ->orWhereHas('patient', fn ($patient) => $patient->where('name', 'like', "%{$search}%"))
                ->orWhereHas('tutor', fn ($tutor) => $tutor->where('name', 'like', "%{$search}%"))))
            ->latest()
            ->get();

        $counts = collect(PetPackage::STATUS_LABELS)->map(fn ($label, $status) => $all->filter(fn (PetPackage $package) => $package->effectiveStatus() === $status)->count());

        return view('pet-packages.index', [
            'petPackages' => $filter ? $all->filter(fn (PetPackage $package) => $package->effectiveStatus() === $filter)->values() : $all,
            'counts' => $counts,
            'filter' => $filter,
            'search' => $search,
        ]);
    }

    public function create(Request $request): View
    {
        return view('pet-packages.create', [
            'templates' => PetshopPackage::query()->active()->with('items.service')->orderBy('name')->get(),
            'patients' => Patient::query()->with('tutor')->orderBy('name')->get(),
            'selectedTemplateId' => $request->integer('petshop_package_id') ?: null,
            'selectedPatientId' => $request->integer('patient_id') ?: null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'petshop_package_id' => ['required', 'integer', $this->tenantExists('petshop_packages', $request)->where('active', true)],
            'patient_id' => ['required', 'integer', $this->tenantExists('patients', $request)],
            'starts_on' => ['required', 'date'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'activate_now' => ['nullable', 'boolean'],
        ], [
            'petshop_package_id.required' => 'Escolha o pacote.',
            'patient_id.required' => 'Escolha o pet.',
        ]);

        if (! $request->user()?->can('sales.manage')) {
            unset($validated['activate_now']);
        }

        $package = $this->packages->sell($validated);

        if ($package->status === 'pending_payment' && $request->user()?->can('sales.manage')) {
            return redirect()
                ->route('sales.create', ['pet_package_id' => $package->id])
                ->with('success', 'Pacote '.$package->code.' criado. Conclua a venda para liberar o saldo.');
        }

        return redirect()->route('pet-packages.show', $package->id)->with('success', 'Pacote '.$package->code.' registrado.');
    }

    public function show(int $petPackage): View
    {
        return view('pet-packages.show', [
            'petPackage' => PetPackage::query()
                ->with(['patient', 'tutor', 'sale', 'balances.usages.serviceOrder', 'usages'])
                ->findOrFail($petPackage),
        ]);
    }

    public function activate(Request $request, int $petPackage): RedirectResponse
    {
        abort_unless((bool) $request->user()?->can('sales.manage'), 403, 'Somente quem opera o caixa pode liberar pacote sem venda.');

        $package = PetPackage::query()->findOrFail($petPackage);

        if ($package->status !== 'pending_payment') {
            return back()->with('error', 'Somente pacotes aguardando pagamento podem ser liberados.');
        }

        $this->packages->activate($package);

        return back()->with('success', 'Pacote liberado sem venda no PDV (pagamento registrado fora do sistema).');
    }

    public function cancel(Request $request, int $petPackage): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $package = PetPackage::query()->findOrFail($petPackage);

        if ($package->sale_id && $package->sale?->status === 'completed') {
            return back()->with('error', 'Este pacote foi pago no PDV. Cancele a venda '.$package->sale->code.' para estornar.');
        }

        $this->packages->cancel($package, $validated['reason'] ?? null);

        return back()->with('success', 'Pacote cancelado.');
    }

    private function tenantExists(string $table, Request $request)
    {
        $rule = Rule::exists($table, 'id');
        $clinicId = $request->user()?->clinic_id;

        return $clinicId !== null ? $rule->where('clinic_id', $clinicId) : $rule;
    }
}
