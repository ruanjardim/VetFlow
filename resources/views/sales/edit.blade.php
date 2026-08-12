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
        <form method="POST" action="{{ route('sales.payments.store', $item->id) }}" class="form-grid">
          @csrf
          <div class="field">
            <label for="payment_method">Forma</label>
            <select id="payment_method" name="method" required>
              <option value="">Selecione</option>
              <option value="cash">Dinheiro</option>
              <option value="pix">Pix</option>
              <option value="debit_card">Cartao debito</option>
              <option value="credit_card">Cartao credito</option>
              <option value="transfer">Transferencia</option>
              <option value="other">Outro</option>
            </select>
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
            <label for="payment_reference">Referencia</label>
            <input id="payment_reference" name="reference" maxlength="255">
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
