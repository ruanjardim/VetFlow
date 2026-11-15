<?php

namespace App\Modules\Financial\Controllers;

use App\Modules\Clinics\Models\Clinic;
use App\Modules\Financial\Models\FinancialCategory;
use App\Modules\Financial\Requests\SaveFinancialCategoryRequest;
use App\Modules\Financial\Services\FinancialCategoryService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class FinancialCategoryController
{
    public function __construct(
        private readonly FinancialCategoryService $categories,
        private readonly TenantContext $tenant
    ) {}

    public function index(Request $request): View
    {
        $clinicId = $this->selectedClinicId($request);

        return view('financial-categories.index', $this->viewData([
            'categories' => $this->categories->forClinic($clinicId),
        ], $clinicId));
    }

    public function create(Request $request): View
    {
        $clinicId = $this->selectedClinicId($request);

        return view('financial-categories.create', $this->viewData([
            'category' => null,
        ], $clinicId));
    }

    public function store(SaveFinancialCategoryRequest $request): RedirectResponse
    {
        $clinicId = $this->selectedClinicId($request);
        $category = $this->categories->create($clinicId, $request->validated());

        return redirect()
            ->route('financial-categories.index', $this->routeClinic($clinicId))
            ->with('success', 'Categoria "'.$category->name.'" cadastrada.');
    }

    public function edit(Request $request, int $financialCategory): View
    {
        $clinicId = $this->selectedClinicId($request);

        return view('financial-categories.edit', $this->viewData([
            'category' => $this->categories->findForClinic($clinicId, $financialCategory),
        ], $clinicId));
    }

    public function update(SaveFinancialCategoryRequest $request, int $financialCategory): RedirectResponse
    {
        $clinicId = $this->selectedClinicId($request);
        $category = $this->categories->update(
            $this->categories->findForClinic($clinicId, $financialCategory),
            $request->validated()
        );

        return redirect()
            ->route('financial-categories.index', $this->routeClinic($clinicId))
            ->with('success', 'Categoria "'.$category->name.'" atualizada.');
    }

    /** @param array<string, mixed> $data */
    private function viewData(array $data, int $clinicId): array
    {
        return $data + [
            'typeLabels' => FinancialCategory::TYPE_LABELS,
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
