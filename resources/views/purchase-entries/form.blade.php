@php
  $entryItems = $entry?->items?->map(fn ($item) => [
    'product_id' => $item->product_id,
    'description' => $item->description,
    'quantity' => $item->quantity,
    'unit_cost' => $item->unit_cost,
    'lot_number' => $item->lot_number,
    'expires_at' => optional($item->expires_at)->format('Y-m-d'),
    'notes' => $item->notes,
  ])->toArray() ?? [];
  $rows = array_values(old('items', $entryItems));
  $rowCount = max(8, count($rows));
  $financials = $entry?->financialTransactions?->sortBy('installment_number')->values() ?? collect();
  $firstFinancial = $financials->first();
  $secondFinancial = $financials->get(1);
  $installmentsCount = old('installments_count', $firstFinancial?->installment_total ?: max(1, $financials->count()));
  $installmentIntervalDays = old(
    'installment_interval_days',
    $firstFinancial?->due_date && $secondFinancial?->due_date
      ? max(1, $firstFinancial->due_date->diffInDays($secondFinancial->due_date))
      : 30
  );
  $paymentMethods = [
    'cash' => 'Dinheiro',
    'pix' => 'PIX',
    'debit_card' => 'Cartao de debito',
    'credit_card' => 'Cartao de credito',
    'transfer' => 'Transferencia',
    'bank_slip' => 'Boleto',
    'other' => 'Outro',
  ];
@endphp

<div class="form-grid">
  <div class="field">
    <label for="status">Status</label>
    <select id="status" name="status">
      <option value="received" @selected(old('status', $entry->status ?? 'received') === 'received')>Recebida</option>
      <option value="draft" @selected(old('status', $entry->status ?? 'received') === 'draft')>Rascunho</option>
      <option value="cancelled" @selected(old('status', $entry->status ?? 'received') === 'cancelled')>Cancelada</option>
    </select>
  </div>
  <div class="field">
    <label for="supplier_id">Fornecedor</label>
    <select id="supplier_id" name="supplier_id">
      <option value="">Selecione</option>
      @foreach($suppliers as $supplier)
        <option value="{{ $supplier->id }}" @selected((int) old('supplier_id', $entry->supplier_id ?? 0) === $supplier->id)>
          {{ $supplier->name }}
        </option>
      @endforeach
    </select>
  </div>
  <div class="field">
    <label for="invoice_number">Numero da NF</label>
    <input id="invoice_number" name="invoice_number" value="{{ old('invoice_number', $entry->invoice_number ?? '') }}">
  </div>
  <div class="field">
    <label for="invoice_key">Chave da NF</label>
    <input id="invoice_key" name="invoice_key" value="{{ old('invoice_key', $entry->invoice_key ?? '') }}">
  </div>
  <div class="field">
    <label for="purchased_at">Data da compra</label>
    <input id="purchased_at" name="purchased_at" type="datetime-local" value="{{ old('purchased_at', isset($entry) && $entry?->purchased_at ? $entry->purchased_at->format('Y-m-d\TH:i') : now()->format('Y-m-d\TH:i')) }}">
  </div>
  <div class="field">
    <label for="received_at">Data do recebimento</label>
    <input id="received_at" name="received_at" type="datetime-local" value="{{ old('received_at', isset($entry) && $entry?->received_at ? $entry->received_at->format('Y-m-d\TH:i') : now()->format('Y-m-d\TH:i')) }}">
  </div>
  <div class="field">
    <label for="clinic_id">Clinica ID</label>
    <input id="clinic_id" name="clinic_id" type="number" value="{{ old('clinic_id', $entry->clinic_id ?? '') }}">
  </div>
  <div class="field">
    <label>Total estimado</label>
    <div class="readonly-total" data-purchase-total>R$ {{ number_format((float) ($entry->total ?? 0), 2, ',', '.') }}</div>
  </div>
  <div class="field">
    <label for="payment_due_date">Primeiro vencimento</label>
    <input id="payment_due_date" name="payment_due_date" type="date" value="{{ old('payment_due_date', $firstFinancial?->due_date ? $firstFinancial->due_date->format('Y-m-d') : '') }}">
  </div>
  <div class="field">
    <label for="installments_count">Parcelas</label>
    <input id="installments_count" name="installments_count" type="number" min="1" max="60" value="{{ $installmentsCount }}">
  </div>
  <div class="field">
    <label for="installment_interval_days">Intervalo entre parcelas</label>
    <input id="installment_interval_days" name="installment_interval_days" type="number" min="1" max="365" value="{{ $installmentIntervalDays }}">
  </div>
  <div class="field">
    <label for="payment_status">Pagamento</label>
    <select id="payment_status" name="payment_status">
      <option value="pending" @selected(old('payment_status', $firstFinancial->status ?? 'pending') === 'pending')>Pendente</option>
      <option value="paid" @selected(old('payment_status', $firstFinancial->status ?? 'pending') === 'paid')>Pago</option>
      <option value="overdue" @selected(old('payment_status', $firstFinancial->status ?? 'pending') === 'overdue')>Vencido</option>
      <option value="cancelled" @selected(old('payment_status', $firstFinancial->status ?? 'pending') === 'cancelled')>Cancelado</option>
    </select>
  </div>
  <div class="field">
    <label for="payment_method">Forma de pagamento</label>
    <select id="payment_method" name="payment_method">
      <option value="">Selecione</option>
      @foreach($paymentMethods as $value => $label)
        <option value="{{ $value }}" @selected(old('payment_method', $firstFinancial->payment_method ?? '') === $value)>{{ $label }}</option>
      @endforeach
    </select>
  </div>
  <div class="field">
    <label for="paid_at">Data do pagamento</label>
    <input id="paid_at" name="paid_at" type="datetime-local" value="{{ old('paid_at', $firstFinancial?->paid_at ? $firstFinancial->paid_at->format('Y-m-d\TH:i') : '') }}">
  </div>
  <div class="field">
    <label for="payment_reference">Referencia</label>
    <input id="payment_reference" name="payment_reference" value="{{ old('payment_reference', $firstFinancial->reference ?? '') }}" placeholder="Boleto, comprovante ou referencia">
  </div>
  <div class="field full">
    <label for="notes">Observacoes</label>
    <textarea id="notes" name="notes">{{ old('notes', $entry->notes ?? '') }}</textarea>
  </div>
</div>

<div class="panel nested-panel">
  <div class="panel-heading">
    <div>
      <h2>Produtos recebidos</h2>
      <p>Informe custo, quantidade, lote e validade de cada item.</p>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Produto</th>
          <th>Descricao</th>
          <th>Qtd</th>
          <th>Custo unit.</th>
          <th>Lote</th>
          <th>Validade</th>
          <th>Total</th>
        </tr>
      </thead>
      <tbody>
        @for($index = 0; $index < $rowCount; $index++)
          @php($item = $rows[$index] ?? [])
          <tr data-purchase-item-row>
            <td>
              <select name="items[{{ $index }}][product_id]" data-purchase-product-select>
                <option value="">Selecione</option>
                @foreach($products as $product)
                  <option
                    value="{{ $product->id }}"
                    data-description="{{ $product->name }}"
                    data-cost="{{ $product->cost_price }}"
                    @selected((int) ($item['product_id'] ?? 0) === $product->id)
                  >
                    {{ $product->name }}
                  </option>
                @endforeach
              </select>
            </td>
            <td><input name="items[{{ $index }}][description]" value="{{ $item['description'] ?? '' }}" data-purchase-description></td>
            <td><input name="items[{{ $index }}][quantity]" type="number" step="0.001" min="0" value="{{ $item['quantity'] ?? '' }}" data-purchase-quantity></td>
            <td><input name="items[{{ $index }}][unit_cost]" type="text" inputmode="decimal" placeholder="0,00" value="{{ $item['unit_cost'] ?? '' }}" data-money-input data-purchase-unit-cost></td>
            <td><input name="items[{{ $index }}][lot_number]" value="{{ $item['lot_number'] ?? '' }}" placeholder="Lote"></td>
            <td><input name="items[{{ $index }}][expires_at]" type="date" value="{{ $item['expires_at'] ?? '' }}"></td>
            <td><span data-purchase-row-total>R$ 0,00</span></td>
          </tr>
        @endfor
      </tbody>
    </table>
  </div>
</div>

<div class="actions form-actions">
  <button type="submit">Salvar entrada</button>
  <a class="button secondary" href="{{ route('purchase-entries.index') }}">Cancelar</a>
</div>
