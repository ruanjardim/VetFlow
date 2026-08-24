<?php

namespace App\Modules\Operations\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Operations\Requests\StoreOperationsReleaseDecisionRequest;
use App\Modules\Operations\Requests\StoreOperationsSmokeCheckRequest;
use App\Modules\Operations\Services\OperationsReleaseDecisionService;
use App\Modules\Operations\Services\OperationsSmokeChecklistService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OperationsController extends Controller
{
    public function __construct(
        private readonly OperationsSmokeChecklistService $smokeChecklist,
        private readonly OperationsReleaseDecisionService $releaseDecision,
    ) {}

    public function index(): View
    {
        $state = $this->releaseDecision->state(request()->user());

        return view('operations.index', [
            'state' => $state,
            'release' => $state['release'],
            'releaseAvailable' => $state['release_available'],
            'environment' => $state['environment'],
            'queueMode' => $state['queue_mode'],
            'queueConnection' => $state['queue_connection'],
            'storageDisk' => $state['storage_disk'],
            'readiness' => $state['readiness'],
            'evidence' => $state['evidence'],
            'smokeChecklist' => $state['smoke_checklist'],
        ]);
    }

    public function storeDecision(StoreOperationsReleaseDecisionRequest $request): RedirectResponse
    {
        $data = $request->validated();

        try {
            $this->releaseDecision->record(
                $request->user(),
                $data['decision'],
                $data['note'] ?? null,
            );
        } catch (DomainException $exception) {
            return back()->withErrors(['decision' => $exception->getMessage()])->withInput();
        }

        return back()->with('success', 'Decisão operacional vinculada às evidências atuais.');
    }

    public function report(Request $request): View
    {
        return view('operations.report', [
            'report' => $this->releaseDecision->report($request->user()),
        ]);
    }

    public function reportJson(Request $request): JsonResponse
    {
        return response()
            ->json($this->releaseDecision->report($request->user()))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
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
