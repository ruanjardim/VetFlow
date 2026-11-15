<?php

namespace App\Modules\Fiscal\Controllers;

use App\Modules\Clinics\Models\Clinic;
use App\Modules\Fiscal\Requests\FiscalDocumentCenterRequest;
use App\Modules\Fiscal\Services\FiscalDocumentCenterService;
use App\Modules\Fiscal\Support\FiscalDocumentType;
use App\Modules\Fiscal\Support\ResolvesFiscalClinic;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FiscalDocumentCenterController
{
    use ResolvesFiscalClinic;

    public function __construct(
        private readonly FiscalDocumentCenterService $documents,
        private readonly TenantContext $tenant,
    ) {}

    public function index(FiscalDocumentCenterRequest $request): View
    {
        [$clinic, $from, $to, $filters] = $this->context($request);
        $report = $this->documents->search($clinic->id, $from, $to, $filters);
        $report['documents']
            ->withPath(route('fiscal.documents.index'))
            ->appends($request->except('page'));

        return view('fiscal.documents.index', [
            'clinic' => $clinic,
            'documents' => $report['documents'],
            'summary' => $report['summary'],
            'filters' => $filters + [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'types' => FiscalDocumentType::labels(),
            'statusLabels' => FiscalDocumentCenterService::STATUS_LABELS,
            'clinics' => $this->availableClinics($this->tenant),
            'selectedClinicId' => (int) $clinic->id,
            'requiresClinic' => $this->tenant->isGlobal(),
            'documentCenter' => $this->documents,
        ]);
    }

    public function export(FiscalDocumentCenterRequest $request): StreamedResponse
    {
        [$clinic, $from, $to, $filters] = $this->context($request);
        $rows = $this->documents->rowsForExport($clinic->id, $from, $to, $filters);
        $fileName = 'vetflow-documentos-fiscais-'
            .Str::slug($clinic->trade_name ?: $clinic->corporate_name)
            .'-'.$filters['competence'].'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, [
                'ID', 'Preparado em', 'Venda', 'Tipo', 'Situação', 'Ambiente',
                'Número', 'Série', 'Chave de acesso', 'Valor', 'Autorizado em',
                'Cancelado em', 'Responsável',
            ], ',', '"', '');

            foreach ($rows as $document) {
                fputcsv($output, [
                    $document->id,
                    $document->created_at?->format('d/m/Y H:i'),
                    $this->csvSafe($document->sale?->code ?? 'Venda removida'),
                    FiscalDocumentType::labels()[$document->type] ?? strtoupper($document->type),
                    $this->documents->statusLabel($document->status),
                    $document->environment === 'homologation' ? 'Homologação' : ucfirst($document->environment),
                    $this->csvSafe($document->document_number, true),
                    $this->csvSafe($document->series, true),
                    $this->csvSafe($document->access_key, true),
                    number_format($this->documents->totalCents($document) / 100, 2, ',', '.'),
                    $document->authorized_at?->format('d/m/Y H:i'),
                    $document->cancelled_at?->format('d/m/Y H:i'),
                    $this->csvSafe($document->creator?->name ?? 'Sistema'),
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

    /**
     * @return array{Clinic, CarbonImmutable, CarbonImmutable, array{competence: string, type: ?string, status: ?string, sale: ?string}}
     */
    private function context(FiscalDocumentCenterRequest $request): array
    {
        $clinicId = $this->selectedClinicId($request, $this->tenant);
        $clinic = Clinic::query()->findOrFail($clinicId);
        $data = $request->validated();
        $competence = CarbonImmutable::createFromFormat(
            'Y-m-d',
            ($data['competence'] ?? now()->format('Y-m')).'-01'
        );
        $from = CarbonImmutable::parse($data['from'] ?? $competence->startOfMonth()->toDateString());
        $to = CarbonImmutable::parse($data['to'] ?? $competence->endOfMonth()->toDateString());

        return [
            $clinic,
            $from,
            $to,
            [
                'competence' => $competence->format('Y-m'),
                'type' => $data['type'] ?? null,
                'status' => $data['status'] ?? null,
                'sale' => filled($data['sale'] ?? null) ? trim((string) $data['sale']) : null,
            ],
        ];
    }

    private function csvSafe(mixed $value, bool $forceText = false): string
    {
        $value = (string) $value;

        return $forceText || preg_match('/^[=+\-@]/u', $value) ? "'{$value}" : $value;
    }
}
