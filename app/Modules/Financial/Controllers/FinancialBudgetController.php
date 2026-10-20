<?php

namespace App\Modules\Financial\Controllers;

use App\Modules\Clinics\Models\Clinic;
use App\Modules\Financial\Requests\CopyPreviousFinancialBudgetRequest;
use App\Modules\Financial\Requests\FinancialBudgetRequest;
use App\Modules\Financial\Requests\SaveFinancialBudgetRequest;
use App\Modules\Financial\Services\FinancialBudgetService;
use App\Modules\Financial\Services\FinancialManagerialResultService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class FinancialBudgetController
{
    public function __construct(
        private readonly FinancialBudgetService $budgets,
        private readonly TenantContext $tenant
    ) {}

    public function index(FinancialBudgetRequest $request): View
    {
        $filters = $request->validated();
        $clinicId = $this->selectedClinicId($filters);

        return view('financial-budgets.index', $this->budgets->report(
            $clinicId,
            $filters['competence'] ?? null,
            $filters['basis'] ?? null,
        ) + [
            'basisLabels' => FinancialManagerialResultService::BASIS_LABELS,
            'clinics' => $this->availableClinics(),
            'selectedClinicId' => $clinicId,
            'requiresClinic' => $this->tenant->isGlobal(),
        ]);
    }

    public function update(SaveFinancialBudgetRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $clinicId = $this->selectedClinicId($data);
        $this->budgets->save($clinicId, $data['competence'], $data['budgets']);

        return redirect()
            ->route('financial-budgets.index', [
                'competence' => $data['competence'],
                'basis' => $data['basis'],
                ...$this->routeClinic($clinicId),
            ])
            ->with('success', 'Orçamento mensal atualizado com sucesso.');
    }

    public function copyPrevious(CopyPreviousFinancialBudgetRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $clinicId = $this->selectedClinicId($data);
        $result = $this->budgets->copyPrevious($clinicId, $data['competence']);
        $route = [
            'competence' => $data['competence'],
            'basis' => $data['basis'],
            ...$this->routeClinic($clinicId),
        ];

        if ($result['copied_count'] === 0 && $result['preserved_count'] === 0) {
            return redirect()
                ->route('financial-budgets.index', $route)
                ->with('warning', 'O mês anterior não possui valores de orçamento para copiar.');
        }

        $message = $result['copied_count'] === 1
            ? '1 valor copiado de '.$result['source_label'].'.'
            : $result['copied_count'].' valores copiados de '.$result['source_label'].'.';
        if ($result['preserved_count'] > 0) {
            $message .= $result['preserved_count'] === 1
                ? ' 1 valor já preenchido foi preservado.'
                : ' '.$result['preserved_count'].' valores já preenchidos foram preservados.';
        }

        return redirect()
            ->route('financial-budgets.index', $route)
            ->with($result['copied_count'] > 0 ? 'success' : 'warning', $message);
    }

    /** @param array<string, mixed> $filters */
    private function selectedClinicId(array $filters): int
    {
        if (! $this->tenant->isGlobal()) {
            return (int) $this->tenant->clinicId();
        }

        $requestedId = (int) ($filters['clinic_id'] ?? 0);
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
