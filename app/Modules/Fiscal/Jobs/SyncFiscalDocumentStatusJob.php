<?php

namespace App\Modules\Fiscal\Jobs;

use App\Modules\Fiscal\Services\FiscalDocumentLifecycleService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SyncFiscalDocumentStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    public int $timeout = 30;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly int $documentId,
        public readonly int $checksRemaining = 1,
    ) {}

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('fiscal-document-'.$this->documentId))->releaseAfter(30)->expireAfter(120)];
    }

    public function handle(FiscalDocumentLifecycleService $lifecycle): void
    {
        $document = $lifecycle->synchronize($this->documentId);

        if ($document?->status === 'processing' && $this->checksRemaining > 1) {
            $completedChecks = 6 - $this->checksRemaining;
            $delayMinutes = [5, 15, 30, 60, 120][$completedChecks] ?? 120;

            self::dispatch($document->id, $this->checksRemaining - 1)
                ->onConnection((string) config('fiscal.queue.connection', 'database'))
                ->onQueue((string) config('fiscal.queue.name', 'fiscal'))
                ->delay(now()->addMinutes($delayMinutes));
        }
    }

    public function failed(?Throwable $exception): void
    {
        app(FiscalDocumentLifecycleService::class)->markSynchronizationFailed(
            $this->documentId,
            $exception ? $exception::class : Throwable::class
        );
    }
}
