<?php

namespace App\Modules\Hospitalizations\Controllers;

use App\Modules\Hospitalizations\Requests\StoreHospitalizationEvolutionRequest;
use App\Modules\Hospitalizations\Services\HospitalizationEvolutionService;
use Illuminate\Http\RedirectResponse;

class HospitalizationEvolutionController
{
    public function __construct(private readonly HospitalizationEvolutionService $service) {}

    public function store(StoreHospitalizationEvolutionRequest $request, int $hospitalization): RedirectResponse
    {
        $this->service->create($hospitalization, $request->validated());

        return redirect()
            ->route('hospitalizations.edit', $hospitalization)
            ->with('success', 'Evolução registrada no histórico da internação.');
    }
}
