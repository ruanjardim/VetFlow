<?php

namespace App\Modules\Fiscal\Services;

use App\Modules\Fiscal\Contracts\FiscalProvider;
use App\Modules\Fiscal\Models\FiscalDocument;
use App\Modules\Fiscal\Models\FiscalNfceInutilization;
use LogicException;

class DisabledFiscalProvider implements FiscalProvider
{
    public function submit(FiscalDocument $document): array
    {
        throw $this->unavailable();
    }

    public function status(FiscalDocument $document): array
    {
        throw $this->unavailable();
    }

    public function cancel(FiscalDocument $document, string $reason): array
    {
        throw $this->unavailable();
    }

    public function inutilizeNfce(FiscalNfceInutilization $inutilization): array
    {
        throw $this->unavailable();
    }

    public function resendWebhook(FiscalDocument $document): void
    {
        throw $this->unavailable();
    }

    public function downloadArtifact(FiscalDocument $document, string $artifact): array
    {
        throw $this->unavailable();
    }

    private function unavailable(): LogicException
    {
        return new LogicException('Nenhum provedor fiscal homologado está conectado. Emissão externa permanece bloqueada.');
    }
}
