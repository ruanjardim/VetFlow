<?php

namespace App\Modules\Implementation\Services;

use App\Modules\Clinics\Models\Clinic;
use App\Modules\Implementation\Models\ImplementationImport;
use App\Modules\Implementation\Models\ImplementationPilotCheck;
use App\Modules\Implementation\Models\ImplementationPilotDecision;
use App\Modules\Implementation\Models\ImplementationPilotRelease;

class ImplementationPilotHistoryService
{
    /** @return array<string, mixed> */
    public function forClinic(Clinic $clinic): array
    {
        return [
            'imports' => ImplementationImport::query()
                ->where('clinic_id', $clinic->id)
                ->latest('completed_at')
                ->latest('id')
                ->paginate(10, ['*'], 'imports_page')
                ->withQueryString(),
            'checks' => ImplementationPilotCheck::query()
                ->where('clinic_id', $clinic->id)
                ->latest('decided_at')
                ->latest('id')
                ->paginate(10, ['*'], 'checks_page')
                ->withQueryString(),
            'releases' => ImplementationPilotRelease::query()
                ->where('clinic_id', $clinic->id)
                ->latest('revision')
                ->paginate(10, ['*'], 'releases_page')
                ->withQueryString(),
            'decisions' => ImplementationPilotDecision::query()
                ->where('clinic_id', $clinic->id)
                ->latest('decided_at')
                ->latest('id')
                ->paginate(10, ['*'], 'decisions_page')
                ->withQueryString(),
        ];
    }
}
