<?php

namespace App\Modules\MedicalRecords\Controllers;

use App\Modules\MedicalRecords\Requests\CancelExamResultRequest;
use App\Modules\MedicalRecords\Requests\SaveExamResultRequest;
use App\Modules\MedicalRecords\Services\ExamResultService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ExamResultController
{
    public function __construct(private readonly ExamResultService $service) {}

    public function edit(int $examRequest): View
    {
        return view('medical-records.exam-results.edit', [
            'examRequest' => $this->service->forRequest($examRequest),
        ]);
    }

    public function save(SaveExamResultRequest $request, int $examRequest): RedirectResponse
    {
        $this->service->saveDraft($examRequest, $request->validated());

        return redirect()
            ->route('exam-results.edit', $examRequest)
            ->with('success', 'Rascunho do resultado salvo.');
    }

    public function finalize(int $examRequest): RedirectResponse
    {
        $this->service->finalize($examRequest);

        return redirect()
            ->route('exam-results.edit', $examRequest)
            ->with('success', 'Resultado finalizado e protegido contra alterações.');
    }

    public function cancel(CancelExamResultRequest $request, int $examRequest): RedirectResponse
    {
        $this->service->cancel($examRequest, $request->validated('cancellation_reason'));

        return redirect()
            ->route('exam-results.edit', $examRequest)
            ->with('success', 'Resultado cancelado com o histórico preservado.');
    }
}
