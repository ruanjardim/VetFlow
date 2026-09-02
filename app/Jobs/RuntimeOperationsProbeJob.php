<?php

namespace App\Jobs;

use App\Support\Operations\RuntimeOperationsProbeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RuntimeOperationsProbeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    /** @var array<int, int> */
    public array $backoff = [5, 15];

    public function __construct(
        public readonly string $probeId,
        public readonly string $disk,
        public readonly string $sentinelSha256,
    ) {}

    public function handle(RuntimeOperationsProbeService $probes): void
    {
        $probes->process($this->probeId, $this->disk, $this->sentinelSha256);
    }
}
