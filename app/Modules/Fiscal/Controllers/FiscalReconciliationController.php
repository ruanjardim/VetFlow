<?php

namespace App\Modules\Fiscal\Controllers;

use App\Modules\Clinics\Models\Clinic;
use App\Modules\Fiscal\Models\FiscalPeriodClosure;
use App\Modules\Fiscal\Requests\FiscalPeriodClosureHistoryRequest;
use App\Modules\Fiscal\Requests\FiscalReconciliationRequest;
use App\Modules\Fiscal\Requests\StoreFiscalPeriodClosureRequest;
use App\Modules\Fiscal\Services\FiscalPeriodClosingService;
use App\Modules\Fiscal\Services\FiscalProfileService;
use App\Modules\Fiscal\Services\FiscalReconciliationService;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FiscalReconciliationController
{
    public function __construct(
        private readonly FiscalReconciliationService $reconciliation,
        private readonly FiscalPeriodClosingService $closures,
        private readonly FiscalProfileService $profiles,
        private readonly TenantContext $tenant,
    ) {}

    public function index(FiscalReconciliationRequest $request): View
    {
        [$clinic, $profile, $report, $from, $to, $status] = $this->context($request);

        return view('fiscal.reconciliation', [
            'clinic' => $clinic,
            'report' => $report,
            'clinics' => $this->availableClinics(),
            'selectedClinicId' => (int) $clinic->id,
            'requiresClinic' => $this->tenant->isGlobal(),
            'closing' => $this->closures->evaluate(
                $clinic,
                $profile,
                $from,
                $to,
                $status === null ? $report : null,
            ),
        ]);
    }

    public function export(FiscalReconciliationRequest $request): StreamedResponse
    {
        [, , $report] = $this->context($request);
        $fileName = 'vetflow-conciliacao-fiscal-'
            .$report['period']['from'].'-a-'.$report['period']['to'].'.csv';

        return response()->streamDownload(function () use ($report): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, ['Venda', 'Conclusão', 'Total', 'Tipos esperados', 'Situação', 'Detalhe', 'Próximo passo'], ',', '"', '');
            foreach ($report['rows'] as $row) {
                fputcsv($output, [
                    $this->csvSafe($row['sale_code']),
                    CarbonImmutable::parse($row['completed_at'])->format('d/m/Y H:i'),
                    number_format($row['sale_total_cents'] / 100, 2, ',', '.'),
                    implode(' + ', array_map('strtoupper', $row['expected_types'])),
                    $row['status_label'],
                    $this->csvSafe($row['detail']),
                    $this->csvSafe($row['next_action']),
                ], ',', '"', '');
            }
            fclose($output);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function storeClosure(StoreFiscalPeriodClosureRequest $request): RedirectResponse
    {
        $clinicId = $this->selectedClinicId($request);
        $clinic = Clinic::query()->findOrFail($clinicId);
        $data = $request->validated();
        $from = CarbonImmutable::parse($data['from']);
        $to = CarbonImmutable::parse($data['to']);

        $this->closures->record(
            $clinic,
            $this->profiles->forClinic($clinicId),
            $request->user(),
            $from,
            $to,
            $data,
        );

        return redirect()
            ->route('fiscal.reconciliation.index', array_filter([
                'clinic_id' => $this->tenant->isGlobal() ? $clinicId : null,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ]))
            ->with('success', 'Fechamento fiscal operacional registrado. Alterações futuras no período serão sinalizadas como evidência desatualizada.');
    }

    public function closureHistory(FiscalPeriodClosureHistoryRequest $request): View
    {
        $clinicId = $this->selectedClinicId($request);
        $clinic = Clinic::query()->findOrFail($clinicId);
        $data = $request->validated();
        $competence = CarbonImmutable::createFromFormat('Y-m-d', ($data['competence'] ?? now()->format('Y-m')).'-01');
        $history = $this->closures->history(
            $clinic,
            $this->profiles->forClinic($clinicId),
            $competence,
            $data,
            (int) ($data['page'] ?? 1),
        );
        $history['paginator']
            ->withPath(route('fiscal.reconciliation.closures.index'))
            ->appends($request->except('page'));

        return view('fiscal.period-closures', [
            'clinic' => $clinic,
            'history' => $history,
            'filters' => [
                'competence' => $competence->format('Y-m'),
                'reviewer' => $data['reviewer'] ?? '',
                'state' => $data['state'] ?? '',
            ],
            'stateLabels' => FiscalPeriodClosingService::HISTORY_STATES,
            'clinics' => $this->availableClinics(),
            'selectedClinicId' => $clinicId,
            'requiresClinic' => $this->tenant->isGlobal(),
        ]);
    }

    public function closureDossier(string $closure): View
    {
        [$closure, $clinic, $profile] = $this->closureContext($closure);

        return view('fiscal.period-closure-dossier', [
            'report' => $this->closures->dossier($clinic, $profile, $closure),
            'closure' => $closure,
            'requiresClinic' => $this->tenant->isGlobal(),
        ]);
    }

    public function closureDossierJson(string $closure): StreamedResponse
    {
        [$closure, $clinic, $profile] = $this->closureContext($closure);
        $report = $this->closures->dossier($clinic, $profile, $closure);
        $fileName = 'vetflow-fechamento-fiscal-'
            .$closure->period_from->format('Ymd').'-a-'.$closure->period_to->format('Ymd')
            .'-'.$closure->ulid.'.json';

        return response()->streamDownload(
            fn () => print json_encode(
                $report,
                JSON_PRETTY_PRINT
                    | JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                    | JSON_THROW_ON_ERROR
            ),
            $fileName,
            [
                'Content-Type' => 'application/json; charset=UTF-8',
                'Cache-Control' => 'no-store, private',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    /** @return array{Clinic, mixed, array<string, mixed>, CarbonImmutable, CarbonImmutable, string|null} */
    private function context(FiscalReconciliationRequest $request): array
    {
        $clinicId = $this->selectedClinicId($request);
        $clinic = Clinic::query()->findOrFail($clinicId);
        $data = $request->validated();
        $from = CarbonImmutable::parse($data['from'] ?? now()->toDateString());
        $to = CarbonImmutable::parse($data['to'] ?? $from->toDateString());
        $profile = $this->profiles->forClinic($clinicId);
        $status = $data['status'] ?? null;

        return [
            $clinic,
            $profile,
            $this->reconciliation->report(
                $clinicId,
                $profile,
                $from,
                $to,
                $status,
            ),
            $from,
            $to,
            $status,
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

    private function availableClinics()
    {
        return $this->tenant->isGlobal()
            ? Clinic::query()->where('active', true)->orderBy('trade_name')->orderBy('corporate_name')->get()
            : collect();
    }

    /** @return array{FiscalPeriodClosure, Clinic, mixed} */
    private function closureContext(string $closureUlid): array
    {
        $closure = FiscalPeriodClosure::query()
            ->with('closedBy')
            ->where('ulid', $closureUlid)
            ->firstOrFail();
        $clinic = Clinic::query()->findOrFail($closure->clinic_id);

        return [$closure, $clinic, $this->profiles->forClinic($clinic->id)];
    }

    private function csvSafe(mixed $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@]/', $value) ? "'{$value}" : $value;
    }
}
