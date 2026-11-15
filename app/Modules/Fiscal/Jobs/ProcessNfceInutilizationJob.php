<?php

namespace App\Modules\Fiscal\Jobs;

use App\Modules\Fiscal\Services\FiscalNfceInutilizationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessNfceInutilizationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    public int $timeout = 30;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public readonly int $inutilizationId) {}

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('fiscal-nfce-inutilization-'.$this->inutilizationId))->releaseAfter(30)->expireAfter(120)];
    }

    public function handle(FiscalNfceInutilizationService $service): void
    {
        $service->process($this->inutilizationId);
    }

    public function failed(?Throwable $exception): void
    {
        app(FiscalNfceInutilizationService::class)->markFailed(
            $this->inutilizationId,
            $exception ? $exception::class : Throwable::class
        );
    }
}
