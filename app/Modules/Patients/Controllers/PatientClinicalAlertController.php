<?php

namespace App\Modules\Patients\Controllers;

use App\Modules\Patients\Requests\ResolvePatientClinicalAlertRequest;
use App\Modules\Patients\Requests\StorePatientClinicalAlertRequest;
use App\Modules\Patients\Services\PatientClinicalAlertService;
use Illuminate\Http\RedirectResponse;

class PatientClinicalAlertController
{
    public function __construct(private readonly PatientClinicalAlertService $service) {}

    public function store(StorePatientClinicalAlertRequest $request, int $patient): RedirectResponse
    {
        $this->service->create($patient, $request->validated());

        return redirect()
            ->route('patients.show', $patient)
            ->with('success', 'Alerta clínico registrado e disponibilizado nos fluxos assistenciais.');
    }

    public function resolve(
        ResolvePatientClinicalAlertRequest $request,
        int $patient,
        int $alert
    ): RedirectResponse {
        $this->service->resolve($patient, $alert, $request->validated('resolution_notes'));

        return redirect()
            ->route('patients.show', $patient)
            ->with('success', 'Alerta clínico resolvido com o histórico preservado.');
    }
}
