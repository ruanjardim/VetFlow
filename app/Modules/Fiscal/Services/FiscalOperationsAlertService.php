<?php

namespace App\Modules\Fiscal\Services;

use App\Modules\Fiscal\Models\FiscalDocument;
use Illuminate\Database\Eloquent\Builder;

class FiscalOperationsAlertService
{
    /** @return array{total: int, critical_count: int, warning_count: int, displayed_count: int, items: array<int, array<string, mixed>>} */
    public function forClinic(int $clinicId): array
    {
        $processingMinutes = max(1, (int) config('fiscal.alerts.processing_stale_minutes', 180));
        $cancellationMinutes = max(1, (int) config('fiscal.alerts.cancellation_stale_minutes', 30));
        $maxItems = min(100, max(1, (int) config('fiscal.alerts.max_items', 20)));
        $processingCutoff = now()->subMinutes($processingMinutes);
        $cancellationCutoff = now()->subMinutes($cancellationMinutes);

        $query = FiscalDocument::withoutGlobalScopes()
            ->where('clinic_id', $clinicId)
            ->where(function (Builder $query) use ($processingCutoff, $cancellationCutoff): void {
                $query->whereIn('status', ['failed', 'cancellation_failed'])
                    ->orWhereIn('last_error_code', ['provider_status_failed', 'webhook_resend_failed'])
                    ->orWhere(function (Builder $query) use ($processingCutoff): void {
                        $query->where('status', 'processing')
                            ->where(function (Builder $query) use ($processingCutoff): void {
                                $query->where('last_error_code', 'provider_status_failed')
                                    ->orWhere('updated_at', '<=', $processingCutoff);
                            });
                    })
                    ->orWhere(function (Builder $query) use ($cancellationCutoff): void {
                        $query->where('status', 'cancellation_queued')
                            ->where('updated_at', '<=', $cancellationCutoff);
                    });
            });

        $total = (clone $query)->count();
        $criticalCount = (clone $query)
            ->where(function (Builder $query): void {
                $query->whereIn('status', ['failed', 'cancellation_failed'])
                    ->orWhereIn('last_error_code', ['provider_status_failed', 'webhook_resend_failed']);
            })
            ->count();

        $documents = (clone $query)
            ->with('sale')
            ->orderByRaw("CASE WHEN status IN ('failed', 'cancellation_failed') OR last_error_code IN ('provider_status_failed', 'webhook_resend_failed') THEN 0 ELSE 1 END")
            ->oldest('updated_at')
            ->limit($maxItems)
            ->get()
            ->map(fn (FiscalDocument $document): array => $this->alertFor($document))
            ->values();

        return [
            'total' => $total,
            'critical_count' => $criticalCount,
            'warning_count' => $total - $criticalCount,
            'displayed_count' => $documents->count(),
            'items' => $documents->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function alertFor(FiscalDocument $document): array
    {
        [$kind, $severity, $title, $message] = match (true) {
            $document->status === 'failed' => [
                'submission_failed', 'danger', 'Transmissão sem conclusão',
                'As tentativas de transmissão foram esgotadas. Revise o documento e a situação no provedor.',
            ],
            $document->status === 'cancellation_failed' => [
                'cancellation_failed', 'danger', 'Cancelamento não confirmado',
                'O provedor não confirmou o cancelamento. Confira a situação externa antes de repetir a operação.',
            ],
            $document->last_error_code === 'webhook_resend_failed' => [
                'webhook_resend_failed', 'danger', 'Reenvio do webhook sem conclusão',
                'As tentativas de solicitar uma nova notificação ao provedor foram esgotadas.',
            ],
            $document->last_error_code === 'provider_status_failed' => [
                'synchronization_failed', 'danger', 'Consulta sem conclusão',
                'As tentativas de consultar a situação atual no provedor foram esgotadas.',
            ],
            $document->status === 'cancellation_queued' => [
                'cancellation_stale', 'warning', 'Cancelamento demorando',
                'O pedido de cancelamento permanece na fila além do prazo operacional configurado.',
            ],
            default => [
                'processing_stale', 'warning', 'Processamento demorando',
                'O documento permanece em processamento além do prazo operacional configurado.',
            ],
        };

        return [
            'document' => $document,
            'kind' => $kind,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'updated_at' => $document->updated_at,
        ];
    }
}
