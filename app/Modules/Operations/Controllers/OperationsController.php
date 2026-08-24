<?php

namespace App\Modules\Operations\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Operations\ReleaseIdentityService;
use App\Support\Operations\ReleaseReadinessService;
use Illuminate\View\View;

class OperationsController extends Controller
{
    public function __construct(
        private readonly ReleaseIdentityService $releaseIdentity,
        private readonly ReleaseReadinessService $readiness,
    ) {}

    public function index(): View
    {
        $release = $this->releaseIdentity->payload();

        return view('operations.index', [
            'release' => $release['release'],
            'releaseAvailable' => $release['status'] === 'ok',
            'environment' => app()->environment(),
            'queueMode' => (string) config('operations.queue.mode', 'worker'),
            'queueConnection' => (string) config('queue.default'),
            'storageDisk' => (string) config('filesystems.default'),
            'readiness' => $this->readiness->evaluate(),
        ]);
    }
}
