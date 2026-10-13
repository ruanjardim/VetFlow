<?php

namespace App\Modules\Fiscal\Services;

use App\Modules\Audit\Services\AuditTrailService;
use App\Modules\Fiscal\Models\FiscalDocument;
use App\Modules\Fiscal\Models\FiscalNfceInutilization;
use App\Modules\Fiscal\Models\FiscalNfceSequence;
use App\Modules\Fiscal\Models\FiscalProfile;
use App\Modules\Fiscal\Support\FiscalProviderCatalog;
use Illuminate\Validation\ValidationException;

class FiscalNfceSequenceService
{
    public const MAX_NUMBER = 999999999;

    public function __construct(private readonly AuditTrailService $audit) {}

    public function currentFor(FiscalProfile $profile): ?FiscalNfceSequence
    {
        $series = data_get($profile->metadata, 'focus_nfce.series');

        if ($profile->provider !== FiscalProviderCatalog::FOCUS_NFE || $series === null || $series === '') {
            return null;
        }

        return $this->query((int) $profile->clinic_id, (int) $series)->first();
    }

    /** @param array<string, mixed> $data */
    public function configure(FiscalProfile $profile, array $data): ?FiscalNfceSequence
    {
        $series = $data['nfce_series'] ?? null;
        $nextNumber = $data['nfce_next_number'] ?? null;

        if ($profile->provider !== FiscalProviderCatalog::FOCUS_NFE
            || $series === null || $series === ''
            || $nextNumber === null || $nextNumber === '') {
            return null;
        }

        $sequence = $this->query((int) $profile->clinic_id, (int) $series)
            ->lockForUpdate()
            ->first();
        $lastUnavailable = $this->lastUnavailableNumber((int) $profile->clinic_id, (int) $series);

        if ($lastUnavailable !== null && (int) $nextNumber <= $lastUnavailable) {
            throw ValidationException::withMessages([
                'nfce_next_number' => 'O próximo número deve ser maior que a última faixa de NFC-e já inutilizada ou em processamento.',
            ]);
        }

        if (! $sequence) {
            $sequence = FiscalNfceSequence::query()->create([
                'clinic_id' => $profile->clinic_id,
                'provider' => FiscalProviderCatalog::FOCUS_NFE,
                'environment' => 'homologation',
                'series' => (int) $series,
                'next_number' => (int) $nextNumber,
            ]);

            $this->recordConfiguration($sequence, []);

            return $sequence;
        }

        if ($sequence->last_reserved_number !== null && (int) $nextNumber <= $sequence->last_reserved_number) {
            throw ValidationException::withMessages([
                'nfce_next_number' => 'O próximo número deve ser maior que a última NFC-e já reservada nesta série.',
            ]);
        }

        $before = $this->snapshot($sequence);
        if ($sequence->next_number !== (int) $nextNumber) {
            $sequence->update(['next_number' => (int) $nextNumber]);
        }
        $this->recordConfiguration($sequence, $before);

        return $sequence;
    }

    /** @return array{series: int, number: int} */
    public function reserve(FiscalDocument $document, int $series): array
    {
        if (filled($document->series) && filled($document->document_number)) {
            return ['series' => (int) $document->series, 'number' => (int) $document->document_number];
        }

        $sequence = $this->query((int) $document->clinic_id, $series)
            ->lockForUpdate()
            ->first();

        if (! $sequence) {
            throw ValidationException::withMessages([
                'payload' => 'Configure a série e o próximo número da NFC-e antes de gerar o payload.',
            ]);
        }

        if ($sequence->next_number < 1 || $sequence->next_number > self::MAX_NUMBER) {
            throw ValidationException::withMessages([
                'payload' => 'A sequência da NFC-e atingiu o limite de 999.999.999 desta série.',
            ]);
        }

        $numberUnavailable = FiscalNfceInutilization::withoutGlobalScopes()
            ->where('clinic_id', $document->clinic_id)
            ->where('provider', FiscalProviderCatalog::FOCUS_NFE)
            ->where('environment', 'homologation')
            ->where('series', $series)
            ->whereIn('status', ['queued', 'processing', 'authorized'])
            ->where('number_start', '<=', $sequence->next_number)
            ->where('number_end', '>=', $sequence->next_number)
            ->exists();

        if ($numberUnavailable) {
            throw ValidationException::withMessages([
                'payload' => 'O próximo número da sequência pertence a uma faixa de NFC-e inutilizada ou em processamento.',
            ]);
        }

        $number = $sequence->next_number;
        $sequence->update([
            'last_reserved_number' => $number,
            'next_number' => $number + 1,
        ]);
        $document->update([
            'series' => (string) $series,
            'document_number' => (string) $number,
            'provider_reference' => $document->provider_reference ?: 'vf-'.$document->ulid,
        ]);

        return ['series' => $series, 'number' => $number];
    }

    private function query(int $clinicId, int $series)
    {
        return FiscalNfceSequence::query()
            ->where('clinic_id', $clinicId)
            ->where('provider', FiscalProviderCatalog::FOCUS_NFE)
            ->where('environment', 'homologation')
            ->where('series', $series);
    }

    private function lastUnavailableNumber(int $clinicId, int $series): ?int
    {
        $number = FiscalNfceInutilization::withoutGlobalScopes()
            ->where('clinic_id', $clinicId)
            ->where('provider', FiscalProviderCatalog::FOCUS_NFE)
            ->where('environment', 'homologation')
            ->where('series', $series)
            ->whereIn('status', ['queued', 'processing', 'authorized'])
            ->max('number_end');

        return $number === null ? null : (int) $number;
    }

    /** @return array<string, mixed> */
    private function snapshot(FiscalNfceSequence $sequence): array
    {
        return $sequence->only([
            'provider', 'environment', 'series', 'next_number', 'last_reserved_number',
        ]);
    }

    /** @param array<string, mixed> $before */
    private function recordConfiguration(FiscalNfceSequence $sequence, array $before): void
    {
        $this->audit->record(
            'fiscal.nfce-sequence.configured',
            $sequence,
            $before,
            $this->snapshot($sequence),
            clinicId: (int) $sequence->clinic_id,
            subjectLabel: 'Sequência NFC-e '.$sequence->series
        );
    }
}
