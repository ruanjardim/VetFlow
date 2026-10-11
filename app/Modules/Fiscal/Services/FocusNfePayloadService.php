<?php

namespace App\Modules\Fiscal\Services;

use App\Models\User;
use App\Modules\Fiscal\Models\FiscalDocument;
use App\Modules\Fiscal\Models\FiscalDocumentEvent;
use App\Modules\Fiscal\Support\FiscalDocumentType;
use App\Modules\Fiscal\Support\FiscalMunicipalityCatalog;
use App\Modules\Fiscal\Support\FiscalProviderCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FocusNfePayloadService
{
    public const SCHEMA_VERSION = 'focus-nfsen-2026-10';

    public function __construct(private readonly FiscalDpsSequenceService $dpsSequences) {}

    public function generate(FiscalDocument $document, User $actor): FiscalDocument
    {
        return DB::transaction(function () use ($document, $actor): FiscalDocument {
            $document = FiscalDocument::query()->lockForUpdate()->findOrFail($document->id);

            if (is_array(data_get($document->request_payload, 'focus_nfe.payload'))) {
                return $document;
            }

            $this->assertSupported($document);
            $this->assertReady($document);

            $series = (string) data_get($document->issuer_snapshot, 'focus_nfe.dps_series');
            $reservation = $this->dpsSequences->reserve($document, $series);
            $generatedAt = now()->utc();
            $payload = $this->payload($document->fresh(), $reservation, $generatedAt->toIso8601String());

            $document->update([
                'request_payload' => [
                    'focus_nfe' => [
                        'schema_version' => self::SCHEMA_VERSION,
                        'generated_at' => $generatedAt->toIso8601String(),
                        'payload' => $payload,
                    ],
                ],
            ]);

            FiscalDocumentEvent::query()->create([
                'clinic_id' => $document->clinic_id,
                'fiscal_document_id' => $document->id,
                'actor_user_id' => $actor->id,
                'event' => 'payload_generated',
                'from_status' => $document->status,
                'to_status' => $document->status,
                'metadata' => [
                    'provider' => FiscalProviderCatalog::FOCUS_NFE,
                    'schema_version' => self::SCHEMA_VERSION,
                    'series' => $reservation['series'],
                    'number' => $reservation['number'],
                    'external_submission' => false,
                ],
                'occurred_at' => now(),
            ]);

            return $document->fresh(['events']);
        });
    }

    private function assertSupported(FiscalDocument $document): void
    {
        if ($document->environment !== 'homologation'
            || $document->provider !== FiscalProviderCatalog::FOCUS_NFE
            || $document->type !== FiscalDocumentType::NFSE
            || data_get($document->issuer_snapshot, 'municipality_code') !== FiscalMunicipalityCatalog::NITEROI_IBGE_CODE) {
            throw ValidationException::withMessages([
                'payload' => 'A geração atual aceita somente NFS-e Nacional da Focus para o piloto de Niterói em homologação.',
            ]);
        }

        if ($document->status !== 'draft') {
            throw ValidationException::withMessages([
                'payload' => 'Somente uma preparação fiscal ainda em rascunho pode gerar o payload.',
            ]);
        }
    }

    private function assertReady(FiscalDocument $document): void
    {
        $issuer = $document->issuer_snapshot ?? [];
        $recipient = $document->recipient_snapshot ?? [];
        $items = $document->items_snapshot ?? [];
        $totals = $document->totals_snapshot ?? [];
        $firstFiscal = data_get($items, '0.fiscal', []);
        $missing = [];

        $this->requireDigits($missing, 'CNPJ do prestador', data_get($issuer, 'document'), 14);
        $this->requireValue($missing, 'inscrição municipal do prestador', data_get($issuer, 'municipal_registration'));
        $this->requireDigits($missing, 'município do prestador', data_get($issuer, 'municipality_code'), 7);
        $this->requireIntegerRange($missing, 'série da DPS', data_get($issuer, 'focus_nfe.dps_series'), 1, 49999);
        $this->requireAllowed($missing, 'situação perante o Simples Nacional', data_get($issuer, 'focus_nfe.simple_national_option'), ['1', '2', '3']);
        $this->requireAllowed($missing, 'regime especial municipal', data_get($issuer, 'focus_nfe.special_tax_regime'), ['0', '1', '2', '3', '4', '5', '6', '9']);

        if ((string) data_get($issuer, 'focus_nfe.simple_national_option') === '3') {
            $this->requireAllowed($missing, 'apuração do Simples Nacional', data_get($issuer, 'focus_nfe.simple_national_tax_regime'), ['1', '2', '3']);
        }

        $this->requireValue($missing, 'nome do tomador', data_get($recipient, 'name'));
        $recipientDocument = $this->digits(data_get($recipient, 'document'));
        if (! in_array(strlen($recipientDocument), [11, 14], true)) {
            $missing[] = 'CPF/CNPJ do tomador';
        }

        if (data_get($recipient, 'consumer_final') === null) {
            $missing[] = 'indicador de consumidor final';
        }

        if ($items === []) {
            $missing[] = 'serviço da DPS';
        }

        $this->requireDigits($missing, 'código de tributação nacional do serviço', data_get($firstFiscal, 'national_taxation_code'), 6);
        $this->requireDigits($missing, 'município de incidência do serviço', data_get($firstFiscal, 'municipality_code'), 7);
        $this->requireAllowed($missing, 'tributação do ISS', data_get($firstFiscal, 'iss_taxation'), ['1', '2', '3', '4']);
        $this->requireDigits($missing, 'CST do IBS/CBS', data_get($firstFiscal, 'ibs_cbs_tax_status'), 3);
        $this->requireDigits($missing, 'classificação tributária do IBS/CBS', data_get($firstFiscal, 'ibs_cbs_classification_code'), 6);
        $this->requireValue($missing, 'data de competência', data_get($totals, 'competence_date'));

        if (data_get($totals, 'requires_allocation') !== 'no') {
            $missing[] = 'rateio concluído para venda mista ou com ajuste global';
        }

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'payload' => 'Complete antes de gerar o payload: '.implode(', ', array_unique($missing)).'.',
            ]);
        }

        $description = $this->description($items, $totals);
        if (mb_strlen($description) > 1000) {
            throw ValidationException::withMessages([
                'payload' => 'A descrição consolidada dos serviços ultrapassa 1.000 caracteres.',
            ]);
        }

        $gross = $this->grossTotal($items)
            + round((float) data_get($totals, 'additions_total'), 2)
            + round((float) data_get($totals, 'delivery_fee'), 2);
        $discount = $this->discountTotal($items);
        $net = round((float) data_get($totals, 'total'), 2);
        if ($gross <= 0 || abs(($gross - $discount) - $net) > 0.01) {
            throw ValidationException::withMessages([
                'payload' => 'Os valores dos serviços, descontos e total da preparação não fecham para a DPS.',
            ]);
        }
    }

    /** @param array{series: string, number: int} $reservation @return array<string, mixed> */
    private function payload(FiscalDocument $document, array $reservation, string $generatedAt): array
    {
        $issuer = $document->issuer_snapshot;
        $recipient = $document->recipient_snapshot;
        $items = $document->items_snapshot;
        $totals = $document->totals_snapshot;
        $fiscal = data_get($items, '0.fiscal');
        $recipientDocument = $this->digits(data_get($recipient, 'document'));
        $gross = $this->grossTotal($items)
            + round((float) data_get($totals, 'additions_total'), 2)
            + round((float) data_get($totals, 'delivery_fee'), 2);
        $discount = $this->discountTotal($items);

        $payload = [
            'data_emissao' => $generatedAt,
            'serie_dps' => (int) $reservation['series'],
            'numero_dps' => $reservation['number'],
            'data_competencia' => data_get($totals, 'competence_date'),
            'emitente_dps' => '1',
            'codigo_municipio_emissora' => (int) data_get($issuer, 'municipality_code'),
            'cnpj_prestador' => $this->digits(data_get($issuer, 'document')),
            'inscricao_municipal_prestador' => data_get($issuer, 'municipal_registration'),
            'codigo_opcao_simples_nacional' => (string) data_get($issuer, 'focus_nfe.simple_national_option'),
            'regime_tributario_simples_nacional' => data_get($issuer, 'focus_nfe.simple_national_tax_regime'),
            'regime_especial_tributacao' => (string) data_get($issuer, 'focus_nfe.special_tax_regime'),
            strlen($recipientDocument) === 14 ? 'cnpj_tomador' : 'cpf_tomador' => $recipientDocument,
            'razao_social_tomador' => data_get($recipient, 'name'),
            'cep_tomador' => $this->digits(data_get($recipient, 'address.zip_code')),
            'logradouro_tomador' => data_get($recipient, 'address.street'),
            'numero_tomador' => data_get($recipient, 'address.number'),
            'complemento_tomador' => data_get($recipient, 'address.complement'),
            'bairro_tomador' => data_get($recipient, 'address.district'),
            'telefone_tomador' => $this->digits(data_get($recipient, 'phone')),
            'email_tomador' => data_get($recipient, 'email'),
            'codigo_municipio_prestacao' => (string) data_get($fiscal, 'municipality_code'),
            'codigo_tributacao_nacional_iss' => (string) data_get($fiscal, 'national_taxation_code'),
            'codigo_tributacao_municipal_iss' => data_get($fiscal, 'municipal_taxation_code'),
            'descricao_servico' => $this->description($items, $totals),
            'valor_servico' => number_format($gross, 2, '.', ''),
            'desconto_incondicionado' => $discount > 0 ? number_format($discount, 2, '.', '') : null,
            'tributacao_iss' => (string) data_get($fiscal, 'iss_taxation'),
            'tipo_retencao_iss' => data_get($fiscal, 'iss_withheld') ? '2' : '1',
            'finalidade_emissao' => '0',
            'consumidor_final' => data_get($recipient, 'consumer_final') ? '1' : '0',
            'codigo_indicador_operacao' => data_get($fiscal, 'operation_indicator'),
            'indicador_destinatario' => '0',
            'ibs_cbs_situacao_tributaria' => (string) data_get($fiscal, 'ibs_cbs_tax_status'),
            'ibs_cbs_classificacao_tributaria' => (string) data_get($fiscal, 'ibs_cbs_classification_code'),
        ];

        return collect($payload)
            ->reject(fn ($value): bool => $value === null || $value === '')
            ->all();
    }

    /** @param array<int, array<string, mixed>> $items */
    private function description(array $items, array $totals = []): string
    {
        $description = collect($items)->map(function (array $item): string {
            $quantity = rtrim(rtrim(number_format((float) $item['quantity'], 3, '.', ''), '0'), '.');
            $unitPrice = number_format((float) $item['unit_price'], 2, '.', '');

            return trim((string) $item['description'])." ({$quantity} x R$ {$unitPrice})";
        })->implode('; ');

        $adjustments = [];
        if ((float) data_get($totals, 'additions_total') > 0) {
            $adjustments[] = 'acréscimos rateados R$ '.number_format((float) data_get($totals, 'additions_total'), 2, ',', '.');
        }
        if ((float) data_get($totals, 'delivery_fee') > 0) {
            $adjustments[] = 'entrega rateada R$ '.number_format((float) data_get($totals, 'delivery_fee'), 2, ',', '.');
        }

        return $adjustments === [] ? $description : $description.'; '.implode('; ', $adjustments);
    }

    /** @param array<int, array<string, mixed>> $items */
    private function grossTotal(array $items): float
    {
        return round(collect($items)->sum(
            fn (array $item): float => (float) $item['quantity'] * (float) $item['unit_price']
        ), 2);
    }

    /** @param array<int, array<string, mixed>> $items */
    private function discountTotal(array $items): float
    {
        return round(collect($items)->sum(fn (array $item): float => (float) $item['discount_total']), 2);
    }

    /** @param array<int, string> $missing */
    private function requireValue(array &$missing, string $label, mixed $value): void
    {
        if (! filled($value)) {
            $missing[] = $label;
        }
    }

    /** @param array<int, string> $missing */
    private function requireDigits(array &$missing, string $label, mixed $value, int $length): void
    {
        if (strlen($this->digits($value)) !== $length) {
            $missing[] = $label;
        }
    }

    /** @param array<int, string> $missing @param array<int, string> $allowed */
    private function requireAllowed(array &$missing, string $label, mixed $value, array $allowed): void
    {
        if (! in_array((string) $value, $allowed, true)) {
            $missing[] = $label;
        }
    }

    /** @param array<int, string> $missing */
    private function requireIntegerRange(array &$missing, string $label, mixed $value, int $minimum, int $maximum): void
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < $minimum || (int) $value > $maximum) {
            $missing[] = $label;
        }
    }

    private function digits(mixed $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?: '';
    }
}
