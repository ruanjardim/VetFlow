@extends('layouts.admin')
@section('title', 'PDV - VetFlow')
@section('content')
@php
  $checkoutOrder = $serviceOrderCheckout['order'] ?? null;
  $checkoutPackage = $serviceOrderCheckout['package'] ?? null;
  $checkoutQuote = $serviceOrderCheckout['quote'] ?? null;
  $quoteSource = $checkoutQuote ?? $editingQuote;
  $checkoutSource = $checkoutOrder ?? $checkoutPackage ?? $quoteSource;
  $money = fn ($value) => number_format((float) $value, 2, ',', '.');
  $selectedClinicId = (int) old('clinic_id', $checkoutSource?->clinic_id ?? auth()->user()?->clinic_id ?? ($clinics->count() === 1 ? $clinics->first()->id : 0));
  $selectedTutorId = (int) old('tutor_id', $checkoutSource?->tutor_id ?? 0);
  $selectedPatientId = (int) old('patient_id', $checkoutSource?->patient_id ?? 0);
  $initialItems = old('items', $editingQuote ? app(\App\Modules\Sales\Services\SaleQuoteService::class)->cartItems($editingQuote) : ($serviceOrderCheckout['items'] ?? []));
  $initialDiscount = old('discount_total', $checkoutOrder ? $money($checkoutOrder->discount_total) : ($quoteSource ? $money($quoteSource->discount_total) : '0,00'));
  $initialAdditions = old('additions_total', $quoteSource ? $money($quoteSource->additions_total) : '0,00');
  $initialSaleType = old('sale_type', $quoteSource?->sale_type ?? \App\Modules\Sales\Support\SaleType::DEFAULT);
  $initialDeliveryFee = old('delivery_fee', $quoteSource ? $money($quoteSource->delivery_fee) : '0,00');
  $initialDeliveryAddress = old('delivery_address', $quoteSource?->delivery_address ?? '');
  $initialNotes = old('notes', $quoteSource?->notes ?? '');
  $initialValidUntil = old('valid_until', $editingQuote?->valid_until?->toDateString() ?? $defaultQuoteValidUntil);
  $canToggleMode = ! $checkoutSource;
  $mode = $editingQuote ? 'quote' : ($canToggleMode ? old('pdv_mode', $startMode) : 'sale');
  $mode = in_array($mode, ['sale', 'quote'], true) ? $mode : 'sale';
@endphp
<header class="topbar pdv-topbar">
  <div><h1>PDV</h1><p>Venda rápida de produtos e serviços</p></div>
  <div class="actions">
    <a class="button secondary" href="{{ route('sales.index', ['status' => 'draft']) }}">Vendas suspensas</a>
    <a class="button secondary" href="{{ route('sales.quotes.index') }}">Orçamentos</a>
    <a class="button secondary" href="{{ route('sales.cash-sessions.index') }}">Caixa</a>
    <a class="button secondary" href="{{ route('sales.create', ['mode' => 'advanced']) }}">Comanda / formulário avançado</a>
  </div>
</header>
@include('shared.clinic-required-alert', ['clinics' => $clinics])
@if($checkoutOrder)
  <div class="alert success" role="status">
    Recebendo a comanda <strong>{{ $checkoutOrder->code }}</strong>
    — {{ $checkoutOrder->tutor?->name ?? 'Sem responsável' }} / {{ $checkoutOrder->patient?->name ?? 'Sem pet' }}.
    Ao concluir a venda, a comanda é finalizada automaticamente.
  </div>
@elseif($checkoutPackage)
  <div class="alert success" role="status">
    Vendendo o pacote <strong>{{ $checkoutPackage->name }}</strong> ({{ $checkoutPackage->code }})
    para {{ $checkoutPackage->patient?->name ?? 'o pet' }}. O saldo é liberado quando a venda for concluída.
  </div>
@elseif($checkoutQuote)
  <div class="alert {{ $checkoutQuote->isExpired() ? 'warning' : 'success' }}" role="status">
    Convertendo o orçamento <strong>{{ $checkoutQuote->code }}</strong>
    — {{ $checkoutQuote->tutor?->name ?? 'Consumidor não identificado' }}.
    @if($checkoutQuote->isExpired())
      Ele venceu em {{ $checkoutQuote->valid_until->format('d/m/Y') }}; os preços orçados foram mantidos, confira antes de receber.
    @else
      Os preços orçados foram mantidos. Ao concluir a venda, o orçamento fica como convertido.
    @endif
  </div>
@elseif($editingQuote)
  <div class="alert success" role="status">
    Editando o orçamento <strong>{{ $editingQuote->code }}</strong>. Salve para atualizar itens, preços e validade.
  </div>
@elseif(request()->filled('pet_package_id'))
  <div class="alert error" role="alert">O pacote informado não está aguardando pagamento.</div>
@elseif(request()->filled('service_order_id'))
  <div class="alert error" role="alert">A comanda informada não está disponível para recebimento (cancelada, inexistente ou já vendida).</div>
@elseif(request()->filled('quote_id'))
  <div class="alert error" role="alert">O orçamento informado não está aberto (convertido, cancelado, inexistente ou com venda em andamento).</div>
@endif
<form method="POST" action="{{ $editingQuote ? route('sales.quotes.update', $editingQuote->id) : route('sales.store') }}" data-pdv-form
  data-pdv-mode="{{ $mode }}"
  data-delivery-types="{{ json_encode($deliverySaleTypes) }}"
  data-search-url="{{ route('sales.quick-search') }}"
  data-lookup-url="{{ route('sales.product-lookup', ['gtin' => '__GTIN__']) }}"
  data-quick-product-url="{{ route('sales.quick-products.store') }}"
  data-product-create-url="{{ route('products.create') }}?gtin=__GTIN__&from=sales"
  data-old-items="{{ json_encode($initialItems, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}"
  data-old-payments="{{ json_encode(old('payments', []), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}"
  data-payment-methods="{{ json_encode($paymentMethods->map->toPdvArray()->values(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}"
  data-cash-sessions="{{ json_encode((object) $cashSessions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}"
  data-cash-suggested="{{ json_encode((object) $suggestedOpenings, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}"
  data-cash-open-url="{{ route('sales.cash-sessions.store') }}"
  data-cash-index-url="{{ route('sales.cash-sessions.index') }}"
  data-user-clinic-id="{{ auth()->user()?->clinic_id }}"
  data-initial-scan="{{ request('scan', '') }}">
  @csrf
  @if($editingQuote)
    @method('PUT')
  @endif
  <input type="hidden" name="source" value="pdv">
  <input type="hidden" name="pdv_mode" value="{{ $mode }}" data-pdv-mode-input>
  @if($checkoutOrder)
    <input type="hidden" name="service_order_id" value="{{ $checkoutOrder->id }}">
  @endif
  @if($checkoutPackage)
    <input type="hidden" name="pet_package_id" value="{{ $checkoutPackage->id }}">
  @endif
  @if($checkoutQuote)
    <input type="hidden" name="sale_quote_id" value="{{ $checkoutQuote->id }}">
  @endif
  <input type="hidden" name="pdv_checkout" value="1">
  <input type="hidden" name="status" value="draft" data-pdv-status>
  <div class="pdv-shell">
    <section class="pdv-main" aria-label="Itens da venda">
      @if($canToggleMode)
        <div class="pdv-mode" role="group" aria-label="Tipo de lançamento">
          <button type="button" class="{{ $mode === 'sale' ? 'is-active' : '' }}" aria-pressed="{{ $mode === 'sale' ? 'true' : 'false' }}" data-pdv-mode-button="sale">Venda</button>
          <button type="button" class="{{ $mode === 'quote' ? 'is-active' : '' }}" aria-pressed="{{ $mode === 'quote' ? 'true' : 'false' }}" data-pdv-mode-button="quote">Orçamento</button>
        </div>
      @endif
      @if(auth()->user()?->clinic_id === null)
        <div class="field">
          <label for="pdv_clinic">Clínica</label>
          <select id="pdv_clinic" name="clinic_id" data-pdv-clinic required>
            <option value="">Selecione uma clínica</option>
            @foreach($clinics as $clinic)
              <option value="{{ $clinic->id }}" @selected($selectedClinicId === $clinic->id)>{{ $clinic->trade_name ?? $clinic->corporate_name }}</option>
            @endforeach
          </select>
        </div>
      @endif
      <div class="pdv-search-panel">
        <label for="pdv_search">Buscar ou ler código de barras <kbd>F2</kbd></label>
        <div class="pdv-search-row">
          <input id="pdv_search" type="search" autocomplete="off" placeholder="Nome, SKU, EAN/GTIN ou código de barras" data-pdv-search autofocus>
          <button type="button" class="secondary" data-pdv-search-button>Buscar</button>
        </div>
        <div class="pdv-search-results" role="listbox" aria-label="Resultados da busca" data-pdv-results hidden></div>
        <p class="lookup-status" role="status" aria-live="polite" data-pdv-lookup-status></p>
        <a class="button secondary" target="_blank" rel="noopener" data-pdv-create-product hidden>Cadastrar produto</a>
      </div>
      <details class="pdv-customer" data-pdv-customer @if($selectedTutorId || $quoteSource) open @endif>
        <summary data-pdv-customer-summary>Consumidor não identificado · Identificar cliente <kbd>F4</kbd></summary>
        <div class="pdv-customer-fields">
          <div class="field">
            <label for="pdv_tutor">Responsável</label>
            <select id="pdv_tutor" name="tutor_id" data-pdv-tutor>
              <option value="">Consumidor não identificado</option>
              @foreach($tutors as $tutor)
                <option value="{{ $tutor->id }}" data-clinic-id="{{ $tutor->clinic_id }}" data-address="{{ $tutor->fullAddress() }}" @selected($selectedTutorId === $tutor->id)>{{ $tutor->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="field">
            <label for="pdv_patient">Pet (opcional)</label>
            <select id="pdv_patient" name="patient_id" data-pdv-patient>
              <option value="">Sem pet</option>
              @foreach($patients as $patient)
                <option value="{{ $patient->id }}" data-clinic-id="{{ $patient->clinic_id }}" @selected($selectedPatientId === $patient->id)>{{ $patient->name }}</option>
              @endforeach
            </select>
          </div>
        </div>
      </details>
      <div class="pdv-sale-type">
        <div class="field">
          <label for="pdv_sale_type">Tipo de venda</label>
          <select id="pdv_sale_type" name="sale_type" data-pdv-sale-type>
            @foreach($saleTypes as $value => $label)
              <option value="{{ $value }}" @selected($initialSaleType === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="field" data-pdv-delivery hidden>
          <label for="pdv_delivery_address">Endereço de entrega</label>
          <textarea id="pdv_delivery_address" name="delivery_address" rows="2" maxlength="500" placeholder="Rua, número, complemento, bairro, cidade" data-pdv-delivery-address>{{ $initialDeliveryAddress }}</textarea>
        </div>
      </div>
      <div class="pdv-cart-heading">
        <div><h2>Carrinho</h2><span data-pdv-item-count>0 itens</span></div>
        <button type="button" class="secondary" data-pdv-add-custom>+ Item avulso</button>
      </div>
      <div class="pdv-cart" data-pdv-cart>
        <p class="pdv-empty" data-pdv-empty>Leia um código ou busque um item para começar.</p>
      </div>
      <details class="pdv-notes" @if($initialNotes !== '') open @endif>
        <summary>Observações</summary>
        <textarea id="pdv_notes" name="notes" rows="3" maxlength="2000" aria-label="Observações" placeholder="Aparecem no comprovante e no orçamento impresso">{{ $initialNotes }}</textarea>
      </details>
    </section>
    <aside class="pdv-summary" aria-label="Resumo">
      <div class="pdv-cash" data-pdv-cash data-pdv-sale-only @if($mode === 'quote') hidden @endif>
        <span data-pdv-cash-label>Caixa</span>
        <a href="{{ route('sales.cash-sessions.index') }}" data-pdv-cash-link>Ver caixa</a>
        <button type="button" class="secondary" data-pdv-cash-open hidden>Abrir caixa</button>
      </div>
      <div class="pdv-summary-head"><span data-pdv-summary-label>{{ $mode === 'quote' ? 'Orçamento em andamento' : 'Venda em andamento' }}</span><strong data-pdv-total>R$ 0,00</strong></div>
      <div class="pdv-summary-lines">
        <div><span>Subtotal</span><strong data-pdv-subtotal>R$ 0,00</strong></div>
        <div><span>Desconto dos itens</span><strong data-pdv-item-discounts>R$ 0,00</strong></div>
        <div class="field"><label for="pdv_discount">Desconto na venda <kbd>F6</kbd></label><input id="pdv_discount" name="discount_total" type="text" inputmode="decimal" value="{{ $initialDiscount }}" data-pdv-discount></div>
        <div class="field"><label for="pdv_additions">Acréscimos</label><input id="pdv_additions" name="additions_total" type="text" inputmode="decimal" value="{{ $initialAdditions }}" data-pdv-additions></div>
        <div class="field" data-pdv-delivery-fee-field hidden><label for="pdv_delivery_fee">Taxa de entrega</label><input id="pdv_delivery_fee" name="delivery_fee" type="text" inputmode="decimal" value="{{ $initialDeliveryFee }}" data-pdv-delivery-fee></div>
        <div class="field" data-pdv-quote-only @if($mode !== 'quote') hidden @endif><label for="pdv_valid_until">Orçamento válido até</label><input id="pdv_valid_until" name="valid_until" type="date" value="{{ $initialValidUntil }}" data-pdv-valid-until @disabled($mode !== 'quote')></div>
        <div data-pdv-sale-only @if($mode === 'quote') hidden @endif><span>Recebido</span><strong data-pdv-paid>R$ 0,00</strong></div>
        <div data-pdv-sale-only @if($mode === 'quote') hidden @endif><span data-pdv-balance-label>Falta</span><strong data-pdv-balance>R$ 0,00</strong></div>
      </div>
      @unless($editingQuote)
        <button type="button" class="pdv-receive-button" data-pdv-open-payment data-pdv-sale-only @if($mode === 'quote') hidden @endif>Receber / finalizar <kbd>F8</kbd></button>
        <button type="submit" class="secondary pdv-suspend-button" data-pdv-suspend data-pdv-sale-only @if($mode === 'quote') hidden @endif>Suspender venda</button>
      @endunless
      <button type="submit" class="pdv-receive-button" data-pdv-save-quote data-pdv-quote-only
        @unless($editingQuote) formaction="{{ route('sales.quotes.store') }}" @endunless
        @if($mode !== 'quote') hidden @endif>{{ $editingQuote ? 'Salvar alterações do orçamento' : 'Salvar orçamento' }}</button>
      @if($editingQuote)
        <a class="button secondary pdv-suspend-button" href="{{ route('sales.quotes.show', $editingQuote->id) }}">Voltar ao orçamento</a>
      @endif
      <p class="lookup-status" role="status" aria-live="polite" data-pdv-checkout-status></p>
      <small data-pdv-sale-only @if($mode === 'quote') hidden @endif>F2 busca · F4 cliente · F6 desconto · F8 receber · Esc fechar</small>
      <small data-pdv-quote-only @if($mode !== 'quote') hidden @endif>Orçamento não baixa estoque, não gera financeiro nem comissão.</small>
    </aside>
  </div>
  <dialog class="pdv-payment-dialog" data-pdv-payment-dialog aria-labelledby="pdv_payment_title">
    <div class="pdv-dialog-head">
      <div><h2 id="pdv_payment_title">Recebimento</h2><p>Combine formas de pagamento, se necessário.</p></div>
      <button type="button" class="secondary" data-pdv-close-payment aria-label="Fechar recebimento">Fechar</button>
    </div>
    <div class="pdv-payment-total">Total <strong data-pdv-payment-total>R$ 0,00</strong></div>
    <div class="pdv-payment-shortcuts" aria-label="Forma de pagamento rápida" data-pdv-method-shortcuts></div>
    @can('payment-methods.manage')
      <p class="pdv-payment-settings"><a href="{{ route('sales.payment-methods.index') }}" target="_blank" rel="noopener">Configurar maquininhas, taxas e prazos</a></p>
    @endcan
    <div data-pdv-payments></div>
    <button type="button" class="secondary" data-pdv-add-payment>+ Adicionar pagamento</button>
    <div class="pdv-payment-reconciliation">
      <span>Recebido <strong data-pdv-dialog-paid>R$ 0,00</strong></span>
      <span>Falta <strong data-pdv-dialog-balance>R$ 0,00</strong></span>
      <span>Troco <strong data-pdv-dialog-change>R$ 0,00</strong></span>
    </div>
    <p class="lookup-status" role="status" aria-live="polite" data-pdv-payment-status></p>
    <button type="button" class="pdv-finish-button" data-pdv-finish>Finalizar venda <kbd>F10</kbd></button>
  </dialog>
  <dialog class="pdv-payment-dialog pdv-cash-dialog" data-pdv-cash-dialog aria-labelledby="pdv_cash_title">
    <div class="pdv-dialog-head">
      <div><h2 id="pdv_cash_title">Abrir caixa</h2><p>Para receber, abra o seu caixa com o dinheiro que já está na gaveta.</p></div>
      <button type="button" class="secondary" data-pdv-close-cash aria-label="Fechar abertura de caixa">Fechar</button>
    </div>
    <div class="field">
      <label for="pdv_cash_opening">Fundo de troco</label>
      <input id="pdv_cash_opening" type="text" inputmode="decimal" placeholder="0,00" data-pdv-cash-opening>
      <small class="muted">Sugerido: o que ficou na gaveta no último fechamento.</small>
    </div>
    <p class="lookup-status" role="status" aria-live="polite" data-pdv-cash-status></p>
    <button type="button" class="pdv-finish-button" data-pdv-cash-confirm>Abrir caixa</button>
  </dialog>
  <dialog class="pdv-payment-dialog pdv-quick-product-dialog" data-pdv-quick-product-dialog aria-labelledby="pdv_quick_product_title">
    <div class="pdv-dialog-head">
      <div><h2 id="pdv_quick_product_title">Cadastro rápido</h2><p>Salve o produto e adicione ao carrinho sem sair do PDV.</p></div>
      <button type="button" class="secondary" data-pdv-close-quick-product aria-label="Fechar cadastro rápido">Fechar</button>
    </div>
    <div class="pdv-quick-product-code">Código de barras <strong data-pdv-quick-product-code></strong></div>
    <div class="pdv-quick-product-grid">
      <div class="field full"><label for="pdv_quick_name">Nome do produto</label><input id="pdv_quick_name" maxlength="255" data-pdv-quick-product-name></div>
      <div class="field"><label for="pdv_quick_price">Preço de venda</label><input id="pdv_quick_price" inputmode="decimal" placeholder="0,00" data-pdv-quick-product-price></div>
      <div class="field"><label for="pdv_quick_stock">Estoque inicial</label><input id="pdv_quick_stock" inputmode="decimal" value="1" data-pdv-quick-product-stock></div>
      <div class="field"><label for="pdv_quick_unit">Unidade</label><select id="pdv_quick_unit" data-pdv-quick-product-unit><option value="un">Unidade</option><option value="kg">Quilograma</option><option value="g">Grama</option><option value="pct">Pacote</option><option value="cx">Caixa</option></select></div>
      <div class="field"><label for="pdv_quick_cost">Custo (opcional)</label><input id="pdv_quick_cost" inputmode="decimal" placeholder="0,00" data-pdv-quick-product-cost></div>
    </div>
    <p class="lookup-status" role="status" aria-live="polite" data-pdv-quick-product-status></p>
    <button type="button" class="pdv-finish-button" data-pdv-save-quick-product>Salvar e adicionar ao carrinho</button>
  </dialog>
</form>
@endsection
