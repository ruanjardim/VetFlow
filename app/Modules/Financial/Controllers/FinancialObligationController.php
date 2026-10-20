<?php

namespace App\Modules\Financial\Controllers;

use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Financial\Requests\FinancialObligationRequest;
use App\Modules\Financial\Requests\StoreFinancialObligationReviewRequest;
use App\Modules\Financial\Services\FinancialObligationService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialObligationController
{
    public function __construct(
        private readonly FinancialObligationService $obligations,
        private readonly TenantContext $tenant
    ) {}

    public function index(FinancialObligationRequest $request): View
    {
        $filters = $request->validated();
        $clinicId = $this->selectedClinicId($filters);

        return view('financial-obligations.index', $this->obligations->report(
            $clinicId,
            $filters['from'] ?? null,
            $filters['to'] ?? null,
            $filters['type'] ?? null,
            $filters['status'] ?? null,
            $filters['aging'] ?? null,
        ) + [
            'clinics' => $this->availableClinics(),
            'selectedClinicId' => $clinicId,
            'requiresClinic' => $this->tenant->isGlobal(),
            'typeLabels' => FinancialObligationService::TYPE_LABELS,
            'statusLabels' => FinancialObligationService::STATUS_LABELS,
            'agingLabels' => FinancialObligationService::AGING_LABELS,
            'reviewLabels' => FinancialObligationService::REVIEW_LABELS,
        ]);
    }

    public function export(FinancialObligationRequest $request): StreamedResponse
    {
        $filters = $request->validated();
        $clinicId = $this->selectedClinicId($filters);
        $export = $this->obligations->exportRows(
            $clinicId,
            $filters['from'] ?? null,
            $filters['to'] ?? null,
            $filters['type'] ?? null,
            $filters['status'] ?? null,
            $filters['aging'] ?? null,
        );
        $normalized = $export['filters'];
        $fileName = 'vetflow-contas-'
            .$normalized['from'].'-a-'.$normalized['to'].'-'
            .Str::slug(FinancialObligationService::TYPE_LABELS[$normalized['type']]).'.csv';

        return response()->streamDownload(function () use ($export): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, [
                'Vencimento', 'Dias em atraso', 'Tipo', 'Descrição', 'Categoria', 'Origem', 'Situação', 'Valor', 'Parcela',
            ], ',', '"', '');

            foreach ($export['rows'] as $transaction) {
                fputcsv($output, [
                    $transaction->due_date?->format('d/m/Y'),
                    $transaction->due_date?->isBefore(today())
                        ? (int) $transaction->due_date->diffInDays(today())
                        : 0,
                    $transaction->type === 'income' ? 'A receber' : 'A pagar',
                    $this->csvSafe($transaction->description),
                    $this->csvSafe($transaction->category?->name ?? 'Sem categoria'),
                    $this->csvSafe($transaction->supplier?->name ?: ($transaction->sale ? 'Venda '.$transaction->sale->code : '')),
                    $this->statusLabel($transaction),
                    number_format((float) $transaction->amount, 2, ',', '.'),
                    (int) $transaction->installment_total > 1
                        ? $transaction->installment_number.'/'.$transaction->installment_total
                        : '',
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

    public function storeReview(StoreFinancialObligationReviewRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $clinicId = $this->selectedClinicId($data);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $this->obligations->recordReview($clinicId, $actor, $data, $data);

        return redirect()
            ->route('financial-obligations.index', [
                'from' => $data['from'],
                'to' => $data['to'],
                'type' => $data['type'],
                'status' => $data['status'],
                'aging' => $data['aging'],
                ...$this->routeClinic($clinicId),
            ])
            ->with('success', 'Revisão financeira registrada no histórico.');
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

        return preg_match('/^[\x00-\x20]*[=+\-@]/u', $value) ? "'{$value}" : $value;
    }

    private function statusLabel(mixed $transaction): string
    {
        if ($transaction->status === 'paid') {
            return $transaction->type === 'income' ? 'Recebida' : 'Paga';
        }

        if ($transaction->status === 'cancelled') {
            return 'Cancelada';
        }

        if ($transaction->due_date?->isBefore(today())) {
            return 'Vencida';
        }

        return $transaction->due_date?->isToday() ? 'Vence hoje' : 'A vencer';
    }
}
