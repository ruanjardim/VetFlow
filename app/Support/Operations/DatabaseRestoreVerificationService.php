<?php

namespace App\Support\Operations;

use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class DatabaseRestoreVerificationService
{
    public const MANIFEST_VERSION = 1;

    /**
     * Durable operational tables used as restore control totals. Transient
     * cache, session and queue tables are intentionally excluded.
     *
     * @var array<int, string>
     */
    private const CONTROL_TABLES = [
        'clinics',
        'users',
        'roles',
        'permissions',
        'user_roles',
        'role_permission',
        'tutors',
        'patients',
        'appointments',
        'appointment_reminders',
        'schedules',
        'medical_records',
        'medical_record_exams',
        'medical_record_exam_results',
        'medical_record_pathology',
        'vaccinations',
        'hospitalizations',
        'hospitalization_evolutions',
        'patient_clinical_alerts',
        'prescriptions',
        'prescription_items',
        'suppliers',
        'products',
        'inventory_movements',
        'purchase_entries',
        'purchase_entry_items',
        'petshop_services',
        'service_orders',
        'service_order_items',
        'sales',
        'sale_items',
        'sale_payments',
        'sale_events',
        'cash_register_closures',
        'financial_transactions',
        'commission_rules',
        'implementation_imports',
        'implementation_pilot_checks',
        'implementation_pilot_releases',
        'implementation_pilot_decisions',
        'operations_smoke_checks',
        'operations_release_decisions',
        'operations_runtime_probe_events',
        'global_products',
        'global_product_sources',
        'global_product_images',
        'global_product_regulatory_data',
        'global_product_suggestions',
        'product_lookup_catalogs',
        'clinic_products',
        'animal_species',
        'animal_breeds',
        'animal_coats',
        'animal_pathologies',
        'animal_pathology_species',
        'animal_exams',
        'animal_exam_species',
        'animal_vaccines',
        'animal_vaccine_species',
        'user_animal_species',
        'audit_events',
    ];

    /** @return array<string, mixed> */
    public function capture(string $connection, string $identifier): array
    {
        $this->guardConnection($connection);

        return [
            'version' => self::MANIFEST_VERSION,
            'backup_identifier' => $identifier,
            'captured_at' => now()->utc()->toIso8601String(),
            'source' => [
                'driver' => DB::connection($connection)->getDriverName(),
                'fingerprint' => $this->connectionFingerprint($connection),
            ],
            'migrations' => $this->migrationControl($connection),
            'tables' => collect(self::CONTROL_TABLES)
                ->mapWithKeys(fn (string $table): array => [
                    $table => $this->tableControl($connection, $table),
                ])
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<string, mixed>
     */
    public function verify(array $manifest, string $restoreConnection): array
    {
        $this->validateManifest($manifest);
        $this->guardConnection($restoreConnection);

        if (hash_equals(
            (string) $manifest['source']['fingerprint'],
            $this->connectionFingerprint($restoreConnection)
        )) {
            throw new InvalidArgumentException(
                'A conexao restaurada deve apontar para um banco isolado, diferente da origem.'
            );
        }

        $checks = [];
        $checks[] = $this->check(
            'Driver do banco',
            $manifest['source']['driver'],
            DB::connection($restoreConnection)->getDriverName()
        );
        $checks[] = $this->check(
            'Historico de migrations',
            $manifest['migrations'],
            $this->migrationControl($restoreConnection)
        );

        foreach ($manifest['tables'] as $table => $expected) {
            $checks[] = $this->check(
                "Tabela {$table}",
                $expected,
                $this->tableControl($restoreConnection, (string) $table)
            );
        }

        return [
            'version' => self::MANIFEST_VERSION,
            'backup_identifier' => $manifest['backup_identifier'],
            'verified_at' => now()->utc()->toIso8601String(),
            'status' => collect($checks)->every(fn (array $check): bool => $check['passed'])
                ? 'passed'
                : 'failed',
            'manifest_sha256' => hash('sha256', $this->canonicalJson($manifest)),
            'restore' => [
                'driver' => DB::connection($restoreConnection)->getDriverName(),
                'fingerprint' => $this->connectionFingerprint($restoreConnection),
            ],
            'checks' => $checks,
        ];
    }

    /** @return array{count: int, digest: string, latest: string|null} */
    private function migrationControl(string $connection): array
    {
        $schema = Schema::connection($connection);

        if (! $schema->hasTable('migrations')) {
            return ['count' => 0, 'digest' => hash('sha256', ''), 'latest' => null];
        }

        $migrations = DB::connection($connection)
            ->table('migrations')
            ->orderBy('migration')
            ->pluck('migration')
            ->map(fn ($migration): string => (string) $migration)
            ->all();

        return [
            'count' => count($migrations),
            'digest' => hash('sha256', implode("\n", $migrations)),
            'latest' => $migrations === [] ? null : end($migrations),
        ];
    }

    /** @return array{exists: bool, count?: int, max_id?: string|null, latest_update?: string|null} */
    private function tableControl(string $connection, string $table): array
    {
        $schema = Schema::connection($connection);

        if (! $schema->hasTable($table)) {
            return ['exists' => false];
        }

        $query = DB::connection($connection)->table($table);
        $control = [
            'exists' => true,
            'count' => $query->count(),
        ];

        if ($schema->hasColumn($table, 'id')) {
            $control['max_id'] = $this->normalize($query->max('id'));
        }

        if ($schema->hasColumn($table, 'updated_at')) {
            $control['latest_update'] = $this->normalize($query->max('updated_at'));
        }

        return $control;
    }

    /** @return array{name: string, passed: bool, expected: mixed, actual: mixed} */
    private function check(string $name, mixed $expected, mixed $actual): array
    {
        return [
            'name' => $name,
            'passed' => $expected === $actual,
            'expected' => $expected,
            'actual' => $actual,
        ];
    }

    /** @param array<string, mixed> $manifest */
    private function validateManifest(array $manifest): void
    {
        $valid = ($manifest['version'] ?? null) === self::MANIFEST_VERSION
            && is_string($manifest['backup_identifier'] ?? null)
            && is_array($manifest['source'] ?? null)
            && is_string($manifest['source']['driver'] ?? null)
            && is_string($manifest['source']['fingerprint'] ?? null)
            && is_array($manifest['migrations'] ?? null)
            && is_array($manifest['tables'] ?? null);

        if (! $valid) {
            throw new InvalidArgumentException('Manifesto de backup invalido ou incompatível.');
        }
    }

    private function guardConnection(string $connection): void
    {
        if ($connection === '' || config("database.connections.{$connection}") === null) {
            throw new InvalidArgumentException("Conexao de banco {$connection} nao configurada.");
        }
    }

    private function connectionFingerprint(string $connection): string
    {
        $config = (array) config("database.connections.{$connection}", []);
        $identity = [
            'driver' => $config['driver'] ?? null,
            'url' => $config['url'] ?? null,
            'host' => $config['host'] ?? null,
            'port' => $config['port'] ?? null,
            'database' => $config['database'] ?? null,
        ];

        return hash('sha256', $this->canonicalJson($identity));
    }

    /** @param array<string, mixed> $data */
    private function canonicalJson(array $data): string
    {
        return (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function normalize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return (string) $value;
    }
}
