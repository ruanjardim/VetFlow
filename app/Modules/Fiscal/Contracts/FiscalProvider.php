<?php

namespace App\Modules\Fiscal\Contracts;

use App\Modules\Fiscal\Models\FiscalDocument;
use App\Modules\Fiscal\Models\FiscalNfceInutilization;

interface FiscalProvider
{
    /**
     * Submit an already prepared document to a homologated external provider.
     * Implementations must be idempotent by FiscalDocument::idempotency_key.
     *
     * @return array<string, mixed>
     */
    public function submit(FiscalDocument $document): array;

    /** @return array<string, mixed> */
    public function status(FiscalDocument $document): array;

    /** @return array<string, mixed> */
    public function cancel(FiscalDocument $document, string $reason): array;

    /** @return array<string, mixed> */
    public function inutilizeNfce(FiscalNfceInutilization $inutilization): array;

    public function resendWebhook(FiscalDocument $document): void;

    /** @return array{content: string, content_type: string, extension: string} */
    public function downloadArtifact(FiscalDocument $document, string $artifact): array;
}
