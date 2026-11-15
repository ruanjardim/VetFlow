<?php

namespace App\Modules\Fiscal\Services;

use App\Modules\Fiscal\Models\FiscalProfile;
use App\Modules\Fiscal\Support\FiscalMunicipalityCatalog;
use App\Modules\Sales\Models\Sale;
use Illuminate\Validation\ValidationException;

class NfseMunicipalityRuleService
{
    public function validatePreparation(Sale $sale, FiscalProfile $profile): void
    {
        if ($profile->municipality_code !== FiscalMunicipalityCatalog::NITEROI_IBGE_CODE) {
            return;
        }

        $serviceItems = $sale->items->where('type', 'service');
        $serviceListItems = $serviceItems
            ->map(fn ($item) => $item->petShopService?->fiscalProfile?->service_list_item)
            ->filter()
            ->unique();
        $issRates = $serviceItems
            ->map(fn ($item) => $item->petShopService?->fiscalProfile?->iss_rate)
            ->filter(fn ($rate): bool => $rate !== null)
            ->map(fn ($rate): string => number_format((float) $rate, 4, '.', ''))
            ->unique();
        $singularFields = [
            'national_taxation_code', 'municipal_taxation_code', 'cnae',
            'municipality_code', 'iss_withheld', 'iss_taxation',
            'ibs_cbs_tax_status', 'ibs_cbs_classification_code', 'operation_indicator',
        ];
        $differentFields = collect($singularFields)->filter(function (string $field) use ($serviceItems): bool {
            return $serviceItems
                ->map(fn ($item) => data_get($item->petShopService?->fiscalProfile, $field))
                ->map(fn ($value) => $value === null ? null : (string) $value)
                ->unique(strict: true)
                ->count() > 1;
        });

        if ($serviceListItems->count() > 1 || $issRates->count() > 1 || $differentFields->isNotEmpty()) {
            throw ValidationException::withMessages([
                'fiscal_items' => 'Em Niterói, uma mesma NFS-e só pode reunir serviços com a mesma classificação e tributação de ISS/IBS/CBS. Separe a venda em preparações compatíveis.',
            ]);
        }
    }
}
