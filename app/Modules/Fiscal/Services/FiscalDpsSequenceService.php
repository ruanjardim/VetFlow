<?php

namespace App\Modules\Fiscal\Services;

use App\Modules\Audit\Services\AuditTrailService;
use App\Modules\Fiscal\Models\FiscalDocument;
use App\Modules\Fiscal\Models\FiscalDpsSequence;
use App\Modules\Fiscal\Models\FiscalProfile;
use App\Modules\Fiscal\Support\FiscalDocumentType;
use App\Modules\Fiscal\Support\FiscalProviderCatalog;
use Illuminate\Validation\ValidationException;

class FiscalDpsSequenceService
{
    public const MAX_DPS_NUMBER = 999999999999999;

    public function __construct(private readonly AuditTrailService $audit) {}

    public function currentFor(FiscalProfile $profile): ?FiscalDpsSequence
    {
        $series = data_get($profile->metadata, 'focus_nfe.dps_series');

        if ($profile->provider !== FiscalProviderCatalog::FOCUS_NFE || ! filled($series)) {
            return null;
        }

        return $this->query((int) $profile->clinic_id, (string) $series)->first();
    }

    /** @param array<string, mixed> $data */
    public function configure(FiscalProfile $profile, array $data): ?FiscalDpsSequence
    {
        $series = $data['nfse_dps_series'] ?? null;
        $nextNumber = $data['nfse_dps_next_number'] ?? null;

        if ($profile->provider !== FiscalProviderCatalog::FOCUS_NFE
            || $series === null || $series === ''
            || $nextNumber === null || $nextNumber === '') {
            return null;
        }

        $sequence = $this->query((int) $profile->clinic_id, (string) $series)
            ->lockForUpdate()
            ->first();

        if (! $sequence) {
            $sequence = FiscalDpsSequence::query()->create([
                'clinic_id' => $profile->clinic_id,
                'provider' => FiscalProviderCatalog::FOCUS_NFE,
                'document_type' => FiscalDocumentType::NFSE,
                'environment' => 'homologation',
                'series' => (string) $series,
                'next_number' => (int) $nextNumber,
            ]);

            $this->recordConfiguration($sequence, []);

            return $sequence;
        }

        if ($sequence->last_reserved_number !== null && (int) $nextNumber <= $sequence->last_reserved_number) {
            throw ValidationException::withMessages([
                'nfse_dps_next_number' => 'O próximo número deve ser maior que o último DPS já reservado nesta série.',
            ]);
        }

        $before = $this->snapshot($sequence);
        if ($sequence->next_number !== (int) $nextNumber) {
            $sequence->update(['next_number' => (int) $nextNumber]);
        }
        $this->recordConfiguration($sequence, $before);

        return $sequence;
    }

    /** @return array{series: string, number: int} */
    public function reserve(FiscalDocument $document, string $series): array
    {
        if (filled($document->series) && filled($document->document_number)) {
            return ['series' => (string) $document->series, 'number' => (int) $document->document_number];
        }

        $sequence = $this->query((int) $document->clinic_id, $series)
            ->lockForUpdate()
            ->first();

        if (! $sequence) {
            throw ValidationException::withMessages([
                'payload' => 'Configure a série e o próximo número da DPS antes de gerar o payload.',
            ]);
        }

        if ($sequence->next_number < 1 || $sequence->next_number > self::MAX_DPS_NUMBER) {
            throw ValidationException::withMessages([
                'payload' => 'A sequência de DPS atingiu um número inválido para o leiaute nacional.',
            ]);
        }

        $number = $sequence->next_number;
        $sequence->update([
            'last_reserved_number' => $number,
            'next_number' => $number + 1,
        ]);
        $document->update([
            'series' => $series,
            'document_number' => (string) $number,
            'provider_reference' => $document->provider_reference ?: 'vf-'.$document->ulid,
        ]);

        return ['series' => $series, 'number' => $number];
    }

    private function query(int $clinicId, string $series)
    {
        return FiscalDpsSequence::query()
            ->where('clinic_id', $clinicId)
            ->where('provider', FiscalProviderCatalog::FOCUS_NFE)
            ->where('document_type', FiscalDocumentType::NFSE)
            ->where('environment', 'homologation')
            ->where('series', $series);
    }

    /** @return array<string, mixed> */
    private function snapshot(FiscalDpsSequence $sequence): array
    {
        return $sequence->only([
            'provider', 'document_type', 'environment', 'series',
            'next_number', 'last_reserved_number',
        ]);
    }

    /** @param array<string, mixed> $before */
    private function recordConfiguration(FiscalDpsSequence $sequence, array $before): void
    {
        $this->audit->record(
            'fiscal.dps-sequence.configured',
            $sequence,
            $before,
            $this->snapshot($sequence),
            clinicId: (int) $sequence->clinic_id,
            subjectLabel: 'Sequência DPS '.$sequence->series
        );
    }
}
