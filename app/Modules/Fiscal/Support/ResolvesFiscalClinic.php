<?php

namespace App\Modules\Fiscal\Support;

use App\Modules\Clinics\Models\Clinic;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;

trait ResolvesFiscalClinic
{
    private function selectedClinicId(Request $request, TenantContext $tenant): int
    {
        if (! $tenant->isGlobal()) {
            return (int) $tenant->clinicId();
        }

        $requestedId = (int) $request->input('clinic_id');
        $clinicId = Clinic::query()
            ->where('active', true)
            ->when($requestedId > 0, fn ($query) => $query->whereKey($requestedId))
            ->orderBy('trade_name')
            ->value('id');

        abort_if(! $clinicId, 404, 'Nenhum estabelecimento ativo disponível.');

        return (int) $clinicId;
    }

    private function availableClinics(TenantContext $tenant)
    {
        return $tenant->isGlobal()
            ? Clinic::query()->where('active', true)->orderBy('trade_name')->orderBy('corporate_name')->get()
            : collect();
    }

    /** @return array<string, int> */
    private function fiscalRouteClinic(int $clinicId, TenantContext $tenant): array
    {
        return $tenant->isGlobal() ? ['clinic_id' => $clinicId] : [];
    }
}
