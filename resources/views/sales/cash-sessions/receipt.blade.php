@extends('layouts.admin')

@section('title', 'Fechamento '.$session->code.' - VetFlow')

@section('content')
  @php
    $money = fn ($value) => 'R$ '.number_format((float) $value, 2, ',', '.');
    $signedMoney = fn ($value) => ((float) $value < 0 ? '−' : ((float) $value > 0 ? '+' : '')).'R$ '.number_format(abs((float) $value), 2, ',', '.');
    $cash = $figures['cash'];
    $methods = collect($figures['methods'] ?? []);
    $paperSize = in_array($receiptPrinter?->paper_size, ['58mm', '80mm'], true)
      ? $receiptPrinter->paper_size
      : '80mm';
  @endphp

  <header class="topbar receipt-actions">
    <div>
      <h1>Fechamento de caixa</h1>
      <p>{{ $session->code }} · fechado em {{ $session->closed_at?->format('d/m/Y H:i') }}</p>
    </div>
    <div class="actions">
      <button type="button" data-print-page>Imprimir fechamento</button>
      <a class="button secondary" href="{{ route('sales.cash-sessions.show', $session->id) }}">Voltar ao caixa</a>
    </div>
  </header>

  <div class="panel receipt-card">
    <div class="panel-heading">
      <div>
        <h2>Prévia do relatório gerencial</h2>
        <p>A impressão usa a bobina {{ str_replace('mm', ' mm', $paperSize) }}{{ $receiptPrinter ? ' configurada em '.$receiptPrinter->name : '' }}.</p>
      </div>
    </div>
    <div class="panel-body">
      <div class="receipt-info-grid">
        <div><span>Estabelecimento</span><strong>{{ $session->clinic?->trade_name ?? $session->clinic?->corporate_name ?? '-' }}</strong></div>
        <div><span>Operador</span><strong>{{ $session->user?->name ?? '-' }}</strong></div>
        <div><span>Aberto em</span><strong>{{ $session->opened_at?->format('d/m/Y H:i') }}</strong></div>
        <div><span>Fechado em</span><strong>{{ $session->closed_at?->format('d/m/Y H:i') }}</strong></div>
        <div><span>Status</span><strong>{{ $session->statusLabel() }}</strong></div>
        <div><span>Responsável pelo fechamento</span><strong>{{ $session->closedBy?->name ?? ($session->wasAutoClosed() ? 'Automático' : '-') }}</strong></div>
      </div>

      <div class="table-wrap receipt-table">
        <table>
          <thead>
            <tr><th>Forma</th><th>Esperado</th><th>Contado</th><th>Diferença</th></tr>
          </thead>
          <tbody>
            <tr>
              <td>Dinheiro</td>
              <td>{{ $money($cash['expected']) }}</td>
              <td>{{ $money($cash['counted'] ?? $session->counted_cash) }}</td>
              <td>{{ $signedMoney($cash['difference'] ?? 0) }}</td>
            </tr>
            @foreach($methods as $method)
              <tr>
                <td>{{ $method['label'] }}</td>
                <td>{{ $money($method['expected']) }}</td>
                <td>{{ $money($method['counted'] ?? $method['expected']) }}</td>
                <td>{{ $signedMoney($method['difference'] ?? 0) }}</td>
              </tr>
            @endforeach
            <tr>
              <td><strong>Total</strong></td>
              <td><strong>{{ $money($session->expected_total) }}</strong></td>
              <td><strong>{{ $money($session->counted_total) }}</strong></td>
              <td><strong>{{ $signedMoney($session->difference_total) }}</strong></td>
            </tr>
          </tbody>
        </table>
      </div>

      @if($session->closing_notes)
        <div class="receipt-section">
          <h2>Justificativa do fechamento</h2>
          <p>{{ $session->closing_notes }}</p>
        </div>
      @endif

      <div class="alert info">
        Este é um relatório interno de conferência. Ele não substitui NFC-e, NFS-e ou qualquer documento fiscal.
      </div>
    </div>
  </div>

  @include('sales.cash-sessions.partials.thermal-closing', [
    'paperSize' => $paperSize,
    'printer' => $receiptPrinter,
  ])
@endsection
