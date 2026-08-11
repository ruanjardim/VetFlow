@php
  $statuses = [
    'draft' => 'Rascunho',
    'completed' => 'Concluida',
    'cancelled' => 'Cancelada',
    'returned' => 'Devolvida',
  ];

  $paymentMethods = [
    'cash' => 'Dinheiro',
    'pix' => 'Pix',
    'debit_card' => 'Cartao debito',
    'credit_card' => 'Cartao credito',
    'transfer' => 'Transferencia',
    'other' => 'Outro',
  ];

  $selectedClinicId = (int) old('clinic_id', $sale->clinic_id ?? request('clinic_id', $clinics->count() === 1 ? $clinics->first()->id : 0));

  $rows = old('items');

  if ($rows === null && isset($sale) && $sale) {
    $rows = $sale->items->map(fn ($item) => [
      'type' => $item->type,
      'product_id' => $item->product_id,
      'petshop_service_id' => $item->petshop_service_id,
      'description' => $item->description,
      'quantity' => $item->quantity,
      'unit_price' => $item->unit_price,
      'discount_total' => $item->discount_total,
    ])->toArray();
  }

  $rows = array_pad($rows ?: [], 8, []);

  $paymentRows = old('payments');

  if ($paymentRows === null && isset($sale) && $sale) {
    $paymentRows = $sale->payments->map(fn ($payment) => [
      'method' => $payment->method,
      'amount' => $payment->amount,
      'installments' => $payment->installments,
      'card_brand' => $payment->card_brand,
      'acquirer' => $payment->acquirer,
      'paid_at' => $payment->paid_at?->format('Y-m-d\TH:i'),
      'reference' => $payment->reference,
      'transaction_reference' => $payment->transaction_reference,
      'notes' => $payment->notes,
    ])->toArray();
  }

  $paymentRows = array_pad($paymentRows ?: [], 4, []);
  $locked = isset($sale) && $sale && (
    $sale->stock_applied
    || $sale->financial_applied
    || in_array($sale->status, ['cancelled', 'returned'], true)
  );
@endphp

<div class="form-grid" data-sale-form data-sale-locked="{{ $locked ? '1' : '0' }}">
  @if($locked)
    <div class="field full">
      <div class="alert success">Venda protegida. Os totais, itens e pagamentos ficam travados para preservar estoque, financeiro e auditoria.</div>
    </div>
  @endif

  @include('shared.clinic-required-alert', ['clinics' => $clinics])

  <div class="field">
    <label for="status">Status</label>
    <select id="status" name="status" data-sale-status @disabled($locked)>
      @foreach($statuses as $value => $label)
        <option value="{{ $value }}" @selected(old('status', $sale->status ?? 'draft') === $value)>{{ $label }}</option>
      @endforeach
    </select>
    @if($locked)
      <input type="hidden" name="status" value="{{ $sale->status }}">
    @endif
  </div>
  <div class="field">
    <label for="sold_at">Data da venda</label>
    <input id="sold_at" name="sold_at" type="datetime-local" value="{{ old('sold_at', isset($sale) && $sale?->sold_at ? $sale->sold_at->format('Y-m-d\TH:i') : now()->format('Y-m-d\TH:i')) }}">
  </div>
  <div class="field">
    <label for="service_order_id">Comanda</label>
    <select id="service_order_id" name="service_order_id">
      <option value="">Venda direta</option>
      @foreach($serviceOrders as $serviceOrder)
        <option value="{{ $serviceOrder->id }}" @selected((int) old('service_order_id', $sale->service_order_id ?? 0) === $serviceOrder->id)>
          {{ $serviceOrder->code }} - {{ $serviceOrder->tutor?->name ?? 'Sem responsável' }} / {{ $serviceOrder->patient?->name ?? 'Sem pet' }} - R$ {{ number_format((float) $serviceOrder->total, 2, ',', '.') }}
        </option>
      @endforeach
    </select>
  </div>
  @if(auth()->user()?->clinic_id === null)
    <div class="field">
      <label for="clinic_id">Clinica</label>
      <select id="clinic_id" name="clinic_id">
        <option value="">Selecione</option>
        @foreach($clinics as $clinic)
          <option value="{{ $clinic->id }}" @selected($selectedClinicId === $clinic->id)>{{ $clinic->trade_name ?? $clinic->corporate_name }}</option>
        @endforeach
      </select>
    </div>
  @endif
  <div class="field">
    <label for="tutor_id">Responsável</label>
    <select id="tutor_id" name="tutor_id">
      <option value="">Selecione</option>
      @foreach($tutors as $tutor)
        <option value="{{ $tutor->id }}" @selected((int) old('tutor_id', $sale->tutor_id ?? 0) === $tutor->id)>{{ $tutor->name }}</option>
      @endforeach
    </select>
  </div>
  <div class="field">
    <label for="patient_id">Pet</label>
    <select id="patient_id" name="patient_id">
      <option value="">Selecione</option>
      @foreach($patients as $patient)
        <option value="{{ $patient->id }}" @selected((int) old('patient_id', $sale->patient_id ?? 0) === $patient->id)>{{ $patient->name }}</option>
      @endforeach
    </select>
  </div>
  <div class="field">
    <label for="discount_total">Desconto</label>
    <input id="discount_total" name="discount_total" type="text" inputmode="decimal" placeholder="0,00" value="{{ old('discount_total', $sale->discount_total ?? 0) }}" data-sale-discount @readonly($locked)>
  </div>
  <div class="field">
    <label for="additions_total">Acrescimo</label>
    <input id="additions_total" name="additions_total" type="text" inputmode="decimal" placeholder="0,00" value="{{ old('additions_total', $sale->additions_total ?? 0) }}" data-sale-additions @readonly($locked)>
  </div>
  <div class="field">
    <label>Total calculado</label>
    <div class="calculated-total" data-sale-total-input aria-live="polite">
      R$ {{ number_format((float) ($sale->total ?? 0), 2, ',', '.') }}
    </div>
  </div>
  <div class="field full">
    <label for="notes">Observacoes</label>
    <textarea id="notes" name="notes">{{ old('notes', $sale->notes ?? '') }}</textarea>
  </div>

  <div class="field full">
    <label>Itens da venda</label>
    <div
      class="sale-scan"
      data-sale-scanner
      data-sale-lookup-url="{{ route('sales.product-lookup', ['gtin' => '__GTIN__']) }}"
      data-sale-lookup-auto="{{ request()->has('scan') ? '1' : '0' }}"
      data-product-create-url="{{ route('products.create') }}?gtin=__GTIN__&from=sales"
      @if($locked) hidden @endif
    >
      <label for="sale_barcode">Leitor de codigo de barras</label>
      <div class="sale-scan-row">
        <input
          id="sale_barcode"
          type="text"
          inputmode="numeric"
          autocomplete="off"
          placeholder="Escaneie ou digite o EAN/GTIN"
          value="{{ request('scan', '') }}"
          data-sale-barcode-input
          @readonly($locked)
        >
        <button type="button" class="secondary" data-sale-barcode-button @disabled($locked)>Adicionar</button>
      </div>
      <div class="lookup-status muted" data-sale-lookup-status></div>
      <div class="sale-scan-actions">
        <a class="button secondary" href="{{ route('products.create') }}" target="_blank" rel="noopener" data-sale-create-product-link hidden>Cadastrar produto agora</a>
      </div>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Tipo</th>
            <th>Servico</th>
            <th>Produto</th>
            <th>Descricao</th>
            <th>Qtd</th>
            <th>Valor unit.</th>
            <th>Desc. item</th>
          </tr>
        </thead>
        <tbody>
          @foreach($rows as $index => $row)
            <tr data-sale-item-row>
              <td>
                <select name="items[{{ $index }}][type]" data-sale-item-type @disabled($locked)>
                  <option value="product" @selected(($row['type'] ?? 'product') === 'product')>Produto</option>
                  <option value="service" @selected(($row['type'] ?? '') === 'service')>Servico</option>
                  <option value="custom" @selected(($row['type'] ?? '') === 'custom')>Avulso</option>
                </select>
              </td>
              <td>
                <select name="items[{{ $index }}][petshop_service_id]" data-sale-service-select @disabled($locked)>
                  <option value="">Selecione</option>
                  @foreach($petShopServices as $petShopService)
                    <option
                      value="{{ $petShopService->id }}"
                      data-description="{{ $petShopService->name }}"
                      data-price="{{ $petShopService->base_price }}"
                      @selected((int) ($row['petshop_service_id'] ?? 0) === $petShopService->id)
                    >
                      {{ $petShopService->name }}
                    </option>
                  @endforeach
                </select>
              </td>
              <td>
                <select name="items[{{ $index }}][product_id]" data-sale-product-select @disabled($locked)>
                  <option value="">Selecione</option>
                  @foreach($products as $product)
                    <option
                      value="{{ $product->id }}"
                      data-description="{{ $product->name }}"
                      data-price="{{ $product->sale_price }}"
                      @selected((int) ($row['product_id'] ?? 0) === $product->id)
                    >
                      {{ $product->name }}
                    </option>
                  @endforeach
                </select>
              </td>
              <td>
                <input name="items[{{ $index }}][description]" value="{{ $row['description'] ?? '' }}" data-sale-description @readonly($locked)>
              </td>
              <td>
                <input name="items[{{ $index }}][quantity]" type="number" step="0.001" min="0" value="{{ $row['quantity'] ?? '' }}" data-sale-quantity @readonly($locked)>
              </td>
              <td>
                <input name="items[{{ $index }}][unit_price]" type="text" inputmode="decimal" placeholder="0,00" value="{{ $row['unit_price'] ?? '' }}" data-sale-unit-price @readonly($locked)>
              </td>
              <td>
                <input name="items[{{ $index }}][discount_total]" type="text" inputmode="decimal" placeholder="0,00" value="{{ $row['discount_total'] ?? '' }}" data-sale-item-discount @readonly($locked)>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  <div class="field full">
    <div class="sale-checkout" data-sale-checkout>
      <div class="sale-checkout-total">
        <span>Total da venda</span>
        <strong data-sale-total-display>R$ 0,00</strong>
      </div>
      <div class="sale-checkout-grid">
        <div>
          <span>Subtotal</span>
          <strong data-sale-subtotal-display>R$ 0,00</strong>
        </div>
        <div>
          <span>Desconto</span>
          <strong data-sale-discount-display>R$ 0,00</strong>
        </div>
        <div>
          <span>Acrescimo</span>
          <strong data-sale-additions-display>R$ 0,00</strong>
        </div>
        <div>
          <span>Pago</span>
          <strong data-sale-paid-display>R$ 0,00</strong>
        </div>
        <div>
          <span data-sale-balance-label>Falta</span>
          <strong data-sale-balance-display>R$ 0,00</strong>
        </div>
      </div>
      <div class="sale-checkout-payment">
        <div class="field">
          <label for="sale_received_amount">Valor recebido</label>
          <input id="sale_received_amount" type="text" inputmode="decimal" placeholder="0,00" data-sale-received-amount @readonly($locked)>
        </div>
        <button type="button" class="secondary" data-sale-pay-balance @disabled($locked)>Receber saldo</button>
        <button type="button" data-sale-finalize @disabled($locked)>Finalizar venda</button>
      </div>
      <div class="lookup-status" data-sale-checkout-status></div>
    </div>
  </div>

  <div class="field full">
    <label>Pagamentos</label>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Forma</th>
            <th>Valor</th>
            <th>Parcelas</th>
            <th>Bandeira</th>
            <th>Operadora</th>
            <th>Data</th>
            <th>Referencia</th>
            <th>Observacao</th>
          </tr>
        </thead>
        <tbody>
          @foreach($paymentRows as $index => $payment)
            <tr>
              <td>
                <select name="payments[{{ $index }}][method]" data-sale-payment-method @disabled($locked)>
                  <option value="">Selecione</option>
                  @foreach($paymentMethods as $value => $label)
                    <option value="{{ $value }}" @selected(($payment['method'] ?? '') === $value)>{{ $label }}</option>
                  @endforeach
                </select>
              </td>
              <td>
                <input name="payments[{{ $index }}][amount]" type="text" inputmode="decimal" placeholder="0,00" value="{{ $payment['amount'] ?? '' }}" data-sale-payment-amount @readonly($locked)>
              </td>
              <td>
                <input name="payments[{{ $index }}][installments]" type="number" min="1" max="120" value="{{ $payment['installments'] ?? 1 }}" @readonly($locked)>
              </td>
              <td>
                <input name="payments[{{ $index }}][card_brand]" value="{{ $payment['card_brand'] ?? '' }}" @readonly($locked)>
              </td>
              <td>
                <input name="payments[{{ $index }}][acquirer]" value="{{ $payment['acquirer'] ?? '' }}" @readonly($locked)>
              </td>
              <td>
                <input name="payments[{{ $index }}][paid_at]" type="datetime-local" value="{{ $payment['paid_at'] ?? '' }}" @readonly($locked)>
              </td>
              <td>
                <input name="payments[{{ $index }}][reference]" value="{{ $payment['reference'] ?? $payment['transaction_reference'] ?? '' }}" @readonly($locked)>
              </td>
              <td>
                <input name="payments[{{ $index }}][notes]" value="{{ $payment['notes'] ?? '' }}" @readonly($locked)>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  <div class="field full">
    <div class="actions">
      <button type="submit">Salvar</button>
      <a class="button secondary" href="{{ route('sales.index') }}">Cancelar</a>
    </div>
  </div>
</div>
