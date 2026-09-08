<?php

namespace Tests\Unit;

use App\Modules\Operations\Services\OperationsEvidenceFreshnessService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class OperationsEvidenceFreshnessServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_it_distinguishes_fresh_and_expiring_evidence(): void
    {
        CarbonImmutable::setTestNow('2026-08-24 12:00:00 UTC');
        config([
            'operations.backup.evidence_max_age_days' => 10,
            'operations.runtime_probe.evidence_max_age_minutes' => 100,
        ]);

        $summary = app(OperationsEvidenceFreshnessService::class)->summary([
            'backup' => $this->evidence(now()->subDay()->toIso8601String()),
            'runtime' => $this->evidence(now()->subMinutes(90)->toIso8601String()),
        ]);

        $this->assertSame('fresh', $summary['backup']['status']);
        $this->assertSame('Dentro do prazo', $summary['backup']['label']);
        $this->assertSame('expiring', $summary['runtime']['status']);
        $this->assertSame('Vence em breve', $summary['runtime']['label']);
    }

    public function test_it_distinguishes_expired_failed_missing_and_future_evidence(): void
    {
        CarbonImmutable::setTestNow('2026-08-24 12:00:00 UTC');
        config([
            'operations.backup.evidence_max_age_days' => 1,
            'operations.runtime_probe.evidence_max_age_minutes' => 60,
        ]);
        $service = app(OperationsEvidenceFreshnessService::class);

        $summary = $service->summary([
            'backup' => $this->evidence(now()->subHours(25)->toIso8601String()),
            'runtime' => $this->evidence(now()->subMinutes(10)->toIso8601String(), 'failed'),
        ]);
        $this->assertSame('expired', $summary['backup']['status']);
        $this->assertSame('failed', $summary['runtime']['status']);

        $summary = $service->summary([
            'backup' => ['available' => false],
            'runtime' => $this->evidence(now()->addMinutes(6)->toIso8601String()),
        ]);
        $this->assertSame('missing', $summary['backup']['status']);
        $this->assertSame('invalid', $summary['runtime']['status']);
    }

    /** @return array<string, mixed> */
    private function evidence(string $verifiedAt, string $status = 'passed'): array
    {
        return [
            'available' => true,
            'verified_at' => $verifiedAt,
            'status' => $status,
        ];
    }
}
