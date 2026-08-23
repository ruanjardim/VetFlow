<?php

namespace App\Support\Operations;

use App\Jobs\RuntimeOperationsProbeJob;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class RuntimeOperationsProbeService
{
    public const EVIDENCE_VERSION = 1;

    /** @var array<int, string> */
    private const UNSUPPORTED_QUEUE_CONNECTIONS = ['sync', 'null', 'background', 'deferred'];

    /** @return array{probe_id: string, prepared_at: string, queue_connection: string, queue_mode: string, storage_disk: string, sentinel_sha256: string} */
    public function prepare(?string $requestedProbeId = null): array
    {
        $probeId = $this->validatedProbeId($requestedProbeId ?: (string) Str::ulid());
        $queueConnection = (string) config('queue.default');
        $queueMode = (string) config('operations.queue.mode', 'worker');
        $disk = $this->diskName();

        if ($queueConnection === '' || config("queue.connections.{$queueConnection}") === null) {
            throw new RuntimeException('A conexao padrao de fila nao esta configurada.');
        }

        if (in_array($queueConnection, self::UNSUPPORTED_QUEUE_CONNECTIONS, true)) {
            throw new RuntimeException("A conexao {$queueConnection} nao comprova processamento assincrono.");
        }

        if (! in_array($queueMode, ['worker', 'cron'], true)) {
            throw new RuntimeException('VETFLOW_QUEUE_MODE deve ser worker ou cron.');
        }

        $storage = Storage::disk($disk);
        $directory = $this->directory($probeId);

        if ($storage->exists($this->sentinelPath($probeId)) || $storage->exists($this->resultPath($probeId))) {
            throw new RuntimeException('Ja existem artefatos para este probe.');
        }

        $preparedAt = CarbonImmutable::now();
        $sentinel = [
            'version' => self::EVIDENCE_VERSION,
            'probe_id' => $probeId,
            'prepared_at' => $preparedAt->toIso8601String(),
            'environment' => app()->environment(),
            'queue_connection' => $queueConnection,
            'queue_mode' => $queueMode,
            'storage_disk' => $disk,
            'nonce' => bin2hex(random_bytes(32)),
        ];
        $encoded = $this->encode($sentinel);
        $sentinelSha256 = hash('sha256', $encoded);

        if (! $storage->put($this->sentinelPath($probeId), $encoded)) {
            throw new RuntimeException("Nao foi possivel gravar o marcador no disco {$disk}.");
        }

        try {
            RuntimeOperationsProbeJob::dispatch($probeId, $disk, $sentinelSha256)
                ->onConnection($queueConnection);
        } catch (Throwable $exception) {
            $storage->deleteDirectory($directory);

            throw $exception;
        }

        return [
            'probe_id' => $probeId,
            'prepared_at' => $preparedAt->toIso8601String(),
            'queue_connection' => $queueConnection,
            'queue_mode' => $queueMode,
            'storage_disk' => $disk,
            'sentinel_sha256' => $sentinelSha256,
        ];
    }

    public function process(string $probeId, string $disk, string $sentinelSha256): void
    {
        $probeId = $this->validatedProbeId($probeId);
        $storage = Storage::disk($disk);
        $sentinelPath = $this->sentinelPath($probeId);
        $resultPath = $this->resultPath($probeId);

        if (! $storage->exists($sentinelPath)) {
            throw new RuntimeException('O marcador persistente do probe nao foi encontrado.');
        }

        $encoded = $storage->get($sentinelPath);

        if (! hash_equals($sentinelSha256, hash('sha256', $encoded))) {
            throw new RuntimeException('A integridade do marcador persistente foi violada.');
        }

        $sentinel = $this->decode($encoded);
        $this->assertSentinelContext($sentinel, $probeId, $disk);

        if ($storage->exists($resultPath)) {
            $existing = $this->decode($storage->get($resultPath));

            if (($existing['status'] ?? null) === 'passed'
                && ($existing['probe_id'] ?? null) === $probeId
                && hash_equals($sentinelSha256, (string) ($existing['sentinel_sha256'] ?? ''))) {
                return;
            }

            throw new RuntimeException('Ja existe um resultado inconsistente para este probe.');
        }

        $result = [
            'version' => self::EVIDENCE_VERSION,
            'status' => 'passed',
            'probe_id' => $probeId,
            'prepared_at' => $sentinel['prepared_at'],
            'processed_at' => CarbonImmutable::now()->toIso8601String(),
            'environment' => app()->environment(),
            'queue_connection' => $sentinel['queue_connection'],
            'queue_mode' => $sentinel['queue_mode'],
            'storage_disk' => $disk,
            'sentinel_sha256' => $sentinelSha256,
        ];

        if (! $storage->put($resultPath, $this->encode($result))) {
            throw new RuntimeException('O job do probe nao conseguiu gravar seu resultado.');
        }
    }

    /** @return array<string, mixed> */
    public function verify(string $probeId): array
    {
        $probeId = $this->validatedProbeId($probeId);
        $disk = $this->diskName();
        $storage = Storage::disk($disk);
        $sentinelPath = $this->sentinelPath($probeId);
        $resultPath = $this->resultPath($probeId);

        if (! $storage->exists($sentinelPath)) {
            throw new RuntimeException('O marcador persistente do probe nao foi encontrado.');
        }

        if (! $storage->exists($resultPath)) {
            throw new RuntimeException('O probe ainda nao foi processado pela fila.');
        }

        $sentinelEncoded = $storage->get($sentinelPath);
        $sentinel = $this->decode($sentinelEncoded);
        $result = $this->decode($storage->get($resultPath));
        $sentinelSha256 = hash('sha256', $sentinelEncoded);
        $this->assertSentinelContext($sentinel, $probeId, $disk);

        $preparedAt = CarbonImmutable::parse((string) ($sentinel['prepared_at'] ?? ''));
        $processedAt = CarbonImmutable::parse((string) ($result['processed_at'] ?? ''));
        $verifiedAt = CarbonImmutable::now();
        $maxAgeMinutes = max(5, (int) config('operations.runtime_probe.evidence_max_age_minutes', 180));
        $fresh = $preparedAt->betweenIncluded($verifiedAt->subMinutes($maxAgeMinutes), $verifiedAt->addMinutes(5));
        $ordered = $processedAt->betweenIncluded($preparedAt, $verifiedAt->addMinutes(5));
        $queueConnection = (string) ($sentinel['queue_connection'] ?? '');
        $queueMode = (string) ($sentinel['queue_mode'] ?? '');
        $contextMatches = ($result['version'] ?? null) === self::EVIDENCE_VERSION
            && ($result['status'] ?? null) === 'passed'
            && ($result['probe_id'] ?? null) === $probeId
            && ($result['prepared_at'] ?? null) === $sentinel['prepared_at']
            && ($result['environment'] ?? null) === app()->environment()
            && ($sentinel['environment'] ?? null) === app()->environment()
            && ($result['storage_disk'] ?? null) === $disk
            && ($result['queue_connection'] ?? null) === $queueConnection
            && ($result['queue_mode'] ?? null) === $queueMode;
        $hashMatches = hash_equals($sentinelSha256, (string) ($result['sentinel_sha256'] ?? ''));
        $queueValid = ! in_array($queueConnection, self::UNSUPPORTED_QUEUE_CONNECTIONS, true)
            && in_array($queueMode, ['worker', 'cron'], true);

        $checks = [
            ['name' => 'storage-sentinel-integrity', 'passed' => $hashMatches],
            ['name' => 'queued-execution', 'passed' => $queueValid && ($result['status'] ?? null) === 'passed'],
            ['name' => 'runtime-context', 'passed' => $contextMatches],
            ['name' => 'evidence-freshness', 'passed' => $fresh && $ordered],
        ];
        $passed = collect($checks)->every(fn (array $check): bool => $check['passed']);

        if (! $passed) {
            throw new RuntimeException('O resultado do probe nao corresponde ao ambiente preparado.');
        }

        return [
            'version' => self::EVIDENCE_VERSION,
            'status' => 'passed',
            'probe_id' => $probeId,
            'prepared_at' => $preparedAt->toIso8601String(),
            'processed_at' => $processedAt->toIso8601String(),
            'verified_at' => $verifiedAt->toIso8601String(),
            'environment' => app()->environment(),
            'queue_connection' => $queueConnection,
            'queue_mode' => $queueMode,
            'storage_disk' => $disk,
            'sentinel_sha256' => $sentinelSha256,
            'checks' => $checks,
        ];
    }

    public function cleanup(string $probeId): void
    {
        $probeId = $this->validatedProbeId($probeId);
        Storage::disk($this->diskName())->deleteDirectory($this->directory($probeId));
    }

    /** @param array<string, mixed> $evidence
     * @return array{bool, string}
     */
    public function validateEvidence(array $evidence, string $expectedEnvironment): array
    {
        try {
            $probeId = $this->validatedProbeId((string) ($evidence['probe_id'] ?? ''));
            $preparedAt = CarbonImmutable::parse((string) ($evidence['prepared_at'] ?? ''));
            $processedAt = CarbonImmutable::parse((string) ($evidence['processed_at'] ?? ''));
            $verifiedAt = CarbonImmutable::parse((string) ($evidence['verified_at'] ?? ''));
            $maxAgeMinutes = max(5, (int) config('operations.runtime_probe.evidence_max_age_minutes', 180));
            $checks = collect($evidence['checks'] ?? []);
            $requiredChecks = [
                'storage-sentinel-integrity',
                'queued-execution',
                'runtime-context',
                'evidence-freshness',
            ];
            $checkNames = $checks->pluck('name')->all();
            $fresh = $verifiedAt->betweenIncluded(now()->subMinutes($maxAgeMinutes), now()->addMinutes(5));
            $ordered = $processedAt->betweenIncluded($preparedAt, $verifiedAt->addMinutes(5));
            $valid = ($evidence['version'] ?? null) === self::EVIDENCE_VERSION
                && ($evidence['status'] ?? null) === 'passed'
                && $probeId === ($evidence['probe_id'] ?? null)
                && ($evidence['environment'] ?? null) === $expectedEnvironment
                && is_string($evidence['storage_disk'] ?? null)
                && filled($evidence['storage_disk'])
                && ! in_array((string) ($evidence['queue_connection'] ?? ''), self::UNSUPPORTED_QUEUE_CONNECTIONS, true)
                && in_array((string) ($evidence['queue_mode'] ?? ''), ['worker', 'cron'], true)
                && preg_match('/^[a-f0-9]{64}$/', (string) ($evidence['sentinel_sha256'] ?? '')) === 1
                && $checks->count() === count($requiredChecks)
                && array_diff($requiredChecks, $checkNames) === []
                && $checks->every(fn ($check): bool => is_array($check) && ($check['passed'] ?? false) === true)
                && $fresh
                && $ordered;

            return $valid
                ? [true, "Probe {$probeId} comprovou storage e processamento assincrono."]
                : [false, 'Evidencia operacional invalida, divergente ou expirada.'];
        } catch (Throwable) {
            return [false, 'Evidencia operacional invalida ou ilegivel.'];
        }
    }

    private function diskName(): string
    {
        $disk = trim((string) config('operations.runtime_probe.disk'));

        return $disk !== '' ? $disk : (string) config('filesystems.default');
    }

    private function validatedProbeId(string $probeId): string
    {
        $probeId = strtoupper(trim($probeId));

        if (! Str::isUlid($probeId)) {
            throw new InvalidArgumentException('O identificador do probe deve ser um ULID valido.');
        }

        return $probeId;
    }

    private function assertSentinelContext(array $sentinel, string $probeId, string $disk): void
    {
        if (($sentinel['version'] ?? null) !== self::EVIDENCE_VERSION
            || ($sentinel['probe_id'] ?? null) !== $probeId
            || ($sentinel['storage_disk'] ?? null) !== $disk
            || ! is_string($sentinel['nonce'] ?? null)
            || preg_match('/^[a-f0-9]{64}$/', (string) $sentinel['nonce']) !== 1) {
            throw new RuntimeException('O marcador do probe esta invalido ou pertence a outro contexto.');
        }
    }

    private function directory(string $probeId): string
    {
        return 'vetflow/runtime-probes/'.$probeId;
    }

    private function sentinelPath(string $probeId): string
    {
        return $this->directory($probeId).'/sentinel.json';
    }

    private function resultPath(string $probeId): string
    {
        return $this->directory($probeId).'/result.json';
    }

    /** @param array<string, mixed> $data */
    private function encode(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
    }

    /** @return array<string, mixed> */
    private function decode(string $encoded): array
    {
        return json_decode($encoded, true, flags: JSON_THROW_ON_ERROR);
    }
}
