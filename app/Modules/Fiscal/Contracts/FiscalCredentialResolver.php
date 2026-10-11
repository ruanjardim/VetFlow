<?php

namespace App\Modules\Fiscal\Contracts;

use App\Modules\Fiscal\Models\FiscalDocument;

interface FiscalCredentialResolver
{
    public function isConfigured(): bool;

    public function canResolve(FiscalDocument $document): bool;

    public function tokenFor(FiscalDocument $document): string;
}
