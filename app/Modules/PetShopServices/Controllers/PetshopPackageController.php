<?php

namespace App\Modules\PetShopServices\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\PetShopServices\Models\PetshopPackage;
use App\Modules\PetShopServices\Models\PetShopService;
use App\Modules\PetShopServices\Services\PetPackageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Catalogo de pacotes (modelos) de banho e tosa. */
class PetshopPackageController extends Controller
{
    public function __construct(private readonly PetPackageService $packages) {}

    public function index(): View
    {
        return view('petshop-packages.index', [
            'packages' => PetshopPackage::query()->with('items.service')->orderByDesc('active')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('petshop-packages.create', $this->formData(null));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->packages->saveTemplate($this->validated($request));

        return redirect()->route('petshop-packages.index')->with('success', 'Pacote cadastrado.');
    }

    public function edit(int $petshopPackage): View
    {
        return view('petshop-packages.edit', $this->formData(
            PetshopPackage::query()->with('items')->findOrFail($petshopPackage)
        ));
    }

    public function update(Request $request, int $petshopPackage): RedirectResponse
    {
        $package = PetshopPackage::query()->findOrFail($petshopPackage);
        $this->packages->saveTemplate($this->validated($request), $package);

        return redirect()->route('petshop-packages.index')->with('success', 'Pacote atualizado. Pacotes já vendidos mantêm as condições da venda.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $clinicRequired = $request->user()?->clinic_id === null;
        $clinicId = $request->user()?->clinic_id ?? ($request->integer('clinic_id') ?: null);

        return $request->validate([
            'clinic_id' => [Rule::requiredIf($clinicRequired), 'nullable', 'integer', Rule::exists('clinics', 'id')->where('active', true)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0'],
            'validity_days' => ['nullable', 'integer', 'min:1', 'max:730'],
            'active' => ['nullable', 'boolean'],
            'items' => ['required', 'array'],
            'items.*.petshop_service_id' => ['nullable', 'integer', Rule::exists('petshop_services', 'id')->when(
                $clinicId !== null,
                fn ($rule) => $rule->where('clinic_id', $clinicId)
            )],
            'items.*.quantity' => ['nullable', 'integer', 'min:1', 'max:100'],
        ], [
            'name.required' => 'Informe o nome do pacote.',
            'price.required' => 'Informe o preço do pacote.',
            'items.*.petshop_service_id.exists' => 'Um dos serviços não foi encontrado.',
        ]);
    }

    /** @return array<string, mixed> */
    private function formData(?PetshopPackage $package): array
    {
        return [
            'package' => $package,
            'services' => PetShopService::query()->active()->orderBy('name')->get(),
            'clinics' => Clinic::query()->active()->orderBy('trade_name')->get(),
        ];
    }
}
