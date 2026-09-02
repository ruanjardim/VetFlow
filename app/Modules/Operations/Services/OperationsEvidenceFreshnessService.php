<?php

namespace App\Modules\Operations\Services;

use Carbon\CarbonImmutable;
use Throwable;

class OperationsEvidenceFreshnessService
{
    /**
     * @param  array<string, array<string, mixed>>  $evidence
     * @return array{backup: array<string, mixed>, runtime: array<string, mixed>}
     */
    public function summary(array $evidence): array
    {
        $backupDays = max(1, (int) config('operations.backup.evidence_max_age_days', 30));
        $runtimeMinutes = max(5, (int) config('operations.runtime_probe.evidence_max_age_minutes', 180));

        return [
            'backup' => $this->assess(
                $evidence['backup'] ?? [],
                $backupDays * 24 * 60,
                max(24 * 60, (int) floor($backupDays * 24 * 60 * 0.2)),
            ),
            'runtime' => $this->assess(
                $evidence['runtime'] ?? [],
                $runtimeMinutes,
                max(5, (int) floor($runtimeMinutes * 0.2)),
            ),
        ];
    }

    /** @return array{status: string, label: string, tone: string, detail: string, expires_at: ?CarbonImmutable} */
    private function assess(array $evidence, int $maxAgeMinutes, int $warningMinutes): array
    {
        if (! ($evidence['available'] ?? false) || blank($evidence['verified_at'] ?? null)) {
            return $this->result('missing', 'Não registrada', 'warning', 'Nenhuma evidência com data de verificação foi localizada.');
        }

        try {
            $verifiedAt = CarbonImmutable::parse((string) $evidence['verified_at']);
            $now = CarbonImmutable::now();
            $expiresAt = $verifiedAt->addMinutes($maxAgeMinutes);
        } catch (Throwable) {
            return $this->result('invalid', 'Data inválida', 'danger', 'A data de verificação não pôde ser validada.');
        }

        if ($verifiedAt->isAfter($now->addMinutes(5))) {
            return $this->result('invalid', 'Data inválida', 'danger', 'A verificação está no futuro e precisa ser refeita.');
        }

        if (($evidence['status'] ?? null) !== 'passed') {
            return $this->result('failed', 'Evidência reprovada', 'danger', 'O arquivo foi localizado, mas não registra uma aprovação.', $expiresAt);
        }

        $remainingMinutes = (int) floor($now->diffInMinutes($expiresAt, false));

        if ($remainingMinutes < 0) {
            return $this->result(
                'expired',
                'Prazo expirado',
                'danger',
                'O prazo terminou em '.$expiresAt->format('d/m/Y H:i').'. Gere uma nova evidência.',
                $expiresAt,
            );
        }

        $remaining = $this->remainingLabel($remainingMinutes);

        if ($remainingMinutes <= $warningMinutes) {
            return $this->result(
                'expiring',
                'Vence em breve',
                'warning',
                'Prazo até '.$expiresAt->format('d/m/Y H:i').' ('.$remaining.').',
                $expiresAt,
            );
        }

        return $this->result(
            'fresh',
            'Dentro do prazo',
            'success',
            'Prazo até '.$expiresAt->format('d/m/Y H:i').' ('.$remaining.').',
            $expiresAt,
        );
    }

    /** @return array{status: string, label: string, tone: string, detail: string, expires_at: ?CarbonImmutable} */
    private function result(
        string $status,
        string $label,
        string $tone,
        string $detail,
        ?CarbonImmutable $expiresAt = null,
    ): array {
        return [
            'status' => $status,
            'label' => $label,
            'tone' => $tone,
            'detail' => $detail,
            'expires_at' => $expiresAt,
        ];
    }

    private function remainingLabel(int $minutes): string
    {
        if ($minutes >= 48 * 60) {
            return (int) floor($minutes / (24 * 60)).' dias restantes';
        }

        if ($minutes >= 120) {
            return (int) floor($minutes / 60).' horas restantes';
        }

        return max(0, $minutes).' minutos restantes';
    }
}
