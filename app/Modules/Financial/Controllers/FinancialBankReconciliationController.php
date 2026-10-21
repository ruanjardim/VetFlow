<?php

namespace App\Modules\Financial\Controllers;

use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Financial\Requests\FinancialBankReconciliationRequest;
use App\Modules\Financial\Requests\ReverseFinancialReconciliationRequest;
use App\Modules\Financial\Requests\StoreFinancialAccountRequest;
use App\Modules\Financial\Requests\StoreFinancialReconciliationRequest;
use App\Modules\Financial\Requests\StoreFinancialStatementImportRequest;
use App\Modules\Financial\Requests\UpdateFinancialAccountRequest;
use App\Modules\Financial\Services\FinancialBankReconciliationService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialBankReconciliationController
{
    public function __construct(
        private readonly FinancialBankReconciliationService $reconciliation,
        private readonly TenantContext $tenant,
    ) {}

    public function index(FinancialBankReconciliationRequest $request): View
    {
        $filters = $request->validated();
        $clinicId = $this->selectedClinicId($filters);

        return view('financial-bank-reconciliation.index', $this->reconciliation->report(
            $clinicId,
            isset($filters['account_id']) ? (int) $filters['account_id'] : null,
            $filters['from'] ?? null,
            $filters['to'] ?? null,
            $filters['status'] ?? null,
        ) + [
            'clinics' => $this->availableClinics(),
            'selectedClinicId' => $clinicId,
            'requiresClinic' => $this->tenant->isGlobal(),
            'accountTypeLabels' => FinancialBankReconciliationService::ACCOUNT_TYPE_LABELS,
            'statusLabels' => FinancialBankReconciliationService::STATUS_LABELS,
        ]);
    }

    public function storeAccount(StoreFinancialAccountRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $clinicId = $this->selectedClinicId($data);
        $account = $this->reconciliation->createAccount($clinicId, $this->actor($request->user()), $data);

        return redirect()
            ->route('financial-bank-reconciliation.index', $this->routeClinic($clinicId) + ['account_id' => $account->id])
            ->with('success', 'Conta financeira cadastrada com segurança.');
    }

    public function updateAccount(UpdateFinancialAccountRequest $request, int $account): RedirectResponse
    {
        $data = $request->validated();
        $clinicId = $this->selectedClinicId($data);
        $updated = $this->reconciliation->updateAccount($clinicId, $account, $this->actor($request->user()), $data);

        return redirect()
            ->route('financial-bank-reconciliation.index', $this->routeClinic($clinicId) + ['account_id' => $updated->id])
            ->with('success', 'Conta financeira atualizada.');
    }

    public function importStatement(StoreFinancialStatementImportRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $clinicId = $this->selectedClinicId($data);
        $result = $this->reconciliation->importStatement(
            $clinicId,
            (int) $data['account_id'],
            $this->actor($request->user()),
            $request->file('statement'),
        );

        return redirect()
            ->route('financial-bank-reconciliation.index', $this->routeClinic($clinicId) + [
                'account_id' => (int) $data['account_id'],
                'from' => $result['import']->period_start?->format('Y-m-d'),
                'to' => $result['import']->period_end?->format('Y-m-d'),
            ])
            ->with('success', "Extrato importado: {$result['imported']} novo(s) e {$result['duplicates']} duplicado(s) ignorado(s).");
    }

    public function reconcile(StoreFinancialReconciliationRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $clinicId = $this->selectedClinicId($data);
        $this->reconciliation->reconcile($clinicId, $this->actor($request->user()), $data);

        return redirect()
            ->route('financial-bank-reconciliation.index', $this->redirectFilters($clinicId, $data))
            ->with('success', 'Correspondência confirmada. O lançamento financeiro original não foi alterado.');
    }

    public function reverse(
        ReverseFinancialReconciliationRequest $request,
        int $reconciliation,
    ): RedirectResponse {
        $data = $request->validated();
        $clinicId = $this->selectedClinicId($data);
        $this->reconciliation->reverse(
            $clinicId,
            $reconciliation,
            $this->actor($request->user()),
            $data['reason'],
        );

        return redirect()
            ->route('financial-bank-reconciliation.index', $this->redirectFilters($clinicId, $data))
            ->with('success', 'Conciliação desfeita com motivo registrado na auditoria.');
    }

    public function export(FinancialBankReconciliationRequest $request): StreamedResponse
    {
        $filters = $request->validated();
        $clinicId = $this->selectedClinicId($filters);
        abort_if(empty($filters['account_id']), 422, 'Selecione uma conta para exportar.');
        $export = $this->reconciliation->exportRows(
            $clinicId,
            (int) $filters['account_id'],
            $filters['from'] ?? null,
            $filters['to'] ?? null,
            $filters['status'] ?? null,
        );
        $normalized = $export['filters'];
        $fileName = 'vetflow-conciliacao-bancaria-'
            .Str::slug($export['account']->name).'-'.$normalized['from'].'-a-'.$normalized['to'].'.csv';

        return response()->streamDownload(function () use ($export): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, [
                'Data no banco', 'Descrição do banco', 'Valor', 'Situação', 'Lançamento VetFlow',
                'Categoria', 'Data de pagamento', 'Responsável pela conciliação', 'Conciliado em',
            ], ',', '"', '');

            foreach ($export['rows'] as $entry) {
                $match = $entry->activeReconciliation;
                $transaction = $match?->financialTransaction;
                fputcsv($output, [
                    $entry->posted_at->format('d/m/Y'),
                    $this->csvSafe($entry->description),
                    number_format((float) $entry->amount, 2, ',', '.'),
                    $match ? 'Conciliado' : 'Pendente',
                    $this->csvSafe($transaction?->description ?? ''),
                    $this->csvSafe($transaction?->category?->name ?? ''),
                    $transaction?->paid_at?->format('d/m/Y'),
                    $this->csvSafe($match?->reconciledBy?->name ?? ''),
                    $match?->reconciled_at?->format('d/m/Y H:i'),
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

    private function availableClinics(): Collection
    {
        return $this->tenant->isGlobal()
            ? Clinic::query()->where('active', true)->orderBy('trade_name')->orderBy('corporate_name')->get()
            : collect();
    }

    private function actor(mixed $actor): User
    {
        abort_unless($actor instanceof User, 403);

        return $actor;
    }

    /** @return array<string, int> */
    private function routeClinic(int $clinicId): array
    {
        return $this->tenant->isGlobal() ? ['clinic_id' => $clinicId] : [];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function redirectFilters(int $clinicId, array $data): array
    {
        return array_filter([
            ...$this->routeClinic($clinicId),
            'account_id' => $data['account_id'] ?? null,
            'from' => $data['from'] ?? null,
            'to' => $data['to'] ?? null,
            'status' => $data['status'] ?? null,
        ], fn ($value): bool => $value !== null && $value !== '');
    }

    private function csvSafe(mixed $value): string
    {
        $value = (string) $value;

        return preg_match('/^[\x00-\x20]*[=+\-@]/u', $value) ? "'{$value}" : $value;
    }
}
