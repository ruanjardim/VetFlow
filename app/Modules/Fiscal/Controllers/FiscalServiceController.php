<?php

namespace App\Modules\Fiscal\Controllers;

use App\Modules\Fiscal\Requests\UpdateServiceFiscalProfileRequest;
use App\Modules\Fiscal\Services\FiscalTaxProfileService;
use App\Modules\Fiscal\Support\ResolvesFiscalClinic;
use App\Modules\PetShopServices\Models\PetShopService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FiscalServiceController
{
    use ResolvesFiscalClinic;

    public function __construct(
        private readonly FiscalTaxProfileService $profiles,
        private readonly TenantContext $tenant
    ) {}

    public function index(Request $request): View
    {
        $clinicId = $this->selectedClinicId($request, $this->tenant);
        $status = in_array($request->query('status'), ['ready', 'pending'], true)
            ? $request->query('status')
            : 'all';

        $query = PetShopService::query()
            ->with('fiscalProfile')
            ->where('clinic_id', $clinicId)
            ->where('active', true)
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = trim((string) $request->query('search'));
                $query->where(fn ($nested) => $nested
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%"));
            })
            ->when($status === 'ready', fn ($query) => $query->whereHas('fiscalProfile', fn ($profile) => $profile->where('ready', true)))
            ->when($status === 'pending', fn ($query) => $query->whereDoesntHave('fiscalProfile', fn ($profile) => $profile->where('ready', true)))
            ->orderBy('name');

        return view('fiscal.services.index', [
            'services' => $query->paginate(20)->withQueryString(),
            'stats' => $this->stats($clinicId),
            'status' => $status,
            'clinics' => $this->availableClinics($this->tenant),
            'selectedClinicId' => $clinicId,
            'requiresClinic' => $this->tenant->isGlobal(),
        ]);
    }

    public function edit(Request $request, int $service): View
    {
        $clinicId = $this->selectedClinicId($request, $this->tenant);
        $serviceModel = PetShopService::query()->where('clinic_id', $clinicId)->findOrFail($service);

        return view('fiscal.services.edit', [
            'service' => $serviceModel,
            'profile' => $this->profiles->serviceProfile($serviceModel),
            'missingLabels' => FiscalTaxProfileService::missingLabels($serviceModel->fiscalProfile, 'service'),
            'selectedClinicId' => $clinicId,
            'requiresClinic' => $this->tenant->isGlobal(),
        ]);
    }

    public function update(UpdateServiceFiscalProfileRequest $request, int $service): RedirectResponse
    {
        $clinicId = $this->selectedClinicId($request, $this->tenant);
        $serviceModel = PetShopService::query()->where('clinic_id', $clinicId)->findOrFail($service);
        $profile = $this->profiles->updateService($serviceModel, $request->validated());

        return redirect()
            ->route('fiscal.services.index', $this->fiscalRouteClinic($clinicId, $this->tenant))
            ->with($profile->ready ? 'success' : 'warning', $profile->ready
                ? 'Cadastro tributário do serviço pronto para homologação.'
                : 'Cadastro salvo, mas ainda há campos mínimos pendentes.');
    }

    /** @return array{total: int, ready: int, pending: int} */
    private function stats(int $clinicId): array
    {
        $base = PetShopService::query()->where('clinic_id', $clinicId)->where('active', true);
        $total = (clone $base)->count();
        $ready = (clone $base)->whereHas('fiscalProfile', fn ($query) => $query->where('ready', true))->count();

        return ['total' => $total, 'ready' => $ready, 'pending' => $total - $ready];
    }
}
