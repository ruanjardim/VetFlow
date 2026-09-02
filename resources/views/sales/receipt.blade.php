@extends('layouts.admin')

@section('title', 'Comprovante '.$sale->code.' - VetFlow')

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
    $statusLabels = [
      'draft' => 'Rascunho',
      'completed' => 'Concluida',
      'cancelled' => 'Cancelada',
      'returned' => 'Devolvida',
    ];
  @endphp

  <header class="topbar receipt-actions">
    <div>
      <h1>Comprovante</h1>
      <p>{{ $sale->code }} - {{ optional($sale->sold_at)->format('d/m/Y H:i') }}</p>
    </div>
    <div class="actions">
      <button type="button" onclick="window.print()">Imprimir</button>
      @if($sale->status === 'completed')
        <a class="button secondary" href="{{ route('sales.returns.create', $sale->id) }}">Devolver</a>
      @endif
      <a class="button secondary" href="{{ route('sales.edit', $sale->id) }}">Voltar para venda</a>
      <a class="button secondary" href="{{ route('sales.index') }}">Ver vendas</a>
    </div>
  </header>

  <div class="panel receipt-card">
    <div class="panel-body">
      <div class="receipt-header">
        <div>
          <strong>VetFlow</strong>
          <span>ERP veterinario</span>
        </div>
        <div>
          <strong>{{ $sale->code }}</strong>
          <span>{{ optional($sale->sold_at)->format('d/m/Y H:i') }}</span>
        </div>
      </div>

      <div class="receipt-info-grid">
        <div>
          <span>Clinica</span>
          <strong>{{ $sale->clinic?->trade_name ?? $sale->clinic?->corporate_name ?? '-' }}</strong>
        </div>
        <div>
          <span>Responsável</span>
          <strong>{{ $sale->tutor?->name ?? '-' }}</strong>
        </div>
        <div>
          <span>Pet</span>
          <strong>{{ $sale->patient?->name ?? '-' }}</strong>
        </div>
        <div>
          <span>Comanda</span>
          <strong>{{ $sale->serviceOrder?->code ?? '-' }}</strong>
        </div>
        <div>
          <span>Status</span>
          <strong>{{ $statusLabels[$sale->status] ?? ucfirst($sale->status) }}</strong>
        </div>
      </div>

      <div class="table-wrap receipt-table">
        <table>
          <thead>
            <tr>
              <th>Item</th>
              <th>Qtd</th>
              <th>Valor unit.</th>
              <th>Total</th>
            </tr>
          </thead>
          <tbody>
            @forelse($sale->items as $item)
              <tr>
                <td>
                  <strong>{{ $item->description }}</strong>
                  <div class="muted">{{ $item->type === 'service' ? 'Servico' : ($item->type === 'product' ? 'Produto' : 'Avulso') }}</div>
                </td>
                <td>{{ $quantity($item->quantity) }}</td>
                <td>{{ $money($item->unit_price) }}</td>
                <td>{{ $money($item->total) }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="4" class="muted">Nenhum item informado.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div class="receipt-summary">
        <div>
          <span>Subtotal</span>
          <strong>{{ $money($sale->subtotal) }}</strong>
        </div>
        <div>
          <span>Desconto</span>
          <strong>{{ $money($sale->discount_total) }}</strong>
        </div>
        <div>
          <span>Acrescimo</span>
          <strong>{{ $money($sale->additions_total) }}</strong>
        </div>
        <div>
          <span>Total</span>
          <strong>{{ $money($sale->total) }}</strong>
        </div>
        <div>
          <span>Pago</span>
          <strong>{{ $money($sale->paid_total) }}</strong>
        </div>
        <div>
          <span>Troco</span>
          <strong>{{ $money($sale->change_total) }}</strong>
        </div>
        <div>
          <span>Devolvido</span>
          <strong>{{ $money($sale->return_total) }}</strong>
        </div>
        <div>
          <span>Estornado</span>
          <strong>{{ $money($sale->refunded_total) }}</strong>
        </div>
      </div>

      <div class="receipt-section">
        <h2>Pagamentos</h2>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Forma</th>
                <th>Valor</th>
                <th>Parcelas</th>
                <th>Cartao</th>
                <th>Status</th>
                <th>Data</th>
                <th>Referencia</th>
              </tr>
            </thead>
            <tbody>
              @forelse($sale->payments as $payment)
                <tr>
                  <td>{{ $paymentMethods[$payment->method] ?? 'Outro' }}</td>
                  <td>{{ $money($payment->amount) }}</td>
                  <td>{{ $payment->installments ?? 1 }}x</td>
                  <td>{{ trim(($payment->card_brand ?: '').' '.($payment->acquirer ?: '')) ?: '-' }}</td>
                  <td>{{ $payment->status === 'paid' ? 'Recebido' : ucfirst($payment->status ?? 'pendente') }}</td>
                  <td>{{ optional($payment->paid_at)->format('d/m/Y H:i') ?: '-' }}</td>
                  <td>{{ $payment->reference ?: $payment->transaction_reference ?: '-' }}</td>
                </tr>
              @empty
                <tr>
                  <td colspan="7" class="muted">Nenhum pagamento registrado.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

      @if($sale->notes)
        <div class="receipt-section">
          <h2>Observacoes</h2>
          <p>{{ $sale->notes }}</p>
        </div>
      @endif

      @if($sale->events->isNotEmpty())
        <div class="receipt-section">
          <h2>Historico da venda</h2>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Evento</th>
                  <th>Qtd</th>
                  <th>Valor</th>
                  <th>Data</th>
                  <th>Motivo</th>
                </tr>
              </thead>
              <tbody>
                @foreach($sale->events->sortByDesc('occurred_at')->take(8) as $event)
                  <tr>
                    <td>{{ str_replace('_', ' ', ucfirst($event->event_type)) }}</td>
                    <td>{{ $event->quantity !== null ? $quantity($event->quantity) : '-' }}</td>
                    <td>{{ $event->amount !== null ? $money($event->amount) : '-' }}</td>
                    <td>{{ optional($event->occurred_at)->format('d/m/Y H:i') ?: '-' }}</td>
                    <td>{{ $event->reason ?: '-' }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      @endif
    </div>
  </div>
@endsection
