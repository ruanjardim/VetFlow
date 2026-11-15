<?php

namespace App\Modules\Financial\Controllers;

use App\Modules\Clinics\Models\Clinic;
use App\Modules\Financial\Requests\FinancialCashFlowRequest;
use App\Modules\Financial\Services\FinancialCashFlowService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialCashFlowController
{
    public function __construct(
        private readonly FinancialCashFlowService $cashFlow,
        private readonly TenantContext $tenant,
    ) {}

    public function index(FinancialCashFlowRequest $request): View
    {
        $filters = $request->validated();
        $clinicId = $this->selectedClinicId($filters);

        return view('financial-cash-flow.index', $this->cashFlow->report(
            $clinicId,
            $filters['from'] ?? null,
            isset($filters['days']) ? (int) $filters['days'] : null,
            $this->includeOverdue($filters),
        ) + $this->viewContext($clinicId));
    }

    public function transactions(FinancialCashFlowRequest $request): View
    {
        $filters = $request->validated();
        abort_if(empty($filters['date']), 404, 'Informe a data da composição.');
        $clinicId = $this->selectedClinicId($filters);
        $report = $this->cashFlow->report(
            $clinicId,
            $filters['from'] ?? null,
            isset($filters['days']) ? (int) $filters['days'] : null,
            $this->includeOverdue($filters),
        );
        $date = (string) $filters['date'];

        return view('financial-cash-flow.transactions', $report + [
            'selectedDate' => $date,
            'transactions' => $this->cashFlow->transactionsForDate(
                $clinicId,
                $date,
                $report['filters']['from'],
                $report['filters']['days'],
                $report['filters']['include_overdue'],
            ),
        ] + $this->viewContext($clinicId));
    }

    public function export(FinancialCashFlowRequest $request): StreamedResponse
    {
        $filters = $request->validated();
        $clinicId = $this->selectedClinicId($filters);
        $report = $this->cashFlow->report(
            $clinicId,
            $filters['from'] ?? null,
            isset($filters['days']) ? (int) $filters['days'] : null,
            $this->includeOverdue($filters),
        );
        $transactions = $this->cashFlow->exportTransactions(
            $clinicId,
            $report['filters']['from'],
            $report['filters']['days'],
            $report['filters']['include_overdue'],
        );
        $fileName = 'vetflow-fluxo-caixa-'
            .$report['filters']['from'].'-'
            .Str::slug(FinancialCashFlowService::HORIZON_LABELS[$report['filters']['days']]).'.csv';

        return response()->streamDownload(function () use ($report, $transactions): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, [
                'Data', 'Entradas realizadas', 'Saídas realizadas', 'Entradas previstas', 'Saídas previstas',
                'Entradas vencidas assumidas', 'Saídas vencidas assumidas', 'Saldo do dia', 'Saldo projetado', 'Lançamentos',
            ], ',', '"', '');

            foreach ($report['rows'] as $row) {
                fputcsv($output, [
                    $row['date']->format('d/m/Y'),
                    $this->money($row['actual_income_cents']),
                    $this->money($row['actual_expense_cents']),
                    $this->money($row['scheduled_income_cents']),
                    $this->money($row['scheduled_expense_cents']),
                    $this->money($row['overdue_income_cents']),
                    $this->money($row['overdue_expense_cents']),
                    $this->money($row['net_cents']),
                    $this->money($row['balance_cents']),
                    $row['transactions_count'],
                ], ',', '"', '');
            }

            fputcsv($output, [], ',', '"', '');
            fputcsv($output, ['COMPOSIÇÃO DOS LANÇAMENTOS'], ',', '"', '');
            fputcsv($output, [
                'Data considerada', 'Natureza', 'Tipo', 'Descrição', 'Categoria', 'Origem', 'Valor', 'Vencimento', 'Pagamento',
            ], ',', '"', '');

            foreach ($transactions as $transaction) {
                fputcsv($output, [
                    $transaction->cash_flow_kind === 'actual'
                        ? $transaction->paid_at?->format('d/m/Y')
                        : $transaction->due_date?->format('d/m/Y'),
                    $this->kindLabel($transaction->cash_flow_kind),
                    $transaction->type === 'income' ? 'Entrada' : 'Saída',
                    $this->csvSafe($transaction->description),
                    $this->csvSafe($transaction->category?->name ?? 'Sem categoria'),
                    $this->csvSafe($this->originLabel($transaction)),
                    number_format((float) $transaction->amount, 2, ',', '.'),
                    $transaction->due_date?->format('d/m/Y'),
                    $transaction->paid_at?->format('d/m/Y H:i'),
                ], ',', '"', '');
            }

            fclose($output);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
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

    /** @return array<string, mixed> */
    private function viewContext(int $clinicId): array
    {
        return [
            'clinics' => $this->availableClinics(),
            'selectedClinicId' => $clinicId,
            'requiresClinic' => $this->tenant->isGlobal(),
            'horizonLabels' => FinancialCashFlowService::HORIZON_LABELS,
        ];
    }

    private function availableClinics(): Collection
    {
        return $this->tenant->isGlobal()
            ? Clinic::query()->where('active', true)->orderBy('trade_name')->orderBy('corporate_name')->get()
            : collect();
    }

    /** @param array<string, mixed> $filters */
    private function includeOverdue(array $filters): bool
    {
        return ! array_key_exists('include_overdue', $filters) || (bool) $filters['include_overdue'];
    }

    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.');
    }

    private function csvSafe(mixed $value): string
    {
        $value = (string) $value;

        return preg_match('/^[\x00-\x20]*[=+\-@]/u', $value) ? "'{$value}" : $value;
    }

    private function kindLabel(string $kind): string
    {
        return match ($kind) {
            'actual' => 'Realizado',
            'overdue' => 'Vencido assumido na data inicial',
            default => 'Previsto no vencimento',
        };
    }

    private function originLabel(mixed $transaction): string
    {
        if ($transaction->salesCommissionSettlement) {
            return 'Comissão de vendas · '.($transaction->salesCommissionSettlement->seller?->name ?? '-');
        }

        if ($transaction->groomingCommissionSettlement) {
            return 'Comissão Banho & Tosa · '.($transaction->groomingCommissionSettlement->user?->name ?? '-');
        }

        if ($transaction->purchaseEntry) {
            return 'Compra '.$transaction->purchaseEntry->code;
        }

        if ($transaction->sale) {
            return 'Venda '.$transaction->sale->code;
        }

        return $transaction->supplier?->name ?: ($transaction->reference ?: 'Manual');
    }
}
