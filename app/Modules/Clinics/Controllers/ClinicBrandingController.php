<?php

namespace App\Modules\Clinics\Controllers;

use App\Modules\Clinics\Models\Clinic;
use App\Modules\Clinics\Requests\UpdateClinicBrandingRequest;
use App\Modules\Clinics\Services\ClinicBrandingService;
use App\Modules\Clinics\Services\ClinicService;
use App\Support\Tenancy\TenantContext;

class ClinicBrandingController
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly ClinicService $clinics
    ) {}

    public function edit()
    {
        return view('clinics.branding', [
            'clinic' => $this->currentClinic(),
            'brandModes' => ClinicBrandingService::modes(),
            'brandIcons' => ClinicBrandingService::icons(),
        ]);
    }

    public function update(UpdateClinicBrandingRequest $request)
    {
        $this->clinics->update($this->currentClinic()->id, $request->validated());

        return redirect()
            ->route('clinic-branding.edit')
            ->with('success', 'Identidade visual da clínica atualizada.');
    }

    private function currentClinic(): Clinic
    {
        abort_if($this->tenant->isGlobal(), 403);

        return Clinic::query()->findOrFail($this->tenant->clinicId());
    }
}
