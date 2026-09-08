@php
  $quickMode = ($quickMode ?? false) && ! $sale;

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

  $selectedServiceOrderId = (int) old('service_order_id', $sale->service_order_id ?? request('service_order_id', 0));
  $selectedServiceOrder = $serviceOrders->firstWhere('id', $selectedServiceOrderId);
  $selectedClinicId = (int) old('clinic_id', $sale->clinic_id ?? request('clinic_id', $selectedServiceOrder?->clinic_id ?? ($clinics->count() === 1 ? $clinics->first()->id : 0)));
  $hasOldSaleInput = old('items') !== null || old('payments') !== null;
  $serviceOrderCatalog = $serviceOrders->mapWithKeys(fn ($serviceOrder) => [
    (string) $serviceOrder->id => [
      'id' => $serviceOrder->id,
      'clinic_id' => $serviceOrder->clinic_id,
      'tutor_id' => $serviceOrder->tutor_id,
      'patient_id' => $serviceOrder->patient_id,
      'discount_total' => (float) $serviceOrder->discount_total,
      'items' => $serviceOrder->items->map(fn ($item) => [
        'type' => $item->type,
        'product_id' => $item->product_id,
        'petshop_service_id' => $item->petshop_service_id,
        'description' => $item->description,
        'quantity' => (float) $item->quantity,
        'unit_price' => (float) $item->unit_price,
      ])->values()->all(),
    ],
  ]);

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

  $rowCapacity = max(8, (int) ($serviceOrders->max(fn ($serviceOrder) => $serviceOrder->items->count()) ?? 0));
  $rows = array_pad($rows ?: [], $rowCapacity, []);

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

<div
  class="form-grid {{ $quickMode ? 'sale-form--quick' : '' }}"
  data-sale-form
  data-sale-locked="{{ $locked ? '1' : '0' }}"
  data-sale-mode="{{ $quickMode ? 'quick' : 'advanced' }}"
  data-sale-clinic-id="{{ auth()->user()?->clinic_id ?? $selectedClinicId }}"
  data-sale-has-old-input="{{ $hasOldSaleInput ? '1' : '0' }}"
>
  @if($locked)
    <div class="field full">
      <div class="alert success">Venda protegida. Os totais, itens e pagamentos ficam travados para preservar estoque, financeiro e auditoria.</div>
    </div>
  @endif

  @include('shared.clinic-required-alert', ['clinics' => $clinics])

  @if($quickMode)
    <input type="hidden" name="status" value="{{ old('status', 'draft') }}" data-sale-status>
    <input type="hidden" name="sold_at" value="{{ old('sold_at', now()->format('Y-m-d\TH:i')) }}">
    <input type="hidden" name="source" value="petshop_pdv">
  @else
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
  @endif
  <div class="field">
    <label for="service_order_id">Comanda</label>
    <select id="service_order_id" name="service_order_id" @disabled($locked)>
      <option value="">Venda direta</option>
      @foreach($serviceOrders as $serviceOrder)
        <option value="{{ $serviceOrder->id }}" data-clinic-id="{{ $serviceOrder->clinic_id }}" @selected($selectedServiceOrderId === $serviceOrder->id)>
          {{ $serviceOrder->code }} - {{ $serviceOrder->tutor?->name ?? 'Sem responsável' }} / {{ $serviceOrder->patient?->name ?? 'Sem pet' }} - R$ {{ number_format((float) $serviceOrder->total, 2, ',', '.') }}
        </option>
      @endforeach
    </select>
    <script type="application/json" data-sale-service-order-catalog>@json($serviceOrderCatalog, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)</script>
  </div>
  @if(auth()->user()?->clinic_id === null)
    <div class="field">
      <label for="clinic_id">Clinica</label>
      <select id="clinic_id" name="clinic_id" data-sale-clinic-select @disabled($locked)>
        <option value="">Selecione</option>
        @foreach($clinics as $clinic)
          <option value="{{ $clinic->id }}" @selected($selectedClinicId === $clinic->id)>{{ $clinic->trade_name ?? $clinic->corporate_name }}</option>
        @endforeach
      </select>
    </div>
  @endif
  <div class="field">
    <label for="tutor_id">Responsável</label>
    <select id="tutor_id" name="tutor_id" data-sale-tutor-select @disabled($locked)>
      <option value="">Selecione</option>
      @foreach($tutors as $tutor)
        <option value="{{ $tutor->id }}" data-clinic-id="{{ $tutor->clinic_id }}" @selected((int) old('tutor_id', $sale->tutor_id ?? 0) === $tutor->id)>{{ $tutor->name }}</option>
      @endforeach
    </select>
    @can('tutors.manage')
      <a class="field-hint" href="{{ route('tutors.create') }}" target="_blank" rel="noopener">Cadastrar novo responsável</a>
    @endcan
  </div>
  <div class="field">
    <label for="patient_id">Pet</label>
    <select id="patient_id" name="patient_id" data-sale-patient-select @disabled($locked)>
      <option value="">Selecione</option>
      @foreach($patients as $patient)
        <option
          value="{{ $patient->id }}"
          data-clinic-id="{{ $patient->clinic_id }}"
          data-tutor-id="{{ $patient->tutor_id }}"
          @selected((int) old('patient_id', $sale->patient_id ?? 0) === $patient->id)
        >{{ $patient->name }}</option>
      @endforeach
    </select>
    @can('patients.manage')
      <a class="field-hint" href="{{ route('patients.create') }}" target="_blank" rel="noopener">Cadastrar novo pet</a>
    @endcan
  </div>
  @if($quickMode && auth()->user()?->hasPermission('tutors.manage') && auth()->user()?->hasPermission('patients.manage'))
    <div class="field full">
      <details class="sale-quick-customer" data-sale-quick-customer data-store-url="{{ route('sales.quick-customer.store') }}">
        <summary>Cadastrar responsável e pet sem sair do PDV</summary>
        <div class="sale-quick-customer-grid">
          <div class="field">
            <label for="quick_tutor_name">Nome do responsável</label>
            <input id="quick_tutor_name" autocomplete="name" data-sale-customer-tutor-name>
          </div>
          <div class="field">
            <label for="quick_tutor_phone">Telefone</label>
            <input id="quick_tutor_phone" autocomplete="tel" data-sale-customer-tutor-phone>
          </div>
          <div class="field">
            <label for="quick_tutor_email">E-mail (opcional)</label>
            <input id="quick_tutor_email" type="email" autocomplete="email" data-sale-customer-tutor-email>
          </div>
          <div class="field">
            <label for="quick_patient_name">Nome do pet</label>
            <input id="quick_patient_name" data-sale-customer-patient-name>
          </div>
          <div class="field">
            <label for="quick_patient_species">Espécie (opcional)</label>
            <input id="quick_patient_species" placeholder="Ex.: Canino ou Felino" data-sale-customer-patient-species>
          </div>
          <div class="field">
            <label for="quick_patient_breed">Raça (opcional)</label>
            <input id="quick_patient_breed" data-sale-customer-patient-breed>
          </div>
          <div class="field">
            <label for="quick_patient_weight">Peso em kg (opcional)</label>
            <input id="quick_patient_weight" type="number" min="0.01" step="0.01" inputmode="decimal" data-sale-customer-patient-weight>
          </div>
        </div>
        <div class="sale-quick-customer-actions">
          <button type="button" data-sale-customer-submit>Salvar e selecionar no PDV</button>
          <div class="lookup-status" data-sale-customer-status aria-live="polite"></div>
        </div>
      </details>
    </div>
  @endif
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

  @if($quickMode)
    <div class="field full">
      <section class="sale-quick-services" aria-labelledby="sale-quick-services-title">
        <div class="sale-quick-services-header">
          <div>
            <h2 id="sale-quick-services-title">Banho e tosa</h2>
            <p>Escolha o preco praticado e adicione o servico ao carrinho.</p>
          </div>
          @can('petshop-services.manage')
            <a class="button secondary" href="{{ route('petshop-services.create') }}" target="_blank" rel="noopener">Novo servico</a>
          @endcan
        </div>

        @if($petShopServices->isEmpty())
          <div class="alert warning" data-sale-quick-empty-catalog>
            Nenhum servico ativo. Cadastre ou ative Banho e Tosa para usar os atalhos do PDV.
          </div>
        @else
          <div class="sale-quick-service-grid">
            @foreach($petShopServices as $petShopService)
              @php
                $prices = collect([
                  'Base' => $petShopService->base_price,
                  'Porte pequeno' => $petShopService->small_price,
                  'Porte medio' => $petShopService->medium_price,
                  'Porte grande' => $petShopService->large_price,
                  'Porte gigante' => $petShopService->giant_price,
                ])->filter(fn ($price) => $price !== null);
              @endphp
              <article class="sale-quick-service-card" data-sale-quick-card data-clinic-id="{{ $petShopService->clinic_id }}">
                <div>
                  <strong>{{ $petShopService->name }}</strong>
                  <span>{{ $petShopService->category ?: 'Servico PetShop' }}</span>
                  @if($petShopService->duration_minutes)
                    <small>{{ $petShopService->duration_minutes }} min</small>
                  @endif
                </div>
                <label>
                  <span>Preco</span>
                  <select data-sale-quick-price>
                    @foreach($prices as $label => $price)
                      <option value="{{ $price }}">{{ $label }} - R$ {{ number_format((float) $price, 2, ',', '.') }}</option>
                    @endforeach
                  </select>
                </label>
                <button
                  type="button"
                  data-sale-quick-item
                  data-sale-quick-type="service"
                  data-sale-quick-id="{{ $petShopService->id }}"
                  data-sale-quick-description="{{ $petShopService->name }}"
                >Adicionar</button>
              </article>
            @endforeach
          </div>
        @endif
        <div class="lookup-status" data-sale-quick-status aria-live="polite"></div>
      </section>
    </div>
  @endif

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
      <table class="{{ $quickMode ? 'sale-quick-cart' : '' }}">
        <thead>
          <tr>
            <th>Tipo</th>
            <th>Servico</th>
            <th>Produto</th>
            <th>Descricao</th>
            <th>Qtd</th>
            <th>Valor unit.</th>
            <th>Desc. item</th>
            <th>Acao</th>
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
                      data-clinic-id="{{ $petShopService->clinic_id }}"
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
                      data-clinic-id="{{ $product->clinic_id }}"
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
              <td>
                <button type="button" class="secondary" data-sale-remove-item @disabled($locked)>Remover</button>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
      @if($quickMode)
        <p class="sale-quick-cart-empty" data-sale-cart-empty>O carrinho esta vazio.</p>
      @endif
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
      @if($quickMode)
        <div class="sale-payment-shortcuts" aria-label="Forma de pagamento">
          <span>Forma de pagamento</span>
          <div>
            @foreach(['cash' => 'Dinheiro', 'pix' => 'Pix', 'debit_card' => 'Debito', 'credit_card' => 'Credito'] as $value => $label)
              <button type="button" class="secondary" data-sale-payment-shortcut="{{ $value }}" @disabled($locked)>{{ $label }}</button>
            @endforeach
          </div>
        </div>
      @endif
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
    @if($quickMode)
      <details class="sale-payment-details">
        <summary>Detalhes do pagamento</summary>
        <p class="muted">Use esta area para parcelamento, bandeira, referencia ou mais de uma forma de pagamento.</p>
    @else
      <label>Pagamentos</label>
    @endif
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
    @if($quickMode)
      </details>
    @endif
  </div>

  <div class="field full">
    <div class="actions">
      <button type="submit">Salvar</button>
      <a class="button secondary" href="{{ route('sales.index') }}">Cancelar</a>
    </div>
  </div>
</div>
