<?php

namespace App\Modules\Operations\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Operations\ReleaseIdentityService;
use App\Support\Operations\ReleaseReadinessService;
use App\Support\Operations\OperationalEvidenceService;
use Illuminate\View\View;

class OperationsController extends Controller
{
    public function __construct(
        private readonly ReleaseIdentityService $releaseIdentity,
        private readonly ReleaseReadinessService $readiness,
        private readonly OperationalEvidenceService $evidence,
    ) {}

    public function index(): View
    {
        $release = $this->releaseIdentity->payload();
        $evidence = $this->evidence->latest();
        $readiness = $this->readiness->evaluate(
            backupEvidencePath: $evidence['backup']['path'],
            runtimeEvidencePath: $evidence['runtime']['path'],
        );

        return view('operations.index', [
            'release' => $release['release'],
            'releaseAvailable' => $release['status'] === 'ok',
            'environment' => app()->environment(),
            'queueMode' => (string) config('operations.queue.mode', 'worker'),
            'queueConnection' => (string) config('queue.default'),
            'storageDisk' => (string) config('filesystems.default'),
            'readiness' => $readiness,
            'evidence' => [
                'backup' => collect($evidence['backup'])->except('path')->all(),
                'runtime' => collect($evidence['runtime'])->except('path')->all(),
            ],
        ]);
    }
}
