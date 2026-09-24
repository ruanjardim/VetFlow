<?php

namespace App\Modules\Sales\Requests\Concerns;

use App\Modules\Sales\Models\PaymentMethod;
use App\Modules\Sales\Services\PaymentMethodService;

/**
 * Rules shared by the PDV, the advanced sale form and the later receipt:
 * the payment method belongs to the sale clinic, is active, accepts the
 * informed installments and gets the NSU when the clinic requires it.
 */
trait ValidatesPaymentMethods
{
    /**
     * Fills the legacy kind (sale_payments.method) from the informed method,
     * so the cash and change rules keep working by kind.
     *
     * @param array<string, mixed> $payment
     * @return array<string, mixed>
     */
    protected function normalizePaymentMethodInput(?int $clinicId, array $payment): array
    {
        $paymentMethodId = $payment['payment_method_id'] ?? null;
        $payment['payment_method_id'] = is_numeric($paymentMethodId) && (int) $paymentMethodId > 0
            ? (int) $paymentMethodId
            : null;

        if ($payment['payment_method_id']) {
            $method = app(PaymentMethodService::class)->find($clinicId, $payment['payment_method_id']);

            if ($method) {
                $payment['method'] = $method->kind;
            }
        }

        return $payment;
    }

    /**
     * @param array<string, mixed> $payment
     */
    protected function resolvePaymentMethod(?int $clinicId, array $payment): ?PaymentMethod
    {
        $service = app(PaymentMethodService::class);

        if (! empty($payment['payment_method_id'])) {
            return $service->find($clinicId, $payment['payment_method_id']);
        }

        return ! empty($payment['method']) && is_string($payment['method'])
            ? $service->defaultForKind($clinicId, $payment['method'])
            : null;
    }

    /**
     * @param array<string, mixed> $payment
     * @return array<int, string>
     */
    protected function paymentMethodErrors(?PaymentMethod $method, array $payment, bool $requireReference): array
    {
        if (! $method) {
            return [];
        }

        $errors = [];

        if (! empty($payment['payment_method_id']) && ! $method->active) {
            $errors[] = 'A forma de pagamento '.$method->name.' está inativa. Escolha outra.';
        }

        $installments = max(1, (int) ($payment['installments'] ?? 1));

        if ($installments > $method->maxInstallments()) {
            $errors[] = $method->maxInstallments() > 1
                ? $method->name.' aceita até '.$method->maxInstallments().' parcelas.'
                : $method->name.' não aceita parcelamento.';
        }

        $reference = trim((string) ($payment['reference'] ?? $payment['transaction_reference'] ?? ''));

        if ($requireReference && $method->requires_reference && $reference === '') {
            $errors[] = match (true) {
                $method->isCard() => 'Informe o NSU ou a autorização do pagamento em '.$method->name.'.',
                $method->kind === 'pix' => 'Informe o ID da transação do pagamento em '.$method->name.'.',
                default => 'Informe a referência do pagamento em '.$method->name.'.',
            };
        }

        return $errors;
    }
}
