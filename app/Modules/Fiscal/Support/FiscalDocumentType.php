<?php

namespace App\Modules\Fiscal\Support;

class FiscalDocumentType
{
    public const NFCE = 'nfce';

    public const NFSE = 'nfse';

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::NFCE => 'NFC-e',
            self::NFSE => 'NFS-e',
        ];
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_keys(self::labels());
    }
}
