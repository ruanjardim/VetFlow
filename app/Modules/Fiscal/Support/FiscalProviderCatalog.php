<?php

namespace App\Modules\Fiscal\Support;

final class FiscalProviderCatalog
{
    public const FOCUS_NFE = 'focus_nfe';

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            self::FOCUS_NFE => 'Focus NFe',
        ];
    }

    public static function label(?string $provider): string
    {
        return self::options()[$provider] ?? 'Nenhum provedor selecionado';
    }
}
