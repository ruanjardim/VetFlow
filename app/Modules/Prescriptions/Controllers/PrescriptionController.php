<?php

namespace App\Modules\Prescriptions\Controllers;

use App\Modules\MedicalRecords\Models\MedicalRecord;
use App\Modules\Prescriptions\Models\Prescription;
use App\Modules\Prescriptions\Requests\CancelPrescriptionRequest;
use App\Modules\Prescriptions\Requests\StorePrescriptionRequest;
use App\Modules\Prescriptions\Requests\UpdatePrescriptionRequest;
use App\Modules\Prescriptions\Services\PrescriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PrescriptionController
{
    public function __construct(private readonly PrescriptionService $service) {}

    public function index(): View
    {
        return view('prescriptions.index', [
            'prescriptions' => $this->service->paginate(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('prescriptions.create', $this->formData(
            null,
            $request->integer('medical_record_id') ?: null
        ));
    }

    public function store(StorePrescriptionRequest $request): RedirectResponse
    {
        $prescription = $this->service->create($request->validated());

        return redirect()
            ->route('prescriptions.show', $prescription->id)
            ->with('success', 'Prescrição criada como rascunho.');
    }

    public function show(int $prescription): View
    {
        return view('prescriptions.show', [
            'prescription' => $this->service->findOrFail($prescription),
        ]);
    }

    public function edit(int $prescription): View|RedirectResponse
    {
        /** @var Prescription $prescriptionModel */
        $prescriptionModel = $this->service->findOrFail($prescription);

        if (! $prescriptionModel->isDraft()) {
            return redirect()
                ->route('prescriptions.show', $prescriptionModel->id)
                ->with('error', 'Somente prescrições em rascunho podem ser editadas.');
        }

        return view('prescriptions.edit', $this->formData($prescriptionModel));
    }

    public function update(UpdatePrescriptionRequest $request, int $prescription): RedirectResponse
    {
        $this->service->update($prescription, $request->validated());

        return redirect()
            ->route('prescriptions.show', $prescription)
            ->with('success', 'Rascunho da prescrição atualizado.');
    }

    public function finalize(int $prescription): RedirectResponse
    {
        $this->service->finalize($prescription);

        return redirect()
            ->route('prescriptions.show', $prescription)
            ->with('success', 'Prescrição finalizada e protegida contra alterações.');
    }

    public function cancel(CancelPrescriptionRequest $request, int $prescription): RedirectResponse
    {
        $this->service->cancel($prescription, $request->validated('cancellation_reason'));

        return redirect()
            ->route('prescriptions.show', $prescription)
            ->with('success', 'Prescrição cancelada com histórico preservado.');
    }

    /** @return array<string, mixed> */
    private function formData(?Prescription $prescription = null, ?int $preselectedMedicalRecordId = null): array
    {
        return [
            'prescription' => $prescription,
            'medicalRecords' => MedicalRecord::query()
                ->with(['patient', 'appointment'])
                ->latest('examined_at')
                ->get(),
            'preselectedMedicalRecordId' => $preselectedMedicalRecordId,
        ];
    }
}
