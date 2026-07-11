@extends('layouts.admin')

@section('title', 'Devolucao '.$sale->code.' - VetFlow')

@section('content')
  @php
    $money = fn ($value) => 'R$ '.number_format((float) $value, 2, ',', '.');
    $quantity = fn ($value) => number_format((float) $value, 3, ',', '.');
    $paymentMethods = [
      'cash' => 'Dinheiro',
      'pix' => 'Pix',
      'debit_card' => 'Cartao debito',
      'credit_card' => 'Cartao credito',
      'transfer' => 'Transferencia',
      'other' => 'Outro',
    ];
  @endphp

  <header class="topbar">
    <div>
      <h1>Devolucao / estorno</h1>
      <p>{{ $sale->code }} - {{ optional($sale->sold_at)->format('d/m/Y H:i') }}</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('sales.edit', $sale->id) }}">Voltar para venda</a>
      <a class="button secondary" href="{{ route('sales.receipt', $sale->id) }}">Comprovante</a>
    </div>
  </header>

  @if($sale->status !== 'completed')
    <div class="alert error">Esta venda nao esta concluida e nao pode receber devolucao.</div>
  @endif

  <div class="panel">
    <div class="panel-heading">
      <div>
        <h2>Itens vendidos</h2>
        <p>Informe apenas a quantidade que esta voltando para estoque ou sendo estornada.</p>
      </div>
    </div>
    <div class="panel-body">
      <form method="POST" action="{{ route('sales.returns.store', $sale->id) }}">
        @csrf

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Item</th>
                <th>Vendido</th>
                <th>Ja devolvido</th>
                <th>Disponivel</th>
                <th>Valor unit.</th>
                <th>Devolver agora</th>
              </tr>
            </thead>
            <tbody>
              @foreach($sale->items as $item)
                @php
                  $available = max(0, (float) $item->quantity - (float) $item->returned_quantity);
                  $lineTotal = (float) $item->net_total > 0 ? (float) $item->net_total : (float) $item->total;
                  $unitValue = (float) $item->quantity > 0 ? ($lineTotal / (float) $item->quantity) : 0;
                @endphp
                <tr>
                  <td>
                    <strong>{{ $item->description }}</strong>
                    <div class="muted">
                      {{ $item->type === 'service' ? 'Servico' : ($item->type === 'product' ? 'Produto' : 'Avulso') }}
                      @if($item->product?->unit)
                        - {{ $item->product->unit }}
                      @endif
                    </div>
                  </td>
                  <td>{{ $quantity($item->quantity) }}</td>
                  <td>{{ $quantity($item->returned_quantity) }}</td>
                  <td>{{ $quantity($available) }}</td>
                  <td>{{ $money($unitValue) }}</td>
                  <td>
                    <input
                      name="items[{{ $item->id }}][quantity]"
                      type="number"
                      step="0.001"
                      min="0"
                      max="{{ $available }}"
                      value="0"
                      @disabled($sale->status !== 'completed' || $available <= 0)
                    >
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        <div class="form-grid nested-panel">
          <div class="field">
            <label for="refund_method">Forma do estorno</label>
            <select id="refund_method" name="refund_method" @disabled($sale->status !== 'completed')>
              @foreach($paymentMethods as $value => $label)
                <option value="{{ $value }}" @selected($value === 'cash')>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="field">
            <label for="refund_amount">Valor estornado</label>
            <input id="refund_amount" name="refund_amount" type="text" inputmode="decimal" placeholder="0,00" data-money-input @readonly($sale->status !== 'completed')>
          </div>
          <div class="field">
            <label for="reference">Referencia</label>
            <input id="reference" name="reference" placeholder="NSU, Pix, autorizacao..." @readonly($sale->status !== 'completed')>
          </div>
          <div class="field full">
            <label for="reason">Motivo</label>
            <textarea id="reason" name="reason" @readonly($sale->status !== 'completed')></textarea>
          </div>
          <div class="field full">
            <div class="actions">
              <button type="submit" @disabled($sale->status !== 'completed')>Registrar devolucao</button>
              <a class="button secondary" href="{{ route('sales.edit', $sale->id) }}">Cancelar</a>
            </div>
          </div>
        </div>
      </form>
    </div>
  </div>
@endsection
