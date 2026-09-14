@extends('layouts.admin')
@section('title', 'PDV - VetFlow')
@section('content')
@php
  $selectedClinicId = (int) old('clinic_id', auth()->user()?->clinic_id ?? ($clinics->count() === 1 ? $clinics->first()->id : 0));
@endphp
<header class="topbar pdv-topbar">
  <div><h1>PDV</h1><p>Venda rápida de produtos e serviços</p></div>
  <div class="actions">
    <a class="button secondary" href="{{ route('sales.index', ['status' => 'draft']) }}">Vendas suspensas</a>
    <a class="button secondary" href="{{ route('sales.cashier') }}">Caixa do dia</a>
    <a class="button secondary" href="{{ route('sales.create', ['mode' => 'advanced']) }}">Comanda / formulário avançado</a>
  </div>
</header>
@include('shared.clinic-required-alert', ['clinics' => $clinics])
<form method="POST" action="{{ route('sales.store') }}" data-pdv-form
  data-search-url="{{ route('sales.quick-search') }}"
  data-lookup-url="{{ route('sales.product-lookup', ['gtin' => '__GTIN__']) }}"
  data-product-create-url="{{ route('products.create') }}?gtin=__GTIN__&from=sales"
  data-old-items="{{ json_encode(old('items', []), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}"
  data-old-payments="{{ json_encode(old('payments', []), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}"
  data-initial-scan="{{ request('scan', '') }}">
  @csrf
  <input type="hidden" name="source" value="pdv">
  <input type="hidden" name="pdv_checkout" value="1">
  <input type="hidden" name="status" value="draft" data-pdv-status>
  <div class="pdv-shell">
    <section class="pdv-main" aria-label="Itens da venda">
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
      <details class="pdv-customer" data-pdv-customer>
        <summary data-pdv-customer-summary>Consumidor não identificado · Identificar cliente <kbd>F4</kbd></summary>
        <div class="pdv-customer-fields">
          <div class="field">
            <label for="pdv_tutor">Responsável</label>
            <select id="pdv_tutor" name="tutor_id" data-pdv-tutor>
              <option value="">Consumidor não identificado</option>
              @foreach($tutors as $tutor)
                <option value="{{ $tutor->id }}" data-clinic-id="{{ $tutor->clinic_id }}" @selected((int) old('tutor_id', 0) === $tutor->id)>{{ $tutor->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="field">
            <label for="pdv_patient">Pet (opcional)</label>
            <select id="pdv_patient" name="patient_id" data-pdv-patient>
              <option value="">Sem pet</option>
              @foreach($patients as $patient)
                <option value="{{ $patient->id }}" data-clinic-id="{{ $patient->clinic_id }}" @selected((int) old('patient_id', 0) === $patient->id)>{{ $patient->name }}</option>
              @endforeach
            </select>
          </div>
        </div>
      </details>
      <div class="pdv-cart-heading">
        <div><h2>Carrinho</h2><span data-pdv-item-count>0 itens</span></div>
        <button type="button" class="secondary" data-pdv-add-custom>+ Item avulso</button>
      </div>
      <div class="pdv-cart" data-pdv-cart>
        <p class="pdv-empty" data-pdv-empty>Leia um código ou busque um item para começar.</p>
      </div>
    </section>
    <aside class="pdv-summary" aria-label="Resumo da venda">
      <div class="pdv-summary-head"><span>Venda em andamento</span><strong data-pdv-total>R$ 0,00</strong></div>
      <div class="pdv-summary-lines">
        <div><span>Subtotal</span><strong data-pdv-subtotal>R$ 0,00</strong></div>
        <div><span>Desconto dos itens</span><strong data-pdv-item-discounts>R$ 0,00</strong></div>
        <div class="field"><label for="pdv_discount">Desconto na venda <kbd>F6</kbd></label><input id="pdv_discount" name="discount_total" type="text" inputmode="decimal" value="{{ old('discount_total', '0,00') }}" data-pdv-discount></div>
        <div class="field"><label for="pdv_additions">Acréscimos</label><input id="pdv_additions" name="additions_total" type="text" inputmode="decimal" value="{{ old('additions_total', '0,00') }}" data-pdv-additions></div>
        <div><span>Recebido</span><strong data-pdv-paid>R$ 0,00</strong></div>
        <div><span data-pdv-balance-label>Falta</span><strong data-pdv-balance>R$ 0,00</strong></div>
      </div>
      <button type="button" class="pdv-receive-button" data-pdv-open-payment>Receber / finalizar <kbd>F8</kbd></button>
      <button type="submit" class="secondary pdv-suspend-button" data-pdv-suspend>Suspender venda</button>
      <p class="lookup-status" role="status" aria-live="polite" data-pdv-checkout-status></p>
      <small>F2 busca · F4 cliente · F6 desconto · F8 receber · Esc fechar</small>
    </aside>
  </div>
  <dialog class="pdv-payment-dialog" data-pdv-payment-dialog aria-labelledby="pdv_payment_title">
    <div class="pdv-dialog-head">
      <div><h2 id="pdv_payment_title">Recebimento</h2><p>Combine formas de pagamento, se necessário.</p></div>
      <button type="button" class="secondary" data-pdv-close-payment aria-label="Fechar recebimento">Fechar</button>
    </div>
    <div class="pdv-payment-total">Total <strong data-pdv-payment-total>R$ 0,00</strong></div>
    <div class="pdv-payment-shortcuts" aria-label="Forma de pagamento rápida">
      <button type="button" class="secondary" data-pdv-method="cash">Dinheiro</button>
      <button type="button" class="secondary" data-pdv-method="pix">PIX</button>
      <button type="button" class="secondary" data-pdv-method="debit_card">Débito</button>
      <button type="button" class="secondary" data-pdv-method="credit_card">Crédito</button>
    </div>
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
</form>
@endsection
