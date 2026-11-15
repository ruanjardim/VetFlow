<?php

namespace App\Modules\Fiscal\Services;

use App\Models\User;
use App\Modules\Fiscal\Models\FiscalDocument;
use App\Modules\Fiscal\Models\FiscalDocumentEvent;
use App\Modules\Fiscal\Support\FiscalDocumentType;
use App\Modules\Fiscal\Support\FiscalProviderCatalog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FocusNfcePayloadService
{
    public const SCHEMA_VERSION = 'focus-nfce-rj-2026-10';

    private const PAYMENT_CODES = [
        'cash' => '01',
        'credit_card' => '03',
        'debit_card' => '04',
        'transfer' => '18',
        'customer_credit' => '21',
        'other' => '99',
    ];

    private const CARD_BRANDS = [
        'visa' => '01',
        'mastercard' => '02',
        'master' => '02',
        'american express' => '03',
        'amex' => '03',
        'diners club' => '05',
        'diners' => '05',
        'elo' => '06',
        'hipercard' => '07',
        'aura' => '08',
        'cabal' => '09',
    ];

    public function __construct(
        private readonly FiscalNfceSequenceService $sequences,
        private readonly FiscalEnvironmentGuard $environment,
    ) {}

    public function generate(FiscalDocument $document, User $actor): FiscalDocument
    {
        return DB::transaction(function () use ($document, $actor): FiscalDocument {
            $document = FiscalDocument::query()->lockForUpdate()->findOrFail($document->id);

            if (is_array(data_get($document->request_payload, 'focus_nfce.payload'))) {
                return $document;
            }

            $this->assertSupported($document);
            $this->assertReady($document);

            $series = (int) data_get($document->issuer_snapshot, 'focus_nfce.series');
            $reservation = $this->sequences->reserve($document, $series);
            $generatedAt = now('America/Sao_Paulo');
            $payload = $this->payload($document->fresh(), $reservation, $generatedAt->toIso8601String());

            $document->update([
                'request_payload' => [
                    'focus_nfce' => [
                        'schema_version' => self::SCHEMA_VERSION,
                        'generated_at' => $generatedAt->toIso8601String(),
                        'model' => '65',
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
                    'model' => '65',
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
        if (! $this->environment->allowsDocument($document)
            || $document->type !== FiscalDocumentType::NFCE) {
            throw ValidationException::withMessages([
                'payload' => 'A geração aceita somente NFC-e modelo 65 da Focus para emitente do RJ em ambiente fiscal autorizado.',
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
        $missing = [];

        $this->requireDigits($missing, 'CNPJ do emitente', data_get($issuer, 'document'), 14);
        $this->requireValue($missing, 'inscrição estadual do emitente', data_get($issuer, 'state_registration'));
        $this->requireAllowed($missing, 'regime tributário do emitente', data_get($issuer, 'tax_regime'), ['simple_national', 'simple_excess_sublimit', 'normal']);
        $this->requireDigits($missing, 'município do emitente', data_get($issuer, 'municipality_code'), 7);
        $this->requireDigits($missing, 'CEP do emitente', data_get($issuer, 'address.zip_code'), 8);
        $this->requireValue($missing, 'logradouro do emitente', data_get($issuer, 'address.street'));
        $this->requireValue($missing, 'número do emitente', data_get($issuer, 'address.number'));
        $this->requireValue($missing, 'bairro do emitente', data_get($issuer, 'address.district'));
        $this->requireValue($missing, 'cidade do emitente', data_get($issuer, 'address.city'));
        $this->requireValue($missing, 'referência externa do certificado', data_get($issuer, 'certificate_reference'));

        $certificateExpiry = data_get($issuer, 'certificate_expires_at');
        if (! $this->certificateIsCurrent($certificateExpiry)) {
            $missing[] = 'certificado dentro da validade';
        }

        $this->requireIntegerRange($missing, 'série da NFC-e', data_get($issuer, 'focus_nfce.series'), 0, 999);
        $this->requireDigits($missing, 'identificador do CSC de '.$document->environment, data_get($issuer, 'focus_nfce.csc_id'), 6);
        $this->requireValue($missing, 'referência externa do CSC de '.$document->environment, data_get($issuer, 'focus_nfce.csc_reference'));
        if (! data_get($issuer, 'focus_nfce.sefaz_credentialed', false)) {
            $missing[] = 'confirmação do credenciamento NFC-e na SEFAZ-RJ';
        }

        if (data_get($recipient, 'consumer_final') === false) {
            $missing[] = 'venda destinada a consumidor final';
        }

        if (data_get($totals, 'requires_allocation') !== 'no') {
            $missing[] = 'rateio concluído para venda mista ou com ajuste global';
        }

        if ($items === []) {
            $missing[] = 'produto da NFC-e';
        }

        if (count($items) > 990) {
            $missing[] = 'limite de 990 itens por NFC-e';
        }

        foreach ($items as $index => $item) {
            $this->validateItem($missing, $item, $index + 1, (string) data_get($issuer, 'tax_regime'));
        }

        $recipientDocument = $this->digits(data_get($recipient, 'document'));
        if ($recipientDocument !== '' && ! in_array(strlen($recipientDocument), [11, 14], true)) {
            $missing[] = 'CPF/CNPJ do destinatário identificado';
        }
        if ($recipientDocument !== '') {
            $this->requireValue($missing, 'nome do destinatário identificado', data_get($recipient, 'name'));
        }

        if (data_get($totals, 'delivery') === 'yes') {
            if ($recipientDocument === '') {
                $missing[] = 'CPF/CNPJ do destinatário da entrega';
            }
            foreach (['street' => 'logradouro', 'number' => 'número', 'district' => 'bairro', 'city' => 'cidade'] as $field => $label) {
                $this->requireValue($missing, $label.' da entrega', data_get($recipient, 'address.'.$field));
            }
            $this->requireDigits($missing, 'CEP da entrega', data_get($recipient, 'address.zip_code'), 8);
            if (strtoupper((string) data_get($recipient, 'address.state')) !== 'RJ') {
                $missing[] = 'entrega dentro do RJ nesta etapa';
            }
        }

        $gross = $this->grossTotal($items);
        $discount = $this->discountTotal($items);
        $additions = round((float) data_get($totals, 'additions_total'), 2);
        $delivery = round((float) data_get($totals, 'delivery_fee'), 2);
        $net = round((float) data_get($totals, 'total'), 2);
        if ($gross <= 0 || abs(($gross - $discount + $additions + $delivery) - $net) > 0.01) {
            $missing[] = 'fechamento entre produtos, descontos e total da NFC-e';
        }

        try {
            $this->paymentPayload($document);
        } catch (ValidationException $exception) {
            $missing[] = collect($exception->errors())->flatten()->first() ?: 'formas de pagamento válidas';
        }

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'payload' => 'Complete antes de gerar o payload: '.implode(', ', array_unique($missing)).'.',
            ]);
        }
    }

    /** @param array<int, string> $missing @param array<string, mixed> $item */
    private function validateItem(array &$missing, array $item, int $number, string $taxRegime): void
    {
        $prefix = "item {$number}: ";
        $fiscal = $item['fiscal'] ?? [];
        $description = trim((string) ($item['description'] ?? ''));
        $quantity = (float) ($item['quantity'] ?? 0);
        $unitPrice = (float) ($item['unit_price'] ?? 0);
        $discount = (float) ($item['discount_total'] ?? 0);
        $additions = (float) ($item['allocated_additions_total'] ?? 0);
        $delivery = (float) ($item['allocated_delivery_fee'] ?? 0);
        $net = (float) ($item['total'] ?? 0);

        if (! filled($item['product_id'] ?? null)) {
            $missing[] = $prefix.'produto vinculado ao catálogo';
        }
        if ($description === '' || mb_strlen($description) > 120) {
            $missing[] = $prefix.'descrição entre 1 e 120 caracteres';
        }
        if ($quantity <= 0 || $unitPrice < 0 || min($discount, $additions, $delivery) < 0
            || abs((round($quantity * $unitPrice, 2) - round($discount, 2) + round($additions, 2) + round($delivery, 2)) - round($net, 2)) > 0.01) {
            $missing[] = $prefix.'quantidade, valor, desconto e total consistentes';
        }

        $this->requireDigits($missing, $prefix.'NCM', data_get($fiscal, 'ncm'), 8);
        $this->requireDigits($missing, $prefix.'CFOP', data_get($fiscal, 'default_cfop'), 4);
        $this->requireAllowed($missing, $prefix.'origem da mercadoria', data_get($fiscal, 'origin_code'), ['0', '1', '2', '3', '4', '5', '6', '7', '8']);
        $this->requireDigits($missing, $prefix.'CST do PIS', data_get($fiscal, 'pis_code'), 2);
        $this->requireDigits($missing, $prefix.'CST da COFINS', data_get($fiscal, 'cofins_code'), 2);
        $this->requireDigits($missing, $prefix.'CST do IBS/CBS', data_get($fiscal, 'ibs_cbs_tax_status'), 3);
        $this->requireDigits($missing, $prefix.'classificação do IBS/CBS', data_get($fiscal, 'ibs_cbs_classification_code'), 6);

        if (! preg_match('/^[A-Z0-9]{1,6}$/', (string) data_get($fiscal, 'fiscal_unit'))) {
            $missing[] = $prefix.'unidade tributável';
        }

        $icmsLength = $taxRegime === 'simple_national' ? 3 : 2;
        $this->requireDigits($missing, $prefix.($icmsLength === 3 ? 'CSOSN' : 'CST').' do ICMS', data_get($fiscal, 'icms_code'), $icmsLength);
    }

    /** @param array{series: int, number: int} $reservation @return array<string, mixed> */
    private function payload(FiscalDocument $document, array $reservation, string $generatedAt): array
    {
        $issuer = $document->issuer_snapshot;
        $recipient = $document->recipient_snapshot ?? [];
        $totals = $document->totals_snapshot;
        $recipientDocument = $this->digits(data_get($recipient, 'document'));
        $payments = $this->paymentPayload($document);
        $gross = $this->grossTotal($document->items_snapshot);
        $discount = $this->discountTotal($document->items_snapshot);

        $payload = [
            'natureza_operacao' => 'VENDA AO CONSUMIDOR',
            'serie' => (string) $reservation['series'],
            'numero' => (string) $reservation['number'],
            'data_emissao' => $generatedAt,
            'tipo_documento' => '1',
            'local_destino' => '1',
            'finalidade_emissao' => '1',
            'consumidor_final' => '1',
            'presenca_comprador' => data_get($totals, 'delivery') === 'yes' ? '4' : '1',
            'cnpj_emitente' => $this->digits(data_get($issuer, 'document')),
            'nome_emitente' => data_get($issuer, 'corporate_name'),
            'nome_fantasia_emitente' => data_get($issuer, 'trade_name'),
            'logradouro_emitente' => data_get($issuer, 'address.street'),
            'numero_emitente' => data_get($issuer, 'address.number'),
            'complemento_emitente' => data_get($issuer, 'address.complement'),
            'bairro_emitente' => data_get($issuer, 'address.district'),
            'codigo_municipio_emitente' => $this->digits(data_get($issuer, 'municipality_code')),
            'municipio_emitente' => data_get($issuer, 'address.city'),
            'uf_emitente' => 'RJ',
            'cep_emitente' => $this->digits(data_get($issuer, 'address.zip_code')),
            'inscricao_estadual_emitente' => preg_replace('/\s+/', '', (string) data_get($issuer, 'state_registration')),
            'regime_tributario_emitente' => $this->taxRegimeCode((string) data_get($issuer, 'tax_regime')),
            'indicador_inscricao_estadual_destinatario' => $recipientDocument !== '' ? '9' : null,
            strlen($recipientDocument) === 14 ? 'cnpj_destinatario' : 'cpf_destinatario' => $recipientDocument !== '' ? $recipientDocument : null,
            'nome_destinatario' => $recipientDocument !== '' ? data_get($recipient, 'name') : null,
            'email_destinatario' => $recipientDocument !== '' ? data_get($recipient, 'email') : null,
            'items' => $this->itemsPayload($document->items_snapshot),
            'formas_pagamento' => $payments['forms'],
            'valor_produtos' => number_format($gross, 2, '.', ''),
            'valor_desconto' => $discount > 0 ? number_format($discount, 2, '.', '') : null,
            'valor_frete' => (float) data_get($totals, 'delivery_fee') > 0
                ? number_format((float) data_get($totals, 'delivery_fee'), 2, '.', '')
                : null,
            'valor_outras_despesas' => (float) data_get($totals, 'additions_total') > 0
                ? number_format((float) data_get($totals, 'additions_total'), 2, '.', '')
                : null,
            'valor_total' => number_format((float) data_get($totals, 'total'), 2, '.', ''),
            'valor_troco' => $payments['change'] > 0 ? number_format($payments['change'], 2, '.', '') : null,
            'modalidade_frete' => '9',
        ];

        if (data_get($totals, 'delivery') === 'yes') {
            $payload = array_replace($payload, [
                'logradouro_destinatario' => data_get($recipient, 'address.street'),
                'numero_destinatario' => data_get($recipient, 'address.number'),
                'complemento_destinatario' => data_get($recipient, 'address.complement'),
                'bairro_destinatario' => data_get($recipient, 'address.district'),
                'municipio_destinatario' => data_get($recipient, 'address.city'),
                'uf_destinatario' => 'RJ',
                'cep_destinatario' => $this->digits(data_get($recipient, 'address.zip_code')),
            ]);
        }

        return collect($payload)
            ->reject(fn ($value): bool => $value === null || $value === '')
            ->all();
    }

    /** @param array<int, array<string, mixed>> $items @return array<int, array<string, mixed>> */
    private function itemsPayload(array $items): array
    {
        return collect($items)->values()->map(function (array $item, int $index): array {
            $fiscal = $item['fiscal'];
            $gross = round((float) $item['quantity'] * (float) $item['unit_price'], 2);
            $discount = round((float) $item['discount_total'], 2);
            $additions = round((float) ($item['allocated_additions_total'] ?? 0), 2);
            $delivery = round((float) ($item['allocated_delivery_fee'] ?? 0), 2);

            return collect([
                'numero_item' => $index + 1,
                'codigo_produto' => (string) $item['product_id'],
                'descricao' => trim((string) $item['description']),
                'codigo_ncm' => (string) $fiscal['ncm'],
                'cest' => $fiscal['cest'] ?? null,
                'codigo_beneficio_fiscal' => $fiscal['tax_benefit_code'] ?? null,
                'cfop' => (string) $fiscal['default_cfop'],
                'unidade_comercial' => (string) $fiscal['fiscal_unit'],
                'quantidade_comercial' => $this->decimal((float) $item['quantity'], 4),
                'valor_unitario_comercial' => $this->decimal((float) $item['unit_price'], 10),
                'valor_bruto' => number_format($gross, 2, '.', ''),
                'valor_desconto' => $discount > 0 ? number_format($discount, 2, '.', '') : null,
                'valor_frete' => $delivery > 0 ? number_format($delivery, 2, '.', '') : null,
                'valor_outras_despesas' => $additions > 0 ? number_format($additions, 2, '.', '') : null,
                'icms_origem' => (string) $fiscal['origin_code'],
                'icms_situacao_tributaria' => (string) $fiscal['icms_code'],
                'pis_situacao_tributaria' => (string) $fiscal['pis_code'],
                'cofins_situacao_tributaria' => (string) $fiscal['cofins_code'],
                'ibs_cbs_situacao_tributaria' => (string) $fiscal['ibs_cbs_tax_status'],
                'ibs_cbs_classificacao_tributaria' => (string) $fiscal['ibs_cbs_classification_code'],
            ])->reject(fn ($value): bool => $value === null || $value === '')->all();
        })->all();
    }

    /** @return array{forms: array<int, array<string, mixed>>, change: float} */
    private function paymentPayload(FiscalDocument $document): array
    {
        $forms = [];
        $paid = 0.0;
        $total = round((float) data_get($document->totals_snapshot, 'total'), 2);
        $change = round((float) data_get($document->totals_snapshot, 'change_total'), 2);
        $pixCode = (string) data_get($document->issuer_snapshot, 'focus_nfce.pix_payment_code');

        foreach ($document->payments_snapshot ?? [] as $payment) {
            $amount = round((float) ($payment['amount'] ?? 0), 2);
            if ($amount <= 0) {
                throw ValidationException::withMessages(['payload' => 'Há uma forma de pagamento com valor inválido']);
            }

            $method = (string) ($payment['method'] ?? '');
            $code = $method === 'pix' ? $pixCode : (self::PAYMENT_CODES[$method] ?? null);
            if ($method === 'pix' && ! in_array($code, ['17', '20'], true)) {
                throw ValidationException::withMessages(['payload' => 'Defina se o Pix usa o código fiscal 17 (dinâmico) ou 20 (estático)']);
            }
            if ($code === null) {
                throw ValidationException::withMessages(['payload' => 'Mapeie a forma de pagamento '.($payment['method_name'] ?? $method)]);
            }

            $form = [
                'indicador_pagamento' => $method === 'credit_card' && (int) ($payment['installments'] ?? 1) > 1 ? '1' : '0',
                'forma_pagamento' => $code,
                'valor_pagamento' => number_format($amount, 2, '.', ''),
            ];

            if ($code === '99') {
                $description = trim((string) ($payment['method_name'] ?? 'Outro meio'));
                $form['descricao_pagamento'] = Str::limit($description, 60, '');
            }

            if (in_array($method, ['credit_card', 'debit_card'], true)) {
                $form['tipo_integracao'] = '2';
                $brand = $this->cardBrandCode($payment['card_brand'] ?? null);
                if ($brand !== null) {
                    $form['bandeira_operadora'] = $brand;
                }
                if (filled($payment['reference'] ?? null)) {
                    $form['numero_autorizacao'] = Str::limit(trim((string) $payment['reference']), 128, '');
                }
            }

            $forms[] = $form;
            $paid += $amount;
        }

        $covered = round($paid - $change, 2);
        if ($change < 0 || $covered - $total > 0.01) {
            throw ValidationException::withMessages(['payload' => 'Pagamentos e troco não fecham com o total da NFC-e']);
        }

        $pending = round($total - $covered, 2);
        if ($pending > 0.01) {
            $forms[] = [
                'indicador_pagamento' => '1',
                'forma_pagamento' => '91',
                'valor_pagamento' => number_format($pending, 2, '.', ''),
            ];
        }

        if ($forms === []) {
            $forms[] = [
                'indicador_pagamento' => '1',
                'forma_pagamento' => '91',
                'valor_pagamento' => number_format($total, 2, '.', ''),
            ];
        }

        return ['forms' => $forms, 'change' => $change];
    }

    private function taxRegimeCode(string $taxRegime): string
    {
        return match ($taxRegime) {
            'simple_national' => '1',
            'simple_excess_sublimit' => '2',
            'normal' => '3',
            default => throw ValidationException::withMessages(['payload' => 'Regime tributário do emitente inválido']),
        };
    }

    private function cardBrandCode(mixed $brand): ?string
    {
        $normalized = Str::lower(Str::ascii(trim((string) $brand)));

        return self::CARD_BRANDS[$normalized] ?? null;
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

    private function decimal(float $value, int $scale): string
    {
        $formatted = rtrim(rtrim(number_format($value, $scale, '.', ''), '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }

    private function certificateIsCurrent(mixed $value): bool
    {
        if (! filled($value)) {
            return false;
        }

        try {
            return Carbon::parse((string) $value)->endOfDay()->isFuture();
        } catch (\Throwable) {
            return false;
        }
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
