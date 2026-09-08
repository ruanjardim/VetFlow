<?php

namespace App\Modules\Implementation\Services;

use App\Modules\Clinics\Models\Clinic;

class ImplementationPilotReportService
{
    public function __construct(
        private readonly ImplementationReadinessService $coverage,
        private readonly ImplementationDataQualityService $quality,
        private readonly ImplementationPilotChecklistService $checklist,
        private readonly ImplementationPilotReleaseService $release,
        private readonly ImplementationPilotReadinessService $readiness
    ) {}

    /** @return array<string, mixed> */
    public function forClinic(Clinic $clinic): array
    {
        $clinics = collect([$clinic]);
        $coverage = $this->coverage->forClinics($clinics);
        $quality = $this->quality->forClinics($clinics, $coverage);
        $checklist = $this->checklist->forClinics($clinics);
        $release = $this->release->forClinics($clinics);
        $readiness = $this->readiness->forClinics(
            $clinics,
            $coverage,
            $quality,
            $checklist,
            $release
        );

        return [
            'generated_at' => now()->toIso8601String(),
            'clinic' => [
                'id' => $clinic->id,
                'name' => $clinic->trade_name,
                'corporate_name' => $clinic->corporate_name,
            ],
            'coverage' => $coverage[0],
            'quality' => $quality[0],
            'checklist' => $checklist[0],
            'release' => $release[0],
            'readiness' => $readiness[0],
        ];
    }
}
