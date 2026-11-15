<?php

namespace App\Modules\Financial\Controllers;

use App\Modules\Financial\Requests\FinancialManagerialResultRequest;
use App\Modules\Financial\Services\FinancialManagerialResultService;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialManagerialResultController
{
    public function __construct(private readonly FinancialManagerialResultService $results) {}

    public function index(FinancialManagerialResultRequest $request): View
    {
        $filters = $request->validated();

        return view('financial-results.index', $this->results->report(
            $filters['competence'] ?? null,
            $filters['basis'] ?? null,
        ) + ['basisLabels' => FinancialManagerialResultService::BASIS_LABELS]);
    }

    public function export(FinancialManagerialResultRequest $request): StreamedResponse
    {
        $filters = $request->validated();
        $report = $this->results->report(
            $filters['competence'] ?? null,
            $filters['basis'] ?? null,
        );
        $fileName = 'vetflow-resultado-gerencial-'
            .$report['filters']['competence'].'-'
            .Str::slug(FinancialManagerialResultService::BASIS_LABELS[$report['filters']['basis']]).'.csv';

        return response()->streamDownload(function () use ($report): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, [
                'Competência', 'Base', 'Tipo', 'Categoria', 'Lançamentos', 'Valor', 'Participação no tipo',
            ], ',', '"', '');

            foreach ($report['rows'] as $row) {
                fputcsv($output, [
                    $report['reference']['label'],
                    $this->csvSafe(FinancialManagerialResultService::BASIS_LABELS[$report['filters']['basis']]),
                    $row['type'] === 'income' ? 'Entrada' : 'Saída',
                    $this->csvSafe($row['category_name']),
                    $row['transactions_count'],
                    number_format($row['amount_cents'] / 100, 2, ',', '.'),
                    number_format((float) $row['share_percent'], 2, ',', '.').'%',
                ], ',', '"', '');
            }

            fputcsv($output, [], ',', '"', '');
            fputcsv($output, ['', '', 'TOTAL', 'Entradas', $report['stats']['income_count'], number_format($report['stats']['income_cents'] / 100, 2, ',', '.')], ',', '"', '');
            fputcsv($output, ['', '', 'TOTAL', 'Saídas', $report['stats']['expense_count'], number_format($report['stats']['expense_cents'] / 100, 2, ',', '.')], ',', '"', '');
            fputcsv($output, ['', '', 'TOTAL', 'Saldo', '', number_format($report['stats']['balance_cents'] / 100, 2, ',', '.')], ',', '"', '');

            fclose($output);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    private function csvSafe(mixed $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@]/u', $value) ? "'{$value}" : $value;
    }
}
