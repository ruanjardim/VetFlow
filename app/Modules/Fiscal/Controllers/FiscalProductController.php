<?php

namespace App\Modules\Fiscal\Controllers;

use App\Modules\Fiscal\Requests\UpdateProductFiscalProfileRequest;
use App\Modules\Fiscal\Services\FiscalTaxProfileService;
use App\Modules\Fiscal\Support\ResolvesFiscalClinic;
use App\Modules\Products\Models\Product;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FiscalProductController
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

        $query = Product::query()
            ->with('fiscalProfile')
            ->where('clinic_id', $clinicId)
            ->where('active', true)
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = trim((string) $request->query('search'));
                $query->where(fn ($nested) => $nested
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('gtin', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%"));
            })
            ->when($status === 'ready', fn ($query) => $query->whereHas('fiscalProfile', fn ($profile) => $profile->where('ready', true)))
            ->when($status === 'pending', fn ($query) => $query->whereDoesntHave('fiscalProfile', fn ($profile) => $profile->where('ready', true)))
            ->orderBy('name');

        return view('fiscal.products.index', [
            'products' => $query->paginate(20)->withQueryString(),
            'stats' => $this->stats($clinicId),
            'status' => $status,
            'clinics' => $this->availableClinics($this->tenant),
            'selectedClinicId' => $clinicId,
            'requiresClinic' => $this->tenant->isGlobal(),
        ]);
    }

    public function edit(Request $request, int $product): View
    {
        $clinicId = $this->selectedClinicId($request, $this->tenant);
        $productModel = Product::query()->where('clinic_id', $clinicId)->findOrFail($product);

        return view('fiscal.products.edit', [
            'product' => $productModel,
            'profile' => $this->profiles->productProfile($productModel),
            'missingLabels' => FiscalTaxProfileService::missingLabels($productModel->fiscalProfile, 'product'),
            'selectedClinicId' => $clinicId,
            'requiresClinic' => $this->tenant->isGlobal(),
        ]);
    }

    public function update(UpdateProductFiscalProfileRequest $request, int $product): RedirectResponse
    {
        $clinicId = $this->selectedClinicId($request, $this->tenant);
        $productModel = Product::query()->where('clinic_id', $clinicId)->findOrFail($product);
        $profile = $this->profiles->updateProduct($productModel, $request->validated());

        return redirect()
            ->route('fiscal.products.index', $this->fiscalRouteClinic($clinicId, $this->tenant))
            ->with($profile->ready ? 'success' : 'warning', $profile->ready
                ? 'Cadastro tributário do produto pronto para homologação.'
                : 'Cadastro salvo, mas ainda há campos mínimos pendentes.');
    }

    /** @return array{total: int, ready: int, pending: int} */
    private function stats(int $clinicId): array
    {
        $base = Product::query()->where('clinic_id', $clinicId)->where('active', true);
        $total = (clone $base)->count();
        $ready = (clone $base)->whereHas('fiscalProfile', fn ($query) => $query->where('ready', true))->count();

        return ['total' => $total, 'ready' => $ready, 'pending' => $total - $ready];
    }
}
