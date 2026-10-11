<?php

namespace App\Modules\Fiscal\Services;

use App\Modules\Fiscal\Contracts\FiscalCredentialResolver;
use App\Modules\Fiscal\Models\FiscalDocument;
use LogicException;

class UnavailableFiscalCredentialResolver implements FiscalCredentialResolver
{
    public function isConfigured(): bool
    {
        return false;
    }

    public function canResolve(FiscalDocument $document): bool
    {
        return false;
    }

    public function tokenFor(FiscalDocument $document): string
    {
        throw new LogicException('O resolvedor externo de credenciais fiscais ainda não foi configurado.');
    }
}
