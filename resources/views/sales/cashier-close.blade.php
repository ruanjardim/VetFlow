@extends('layouts.admin')

@section('title', 'Fechamento de caixa - VetFlow')

@section('content')
  @php
    $period = $summary['period'];
    $stats = $summary['stats'];
    $paymentReconciliation = $summary['payment_reconciliation'];
    $money = fn ($value) => 'R$ '.number_format((float) $value, 2, ',', '.');
  @endphp

  <header class="topbar">
    <div>
      <h1>Fechamento de caixa</h1>
      <p>Conferencia operacional do periodo {{ $period['label'] }}.</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('sales.cashier', ['from' => $period['from'], 'to' => $period['to']]) }}">Voltar ao caixa</a>
      <a class="button secondary" href="{{ route('sales.index') }}">Ver vendas</a>
    </div>
  </header>

  <div class="grid stats inventory-lot-stats">
    <div class="stat">
      <span>Dinheiro esperado</span>
      <strong>{{ $money($stats['cash_drawer']) }}</strong>
    </div>
    <div class="stat">
      <span>Recebido bruto</span>
      <strong>{{ $money($stats['received']) }}</strong>
    </div>
    <div class="stat">
      <span>Estornos</span>
      <strong>{{ $money($stats['refunds']) }}</strong>
    </div>
    <div class="stat">
      <span>Total esperado para conferencia</span>
      <strong>{{ $money($stats['reconciled_total']) }}</strong>
    </div>
  </div>

  <div class="panel">
    <div class="panel-heading">
      <div>
        <h2>Conferencia</h2>
        <p>Informe o valor conferido em cada meio. O VetFlow compara com recebimentos, estornos e troco do periodo.</p>
      </div>
    </div>
    <div class="panel-body">
      <form method="POST" action="{{ route('sales.cashier.close.store') }}" class="form-grid">
        @csrf
        <input type="hidden" name="period_from" value="{{ $period['from'] }}">
        <input type="hidden" name="period_to" value="{{ $period['to'] }}">

        @foreach($paymentReconciliation as $method)
          <div class="field">
            <label for="counted_method_{{ $method['method'] }}">{{ $method['label'] }} conferido</label>
            <input
              id="counted_method_{{ $method['method'] }}"
              name="counted_methods[{{ $method['method'] }}]"
              type="text"
              inputmode="decimal"
              value="{{ old('counted_methods.'.$method['method'], number_format((float) $method['expected'], 2, ',', '.')) }}"
              data-money-input
              required
            >
            <div class="field-hint">
              Esperado: {{ $money($method['expected']) }}
              @if((float) $method['refunds'] > 0)
                · Estornos: {{ $money($method['refunds']) }}
              @endif
              @if((float) $method['change'] > 0)
                · Troco: {{ $money($method['change']) }}
              @endif
            </div>
          </div>
        @endforeach
        <div class="field full">
          <label for="notes">Observacoes</label>
          <textarea id="notes" name="notes"></textarea>
        </div>
        <div class="field full">
          <div class="actions">
            <button type="submit">Fechar caixa</button>
            <a class="button secondary" href="{{ route('sales.cashier', ['from' => $period['from'], 'to' => $period['to']]) }}">Cancelar</a>
          </div>
        </div>
      </form>
    </div>
  </div>

  <div class="panel nested-panel">
    <div class="panel-heading">
      <div>
        <h2>Ultimos fechamentos</h2>
        <p>Historico de conferencias salvas.</p>
      </div>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Periodo</th>
            <th>Total esperado</th>
            <th>Total conferido</th>
            <th>Diferenca total</th>
            <th>Por forma</th>
            <th>Status</th>
            <th>Fechado em</th>
          </tr>
        </thead>
        <tbody>
          @forelse($summary['closures'] as $closure)
            <tr>
              <td>{{ $closure->period_from->format('d/m/Y') }} a {{ $closure->period_to->format('d/m/Y') }}</td>
              <td>{{ $money($closure->expected_total) }}</td>
              <td>{{ $money($closure->counted_total) }}</td>
              <td>{{ $money($closure->total_difference) }}</td>
              <td>
                @forelse(data_get($closure->metadata, 'payment_reconciliation', []) as $method)
                  @if(abs((float) ($method['expected'] ?? 0)) >= 0.01 || abs((float) ($method['counted'] ?? 0)) >= 0.01 || abs((float) ($method['difference'] ?? 0)) >= 0.01)
                    <div>
                      <strong>{{ $method['label'] ?? $method['method'] }}:</strong>
                      {{ $money($method['counted'] ?? 0) }}
                      @if(abs((float) ($method['difference'] ?? 0)) >= 0.01)
                        <span class="badge warning">{{ $money($method['difference']) }}</span>
                      @endif
                    </div>
                  @endif
                @empty
                  <span class="muted">Fechamento anterior</span>
                @endforelse
              </td>
              <td>
                @if($closure->status === 'balanced')
                  <span class="badge success">Conferido</span>
                @else
                  <span class="badge warning">Diferenca</span>
                @endif
              </td>
              <td>{{ $closure->closed_at->format('d/m/Y H:i') }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="muted">Nenhum fechamento registrado ainda.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection
