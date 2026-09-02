<?php

namespace App\Modules\PurchaseEntries\Requests;

use App\Http\Requests\Concerns\ValidatesTenantScopedReferences;
use App\Modules\Products\Models\Product;
use App\Modules\PurchaseEntries\Services\ReplenishmentPurchaseDecisionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePurchaseEntryRequest extends FormRequest
{
    use ValidatesTenantScopedReferences;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = $this->all();

        if (isset($data['items']) && is_array($data['items'])) {
            $data['items'] = array_map(function ($item) {
                if (! is_array($item)) {
                    return $item;
                }

                $item['quantity'] = $this->normalizeDecimalValue($item['quantity'] ?? null);
                $item['unit_cost'] = $this->normalizeDecimalValue($item['unit_cost'] ?? null);
                $item['sale_price'] = $this->normalizeDecimalValue($item['sale_price'] ?? null);
                $item['margin_percent'] = $this->normalizeDecimalValue($item['margin_percent'] ?? null);
                $item['minimum_stock_after_entry'] = $this->normalizeDecimalValue($item['minimum_stock_after_entry'] ?? null);
                $item['replenishment_adjustment_reason'] = trim((string) ($item['replenishment_adjustment_reason'] ?? '')) ?: null;
                $item['replenishment_adjustment_note'] = trim((string) ($item['replenishment_adjustment_note'] ?? '')) ?: null;

                return $item;
            }, $data['items']);
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        return [
            'clinic_id' => [Rule::requiredIf($this->user()?->clinic_id === null), 'nullable', 'integer', 'exists:clinics,id'],
            'supplier_id' => ['nullable', 'integer', $this->existsInCurrentClinic('suppliers')],
            'status' => ['required', 'string', Rule::in(['draft', 'received', 'cancelled'])],
            'invoice_number' => ['nullable', 'string', 'max:255'],
            'invoice_key' => ['nullable', 'string', 'max:255'],
            'purchased_at' => ['nullable', 'date'],
            'received_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'payment_due_date' => ['nullable', 'date'],
            'payment_status' => ['nullable', 'string', Rule::in(['pending', 'paid', 'cancelled', 'overdue'])],
            'payment_method' => ['nullable', 'string', Rule::in(['cash', 'pix', 'debit_card', 'credit_card', 'transfer', 'bank_slip', 'other'])],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'installments_count' => ['nullable', 'integer', 'min:1', 'max:60'],
            'installment_interval_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'paid_at' => ['nullable', 'date'],

            'items' => ['required', 'array'],
            'items.*.product_id' => ['nullable', 'integer', $this->existsInCurrentClinic('products')],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0.001'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.sale_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.margin_percent' => ['nullable', 'numeric'],
            'items.*.update_sale_price' => ['nullable', 'boolean'],
            'items.*.minimum_stock_after_entry' => ['nullable', 'numeric', 'min:0'],
            'items.*.barcode_snapshot' => ['nullable', 'string', 'max:64'],
            'items.*.supplier_sku' => ['nullable', 'string', 'max:255'],
            'items.*.intelligence_status' => ['nullable', 'string', 'max:255'],
            'items.*.intelligence_metadata' => ['nullable'],
            'items.*.replenishment_adjustment_reason' => [
                'nullable',
                'string',
                Rule::in(array_keys(ReplenishmentPurchaseDecisionService::ADJUSTMENT_REASONS)),
            ],
            'items.*.replenishment_adjustment_note' => ['nullable', 'string', 'max:500'],
            'items.*.lot_number' => ['nullable', 'string', 'max:255'],
            'items.*.expires_at' => ['nullable', 'date'],
            'items.*.notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Informe o status da entrada.',
            'status.in' => 'Informe um status valido para a entrada.',
            'supplier_id.exists' => 'O fornecedor informado nao foi encontrado.',
            'items.required' => 'Inclua pelo menos um produto na entrada.',
            'items.*.product_id.exists' => 'Um dos produtos informados nao foi encontrado.',
            'items.*.quantity.min' => 'A quantidade de cada item precisa ser maior que zero.',
            'items.*.expires_at.date' => 'Informe uma validade valida para o lote.',
            'items.*.replenishment_adjustment_reason.in' => 'Selecione um motivo de ajuste valido.',
            'items.*.replenishment_adjustment_note.max' => 'A observacao do ajuste deve ter no maximo 500 caracteres.',
            'payment_status.in' => 'Informe um status de pagamento valido.',
            'payment_method.in' => 'Informe uma forma de pagamento valida.',
            'installments_count.min' => 'Informe pelo menos uma parcela.',
            'installments_count.max' => 'Informe no maximo 60 parcelas.',
            'installment_interval_days.min' => 'Informe um intervalo valido entre parcelas.',
            'clinic_id.required' => 'Selecione a clinica da entrada.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->billableItems() === []) {
                $validator->errors()->add('items', 'Inclua pelo menos um produto com quantidade na entrada.');
            }

            $this->validateReplenishmentAdjustmentReasons($validator);
        });
    }

    private function validateReplenishmentAdjustmentReasons(Validator $validator): void
    {
        $clinicId = (int) ($this->user()?->clinic_id ?: $this->input('clinic_id'));
        $supplierId = $this->filled('supplier_id') ? (int) $this->input('supplier_id') : null;
        $decisions = app(ReplenishmentPurchaseDecisionService::class);

        foreach ($this->input('items', []) as $index => $item) {
            if (! is_array($item) || empty($item['product_id'])) {
                continue;
            }

            $quantity = (float) ($item['quantity'] ?? 0);

            if ($quantity <= 0) {
                continue;
            }

            $product = Product::query()
                ->where('clinic_id', $clinicId)
                ->find((int) $item['product_id']);
            $unitCost = filled($item['unit_cost'] ?? null)
                ? (float) $item['unit_cost']
                : (float) ($product?->cost_price ?? 0);

            $metadata = $this->decodeMetadata($item['intelligence_metadata'] ?? null);
            $requiresReason = $decisions->requiresAdjustmentReason(
                $clinicId,
                (int) $item['product_id'],
                $supplierId,
                $quantity,
                $unitCost,
                $metadata,
                $item['intelligence_status'] ?? null,
            );

            if (! $requiresReason) {
                continue;
            }

            $reason = trim((string) ($item['replenishment_adjustment_reason'] ?? ''));

            if ($reason === '') {
                $validator->errors()->add(
                    "items.$index.replenishment_adjustment_reason",
                    'Informe por que a sugestao de reposicao foi ajustada.',
                );
            }

            if ($reason === 'other' && blank($item['replenishment_adjustment_note'] ?? null)) {
                $validator->errors()->add(
                    "items.$index.replenishment_adjustment_note",
                    'Descreva o outro motivo do ajuste.',
                );
            }
        }
    }

    /** @return array<string, mixed> */
    private function decodeMetadata(mixed $metadata): array
    {
        if (is_array($metadata)) {
            return $metadata;
        }

        if (! is_string($metadata) || trim($metadata) === '') {
            return [];
        }

        $decoded = json_decode($metadata, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function billableItems(): array
    {
        return array_values(array_filter(
            $this->input('items', []),
            fn (array $item) => ! empty($item['product_id']) && (float) ($item['quantity'] ?? 0) > 0
        ));
    }

    private function normalizeDecimalValue(mixed $value): mixed
    {
        if ($value === null || $value === '' || ! is_string($value)) {
            return $value;
        }

        $normalized = trim($value);

        if ($normalized === '') {
            return $value;
        }

        if (str_contains($normalized, ',')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        }

        return $normalized;
    }
}
