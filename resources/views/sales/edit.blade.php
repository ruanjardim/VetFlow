@extends('layouts.admin')

@section('title', 'Editar venda - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Editar venda</h1>
      <p>{{ $item->code }}</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('sales.receipt', $item->id) }}">Comprovante</a>
      @if($item->status === 'completed')
        <a class="button secondary" href="{{ route('sales.returns.create', $item->id) }}">Devolver</a>
        <form class="inline" action="{{ route('sales.cancel', $item->id) }}" method="POST">
          @csrf
          @method('PATCH')
          <button class="danger" type="submit" data-confirm="Cancelar esta venda e estornar estoque/financeiro?">Cancelar venda</button>
        </form>
      @endif
      <a class="button secondary" href="{{ route('sales.cashier') }}">Caixa do dia</a>
    </div>
  </header>

  @if($item->status === 'cancelled')
    <div class="alert error">
      Venda cancelada em {{ optional($item->cancelled_at)->format('d/m/Y H:i') }}.
      @if($item->cancellation_reason)
        Motivo: {{ $item->cancellation_reason }}
      @endif
    </div>
  @elseif($item->status === 'returned')
    <div class="alert success">Venda totalmente devolvida e estornada.</div>
  @elseif((float) $item->return_total > 0)
    <div class="alert success">Esta venda possui devolucao parcial de R$ {{ number_format((float) $item->return_total, 2, ',', '.') }}.</div>
  @endif

  <div class="panel">
    <div class="panel-body">
      <form method="POST" action="{{ route('sales.update', $item->id) }}">
        @csrf
        @method('PUT')
        @include('sales.form', ['sale' => $item])
      </form>
    </div>
  </div>

  @if($item->status === 'completed' && (float) $item->return_total <= 0 && (float) $item->paid_total < (float) $item->total)
    <div class="panel nested-panel">
      <div class="panel-heading">
        <div>
          <h2>Registrar recebimento</h2>
          <p>Saldo pendente: R$ {{ number_format((float) $item->total - (float) $item->paid_total, 2, ',', '.') }}.</p>
        </div>
      </div>
      <div class="panel-body">
        @unless($receiptCashSession)
          <div class="alert warning">
            Para registrar o recebimento em dinheiro, cartão ou Pix, abra o seu caixa em <a href="{{ route('sales.cash-sessions.index', ['clinic_id' => $item->clinic_id]) }}">Caixa</a>.
          </div>
        @endunless
        @if($item->tutor_id)
          <p class="muted">Cliente {{ $item->tutor?->name }}. <a href="{{ route('sales.customer-balances.show', $item->tutor_id) }}">Ver saldo e receber várias vendas de uma vez</a>.</p>
        @endif
        <form method="POST" action="{{ route('sales.payments.store', $item->id) }}" class="form-grid" data-receipt-payment-form>
          @csrf
          <div class="field">
            <label for="payment_method">Forma</label>
            <select id="payment_method" name="payment_method_id" data-receipt-payment-method required>
              <option value="">Selecione</option>
              @if($receiptCustomerCredit > 0)
                <option value="customer_credit" data-kind="customer_credit" data-max-installments="1" data-requires-reference="0" @selected(old('payment_method_id') === 'customer_credit')>Crédito do cliente (R$ {{ number_format($receiptCustomerCredit, 2, ',', '.') }})</option>
              @endif
              @foreach($receiptPaymentMethods as $option)
                <option value="{{ $option->id }}" data-kind="{{ $option->kind }}" data-max-installments="{{ $option->maxInstallments() }}" data-requires-reference="{{ $option->requires_reference ? '1' : '0' }}" @selected((int) old('payment_method_id') === $option->id)>{{ $option->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="field" data-receipt-installments-field hidden>
            <label for="payment_installments">Parcelas</label>
            <input id="payment_installments" name="installments" type="number" min="1" max="1" value="{{ old('installments', 1) }}" data-receipt-installments>
          </div>
          <div class="field">
            <label for="payment_amount">Valor recebido</label>
            <input id="payment_amount" name="amount" type="text" inputmode="decimal" placeholder="0,00" data-money-input required>
          </div>
          <div class="field">
            <label for="payment_paid_at">Recebido em</label>
            <input id="payment_paid_at" name="paid_at" type="datetime-local" value="{{ now()->format('Y-m-d\\TH:i') }}">
          </div>
          <div class="field">
            <label for="payment_reference" data-receipt-reference-label>NSU / referência</label>
            <input id="payment_reference" name="reference" maxlength="255" value="{{ old('reference') }}">
          </div>
          <div class="field full">
            <label for="payment_notes">Observacoes</label>
            <textarea id="payment_notes" name="notes"></textarea>
          </div>
          <div class="field full">
            <button type="submit">Registrar recebimento</button>
          </div>
        </form>
      </div>
    </div>
  @endif
@endsection
