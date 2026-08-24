<?php

namespace App\Modules\Operations\Services;

use App\Models\User;
use App\Modules\Operations\Models\OperationsBackupEvidenceEvent;
use App\Support\Operations\DatabaseRestoreVerificationService;
use App\Support\Operations\ReleaseIdentityService;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

class OperationsBackupEvidenceService
{
    public function __construct(
        private readonly DatabaseRestoreVerificationService $verification,
        private readonly ReleaseIdentityService $releaseIdentity,
    ) {}

    /** @return array{available: bool, items: array<int, array<string, mixed>>} */
    public function summary(User $user): array
    {
        $sha = $this->releaseIdentity->sha();

        if ($sha === null) {
            return ['available' => false, 'items' => []];
        }

        return [
            'available' => true,
            'items' => $this->scope($user, $sha)
                ->with('actor:id,name')
                ->latest('occurred_at')
                ->latest('id')
                ->limit(10)
                ->get()
                ->map(fn (OperationsBackupEvidenceEvent $event): array => [
                    'identifier' => $event->backup_identifier,
                    'status' => $event->status,
                    'status_label' => $event->status === 'passed' ? 'Aprovada' : 'Reprovada',
                    'status_tone' => $event->status === 'passed' ? 'success' : 'danger',
                    'checks' => $event->checks_count,
                    'verified_at' => $event->verified_at,
                    'actor' => $event->actor?->name,
                    'occurred_at' => $event->occurred_at,
                ])->all(),
        ];
    }

    public function import(User $user, UploadedFile $file): OperationsBackupEvidenceEvent
    {
        $sha = $this->releaseIdentity->sha();

        if ($sha === null) {
            throw new DomainException('Identifique o commit publicado antes de importar a evidência.');
        }

        try {
            $decoded = json_decode(
                (string) file_get_contents($file->getPathname()),
                true,
                flags: JSON_THROW_ON_ERROR,
            );
            $evidence = $this->verification->normalizeEvidence($decoded);
        } catch (Throwable $exception) {
            throw new DomainException('O arquivo não contém uma evidência de restauração válida.', previous: $exception);
        }

        if (! $this->verification->evidenceIsFresh($evidence)) {
            $days = max(1, (int) config('operations.backup.evidence_max_age_days', 30));

            throw new DomainException("A evidência deve ter sido verificada nos últimos {$days} dias.");
        }

        $encoded = json_encode(
            $evidence,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        ).PHP_EOL;
        $evidenceSha = hash('sha256', $encoded);

        if ($this->scope($user, $sha)->where('evidence_sha256', $evidenceSha)->exists()) {
            throw new DomainException('Esta evidência já foi registrada para a clínica e release atuais.');
        }

        $directory = trim((string) config('operations.backup.evidence_directory'));

        if ($directory === '') {
            throw new DomainException('O diretório privado das evidências de backup não está configurado.');
        }

        $path = rtrim($directory, '\\/').DIRECTORY_SEPARATOR
            .$evidence['backup_identifier'].'-'.Str::ulid().'-evidence.json';
        File::ensureDirectoryExists($directory);

        if (File::put($path, $encoded) === false) {
            throw new DomainException('Não foi possível armazenar a evidência no diretório privado.');
        }

        try {
            return OperationsBackupEvidenceEvent::query()->create([
                'clinic_id' => $user->clinic_id,
                'actor_user_id' => $user->id,
                'environment' => app()->environment(),
                'release_sha' => $sha,
                'backup_identifier' => $evidence['backup_identifier'],
                'status' => $evidence['status'],
                'checks_count' => count($evidence['checks']),
                'evidence_sha256' => $evidenceSha,
                'verified_at' => $evidence['verified_at'],
                'occurred_at' => now(),
            ]);
        } catch (Throwable $exception) {
            File::delete($path);

            throw $exception;
        }
    }

    private function scope(User $user, string $sha): Builder
    {
        return OperationsBackupEvidenceEvent::query()
            ->where('environment', app()->environment())
            ->where('release_sha', $sha)
            ->where(function (Builder $query) use ($user): void {
                $user->clinic_id === null
                    ? $query->whereNull('clinic_id')
                    : $query->where('clinic_id', $user->clinic_id);
            });
    }
}
