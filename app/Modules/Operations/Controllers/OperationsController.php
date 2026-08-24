<?php

namespace App\Modules\Operations\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Operations\Requests\StoreOperationsSmokeCheckRequest;
use App\Modules\Operations\Services\OperationsSmokeChecklistService;
use App\Support\Operations\OperationalEvidenceService;
use App\Support\Operations\ReleaseIdentityService;
use App\Support\Operations\ReleaseReadinessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OperationsController extends Controller
{
    public function __construct(
        private readonly ReleaseIdentityService $releaseIdentity,
        private readonly ReleaseReadinessService $readiness,
        private readonly OperationalEvidenceService $evidence,
        private readonly OperationsSmokeChecklistService $smokeChecklist,
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
            'smokeChecklist' => $this->smokeChecklist->summary(request()->user()),
        ]);
    }

    public function storeSmokeCheck(
        StoreOperationsSmokeCheckRequest $request,
        string $checkKey,
    ): RedirectResponse {
        $data = $request->validated();
        $this->smokeChecklist->record(
            $request->user(),
            $checkKey,
            $data['action'] === 'complete',
            $data['note'] ?? null,
        );

        return back()->with('success', 'Decisão do smoke test registrada no histórico.');
    }
}
