@extends('layouts.admin')

@section('title', 'Fechamento de caixa - VetFlow')

@section('content')
  @php
    $period = $summary['period'];
    $stats = $summary['stats'];
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
      <span>Recebido liquido</span>
      <strong>{{ $money($stats['net_received']) }}</strong>
    </div>
  </div>

  <div class="panel">
    <div class="panel-heading">
      <div>
        <h2>Conferencia</h2>
        <p>Informe o dinheiro contado na gaveta e, se desejar, o total conferido de todos os meios.</p>
      </div>
    </div>
    <div class="panel-body">
      <form method="POST" action="{{ route('sales.cashier.close.store') }}" class="form-grid">
        @csrf
        <input type="hidden" name="period_from" value="{{ $period['from'] }}">
        <input type="hidden" name="period_to" value="{{ $period['to'] }}">

        <div class="field">
          <label for="counted_cash">Dinheiro contado</label>
          <input id="counted_cash" name="counted_cash" type="text" inputmode="decimal" value="{{ number_format((float) $stats['cash_drawer'], 2, ',', '.') }}" data-money-input required>
        </div>
        <div class="field">
          <label for="counted_total">Total conferido</label>
          <input id="counted_total" name="counted_total" type="text" inputmode="decimal" value="{{ number_format((float) $stats['net_received'], 2, ',', '.') }}" data-money-input>
        </div>
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
            <th>Dinheiro esperado</th>
            <th>Dinheiro contado</th>
            <th>Diferenca</th>
            <th>Status</th>
            <th>Fechado em</th>
          </tr>
        </thead>
        <tbody>
          @forelse($summary['closures'] as $closure)
            <tr>
              <td>{{ $closure->period_from->format('d/m/Y') }} a {{ $closure->period_to->format('d/m/Y') }}</td>
              <td>{{ $money($closure->expected_cash) }}</td>
              <td>{{ $money($closure->counted_cash) }}</td>
              <td>{{ $money($closure->cash_difference) }}</td>
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
              <td colspan="6" class="muted">Nenhum fechamento registrado ainda.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection
