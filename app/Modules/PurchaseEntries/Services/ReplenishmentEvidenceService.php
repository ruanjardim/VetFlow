<?php

namespace App\Modules\PurchaseEntries\Services;

use Carbon\CarbonInterface;
use RuntimeException;

class ReplenishmentEvidenceService
{
    public const VERSION = 1;

    /** @return array<string, mixed> */
    public function snapshot(array $suggestion): array
    {
        return [
            'version' => self::VERSION,
            'clinic_id' => (int) $suggestion['product']->clinic_id,
            'product_id' => (int) $suggestion['product']->id,
            'stock_quantity' => (float) $suggestion['stock_quantity'],
            'minimum_stock' => (float) $suggestion['minimum_stock'],
            'suggested_quantity' => (float) $suggestion['suggested_quantity'],
            'unit_cost' => (float) $suggestion['unit_cost'],
            'supplier_id' => $suggestion['last_supplier_id'] === null ? null : (int) $suggestion['last_supplier_id'],
            'demand_window_days' => (int) $suggestion['demand_window_days'],
            'net_demand_quantity' => (float) $suggestion['net_demand_quantity'],
            'average_monthly_demand' => (float) $suggestion['average_monthly_demand'],
            'lead_time_days' => $suggestion['coverage_lead_time_days'],
            'coverage_days' => $suggestion['coverage_days'],
            'coverage_margin_days' => $suggestion['coverage_margin_days'],
            'coverage_risk' => $suggestion['coverage_risk'],
        ];
    }

    public function hash(array $snapshot): string
    {
        return hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
    }

    /** @return array<string, mixed> */
    public function envelope(array $suggestion, ?CarbonInterface $issuedAt = null): array
    {
        $snapshot = $this->snapshot($suggestion);
        $hash = $this->hash($snapshot);
        $issuedAt = ($issuedAt ?? now())->toISOString();

        return [
            'version' => self::VERSION,
            'algorithm' => 'hmac-sha256',
            'issued_at' => $issuedAt,
            'hash' => $hash,
            'snapshot' => $snapshot,
            'signature' => $this->signature($hash, $snapshot, $issuedAt),
        ];
    }

    public function validEnvelope(mixed $evidence): bool
    {
        if (! is_array($evidence)
            || ($evidence['version'] ?? null) !== self::VERSION
            || ($evidence['algorithm'] ?? null) !== 'hmac-sha256'
            || ! is_string($evidence['issued_at'] ?? null)
            || ! is_string($evidence['hash'] ?? null)
            || ! is_string($evidence['signature'] ?? null)
            || ! is_array($evidence['snapshot'] ?? null)) {
            return false;
        }

        $calculatedHash = $this->hash($evidence['snapshot']);

        if (! hash_equals($calculatedHash, $evidence['hash'])) {
            return false;
        }

        return hash_equals(
            $this->signature($calculatedHash, $evidence['snapshot'], $evidence['issued_at']),
            $evidence['signature'],
        );
    }

    private function signature(string $hash, array $snapshot, string $issuedAt): string
    {
        $payload = [
            'version' => self::VERSION,
            'clinic_id' => (int) ($snapshot['clinic_id'] ?? 0),
            'product_id' => (int) ($snapshot['product_id'] ?? 0),
            'issued_at' => $issuedAt,
            'hash' => $hash,
        ];

        return hash_hmac(
            'sha256',
            json_encode($payload, JSON_THROW_ON_ERROR),
            $this->signingKey(),
        );
    }

    private function signingKey(): string
    {
        $configuredKey = (string) config('app.key');

        if ($configuredKey === '') {
            throw new RuntimeException('APP_KEY is required to sign replenishment evidence.');
        }

        if (! str_starts_with($configuredKey, 'base64:')) {
            return $configuredKey;
        }

        $decoded = base64_decode(substr($configuredKey, 7), true);

        if ($decoded === false || $decoded === '') {
            throw new RuntimeException('APP_KEY is invalid for replenishment evidence signing.');
        }

        return $decoded;
    }
}
