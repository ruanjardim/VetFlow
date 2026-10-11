<?php

namespace App\Modules\Fiscal\Support;

use App\Modules\Clinics\Models\Clinic;
use Illuminate\Support\Str;

final class FiscalMunicipalityCatalog
{
    public const NITEROI_IBGE_CODE = '3303302';

    /** @return array<string, mixed>|null */
    public static function find(?string $ibgeCode): ?array
    {
        return match ($ibgeCode) {
            self::NITEROI_IBGE_CODE => [
                'ibge_code' => self::NITEROI_IBGE_CODE,
                'name' => 'Niterói',
                'state' => 'RJ',
                'label' => 'Niterói/RJ',
                'nfse_strategy' => 'national_dps',
                'nfse_strategy_label' => 'Emissor Nacional NFS-e via DPS',
                'nfse_layout_version' => '1.01',
                'nfse_effective_from' => '2026-01-01',
                'homologation_environment' => 'Produção restrita da NFS-e Nacional',
                'homologation_endpoint' => 'https://sefin.producaorestrita.nfse.gov.br/API/SefinNacional',
                'municipal_parameters_endpoint' => 'https://adn.producaorestrita.nfse.gov.br/parametrizacao',
                'source_url' => 'https://www.fazenda.niteroi.rj.gov.br/site/nfse-nacional/',
            ],
            default => null,
        };
    }

    /** @return array<int, array<string, string>> */
    public static function suggestions(): array
    {
        $niteroi = self::find(self::NITEROI_IBGE_CODE);

        return [[
            'ibge_code' => $niteroi['ibge_code'],
            'label' => $niteroi['label'],
        ]];
    }

    public static function inferFromClinic(Clinic $clinic): ?string
    {
        $city = Str::lower(Str::ascii(trim((string) $clinic->city)));
        $state = Str::upper(trim((string) $clinic->state));

        if ($city === 'niteroi' && $state === 'RJ') {
            return self::NITEROI_IBGE_CODE;
        }

        return null;
    }
}
