<?php

namespace App\Modules\Fiscal\Services;

use App\Modules\Fiscal\Models\FiscalDocument;
use App\Modules\Fiscal\Support\FiscalDocumentType;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

class FiscalDocumentCenterService
{
    public const STATUS_LABELS = [
        'draft' => 'Preparado',
        'queued' => 'Na fila',
        'processing' => 'Processando',
        'authorized' => 'Autorizado',
        'cancellation_queued' => 'Cancelamento na fila',
        'cancellation_failed' => 'Falha no cancelamento',
        'rejected' => 'Rejeitado',
        'cancelled' => 'Cancelado',
        'failed' => 'Falhou',
    ];

    private const PENDING_STATUSES = ['draft', 'queued', 'processing', 'cancellation_queued'];

    private const ISSUE_STATUSES = ['rejected', 'failed', 'cancellation_failed'];

    /**
     * @param  array{type: ?string, status: ?string, sale: ?string}  $filters
     * @return array{documents: LengthAwarePaginator, summary: array<string, int>}
     */
    public function search(
        int $clinicId,
        CarbonImmutable $from,
        CarbonImmutable $to,
        array $filters,
        int $perPage = 25,
    ): array {
        $query = $this->filteredQuery($clinicId, $from, $to, $filters);
        $summary = $this->summarize((clone $query)->get(['type', 'status', 'totals_snapshot']));
        $documents = (clone $query)
            ->with([
                'sale:id,clinic_id,code,status,total,completed_at',
                'creator:id,name',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return [
            'documents' => $documents,
            'summary' => $summary,
        ];
    }

    /**
     * @param  array{type: ?string, status: ?string, sale: ?string}  $filters
     * @return LazyCollection<int, FiscalDocument>
     */
    public function rowsForExport(
        int $clinicId,
        CarbonImmutable $from,
        CarbonImmutable $to,
        array $filters,
    ): LazyCollection {
        return $this->filteredQuery($clinicId, $from, $to, $filters)
            ->with([
                'sale:id,clinic_id,code,status,total,completed_at',
                'creator:id,name',
            ])
            ->lazyById(500);
    }

    public function statusLabel(string $status): string
    {
        return self::STATUS_LABELS[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    public function statusSeverity(string $status): string
    {
        return match ($status) {
            'authorized' => 'success',
            'rejected', 'failed', 'cancellation_failed' => 'danger',
            'draft', 'queued', 'processing', 'cancellation_queued' => 'warning',
            default => '',
        };
    }

    public function totalCents(FiscalDocument $document): int
    {
        return $this->cents(data_get($document->totals_snapshot, 'total', 0));
    }

    /**
     * @param  array{type: ?string, status: ?string, sale: ?string}  $filters
     * @return Builder<FiscalDocument>
     */
    private function filteredQuery(
        int $clinicId,
        CarbonImmutable $from,
        CarbonImmutable $to,
        array $filters,
    ): Builder {
        $saleSearch = trim((string) ($filters['sale'] ?? ''));

        return FiscalDocument::query()
            ->where('clinic_id', $clinicId)
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->when($filters['type'] ?? null, fn (Builder $query, string $type): Builder => $query->where('type', $type))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('status', $status))
            ->when($saleSearch !== '', function (Builder $query) use ($saleSearch): void {
                $query->whereHas('sale', function (Builder $saleQuery) use ($saleSearch): void {
                    $saleQuery->where(function (Builder $match) use ($saleSearch): void {
                        $match->where('code', 'like', '%'.$saleSearch.'%');
                        if (ctype_digit($saleSearch)) {
                            $match->orWhere('id', (int) $saleSearch);
                        }
                    });
                });
            });
    }

    /** @return array<string, int> */
    private function summarize(Collection $documents): array
    {
        return [
            'total' => $documents->count(),
            'total_cents' => $documents->sum(fn (FiscalDocument $document): int => $this->totalCents($document)),
            'nfce' => $documents->where('type', FiscalDocumentType::NFCE)->count(),
            'nfse' => $documents->where('type', FiscalDocumentType::NFSE)->count(),
            'authorized' => $documents->where('status', 'authorized')->count(),
            'cancelled' => $documents->where('status', 'cancelled')->count(),
            'pending' => $documents->whereIn('status', self::PENDING_STATUSES)->count(),
            'issues' => $documents->whereIn('status', self::ISSUE_STATUSES)->count(),
        ];
    }

    private function cents(mixed $value): int
    {
        $normalized = trim((string) $value);
        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '+-');
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $whole = preg_replace('/\D+/', '', $whole) ?: '0';
        $fraction = substr(str_pad(preg_replace('/\D+/', '', $fraction) ?: '', 2, '0'), 0, 2);
        $cents = ((int) $whole * 100) + (int) $fraction;

        return $negative ? -$cents : $cents;
    }
}
