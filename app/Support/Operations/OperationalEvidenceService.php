<?php

namespace App\Support\Operations;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use SplFileInfo;
use Throwable;

class OperationalEvidenceService
{
    /**
     * @return array{
     *   backup: array{path: string|null, available: bool, identifier: string|null, status: string|null, verified_at: string|null, checks: int},
     *   runtime: array{path: string|null, available: bool, identifier: string|null, status: string|null, verified_at: string|null, checks: int}
     * }
     */
    public function latest(): array
    {
        return [
            'backup' => $this->latestEvidence(
                (string) config('operations.backup.evidence_directory'),
                'backup_identifier'
            ),
            'runtime' => $this->latestEvidence(
                (string) config('operations.runtime_probe.evidence_directory'),
                'probe_id'
            ),
        ];
    }

    /**
     * @return array{path: string|null, available: bool, identifier: string|null, status: string|null, verified_at: string|null, checks: int}
     */
    private function latestEvidence(string $directory, string $identifierKey): array
    {
        if ($directory === '' || ! File::isDirectory($directory)) {
            return $this->missing();
        }

        $files = collect(File::files($directory))
            ->filter(fn (SplFileInfo $file): bool => str_ends_with($file->getFilename(), '-evidence.json'))
            ->sortByDesc(fn (SplFileInfo $file): int => $file->getMTime());

        foreach ($files as $file) {
            try {
                $evidence = json_decode(File::get($file->getPathname()), true, flags: JSON_THROW_ON_ERROR);
                $verifiedAt = CarbonImmutable::parse((string) ($evidence['verified_at'] ?? ''));

                return [
                    'path' => $file->getPathname(),
                    'available' => true,
                    'identifier' => $this->safeIdentifier($evidence[$identifierKey] ?? null),
                    'status' => is_string($evidence['status'] ?? null) ? $evidence['status'] : null,
                    'verified_at' => $verifiedAt->toIso8601String(),
                    'checks' => is_array($evidence['checks'] ?? null) ? count($evidence['checks']) : 0,
                ];
            } catch (Throwable) {
                continue;
            }
        }

        return $this->missing();
    }

    private function safeIdentifier(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,119}$/', $value) === 1 ? $value : null;
    }

    /** @return array{path: null, available: false, identifier: null, status: null, verified_at: null, checks: int} */
    private function missing(): array
    {
        return [
            'path' => null,
            'available' => false,
            'identifier' => null,
            'status' => null,
            'verified_at' => null,
            'checks' => 0,
        ];
    }
}
