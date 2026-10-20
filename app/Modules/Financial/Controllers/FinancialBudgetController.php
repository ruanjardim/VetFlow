<?php

namespace App\Modules\Financial\Controllers;

use App\Modules\Clinics\Models\Clinic;
use App\Modules\Financial\Requests\CopyPreviousFinancialBudgetRequest;
use App\Modules\Financial\Requests\FinancialBudgetDetailRequest;
use App\Modules\Financial\Requests\FinancialBudgetRequest;
use App\Modules\Financial\Requests\SaveFinancialBudgetRequest;
use App\Modules\Financial\Services\FinancialBudgetService;
use App\Modules\Financial\Services\FinancialManagerialResultService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function export(FinancialBudgetRequest $request): StreamedResponse
    {
        $filters = $request->validated();
        $clinicId = $this->selectedClinicId($filters);
        $report = $this->budgets->report(
            $clinicId,
            $filters['competence'] ?? null,
            $filters['basis'] ?? null,
        );
        $fileName = 'vetflow-orcamento-'
            .$report['filters']['competence'].'-'
            .Str::slug(FinancialManagerialResultService::BASIS_LABELS[$report['filters']['basis']]).'.csv';

        return response()->streamDownload(function () use ($report): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, [
                'Competência', 'Base', 'Tipo', 'Categoria', 'Previsto', 'Realizado', 'Diferença', 'Execução', 'Situação',
            ], ',', '"', '');

            foreach ($report['income_rows']->concat($report['expense_rows']) as $row) {
                fputcsv($output, [
                    $report['reference']['label'],
                    $this->csvSafe(FinancialManagerialResultService::BASIS_LABELS[$report['filters']['basis']]),
                    $row['type'] === 'income' ? 'Entrada' : 'Saída',
                    $this->csvSafe($row['category_name']),
                    number_format($row['planned_cents'] / 100, 2, ',', '.'),
                    number_format($row['actual_cents'] / 100, 2, ',', '.'),
                    number_format($row['variance_cents'] / 100, 2, ',', '.'),
                    $row['execution_percent'] === null ? '' : number_format($row['execution_percent'], 2, ',', '.').'%',
                    $this->csvSafe($row['status_label']),
                ], ',', '"', '');
            }

            fputcsv($output, [], ',', '"', '');
            fputcsv($output, ['', '', 'TOTAL', 'Entradas', number_format($report['stats']['planned_income_cents'] / 100, 2, ',', '.'), number_format($report['stats']['actual_income_cents'] / 100, 2, ',', '.'), number_format($report['stats']['income_variance_cents'] / 100, 2, ',', '.')], ',', '"', '');
            fputcsv($output, ['', '', 'TOTAL', 'Saídas', number_format($report['stats']['planned_expense_cents'] / 100, 2, ',', '.'), number_format($report['stats']['actual_expense_cents'] / 100, 2, ',', '.'), number_format($report['stats']['expense_variance_cents'] / 100, 2, ',', '.')], ',', '"', '');
            fputcsv($output, ['', '', 'TOTAL', 'Saldo', number_format($report['stats']['planned_balance_cents'] / 100, 2, ',', '.'), number_format($report['stats']['actual_balance_cents'] / 100, 2, ',', '.'), number_format($report['stats']['balance_variance_cents'] / 100, 2, ',', '.')], ',', '"', '');

            fclose($output);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    public function transactions(FinancialBudgetDetailRequest $request): View
    {
        $filters = $request->validated();
        $clinicId = $this->selectedClinicId($filters);

        return view('financial-budgets.transactions', $this->budgets->transactions(
            $clinicId,
            $filters['competence'],
            $filters['basis'],
            $filters['type'],
            isset($filters['financial_category_id']) ? (int) $filters['financial_category_id'] : null,
        ) + [
            'basisLabels' => FinancialManagerialResultService::BASIS_LABELS,
            'requiresClinic' => $this->tenant->isGlobal(),
            'selectedClinicId' => $clinicId,
        ]);
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

    private function csvSafe(mixed $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@]/u', $value) ? "'{$value}" : $value;
    }
}
