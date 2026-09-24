@extends('layouts.admin')

@section('title', 'Recebíveis de cartão - VetFlow')

@section('content')
  @php
    $period = $summary['period'];
    $stats = $summary['stats'];
    $money = fn ($value) => 'R$ '.number_format((float) $value, 2, ',', '.');
  @endphp

  <header class="topbar">
    <div>
      <h1>Recebíveis de cartão</h1>
      <p>Quanto as maquininhas devem depositar, por data prevista, já descontadas as taxas.</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('sales.payment-methods.index') }}">Formas de pagamento</a>
      <a class="button secondary" href="{{ route('sales.cashier') }}">Movimentos de caixa</a>
    </div>
  </header>

  <div class="panel">
    <div class="panel-body">
      <form method="GET" action="{{ route('sales.receivables') }}" class="form-grid">
        <div class="field">
          <label for="from">Previsto de</label>
          <input id="from" name="from" type="date" value="{{ $period['from'] }}">
        </div>
        <div class="field">
          <label for="to">Até</label>
          <input id="to" name="to" type="date" value="{{ $period['to'] }}">
        </div>
        <div class="field">
          <label for="payment_method_id">Forma de pagamento</label>
          <select id="payment_method_id" name="payment_method_id">
            <option value="">Todas as formas de cartão</option>
            @foreach($methodOptions as $option)
              <option value="{{ $option->id }}" @selected($selectedMethodId === $option->id)>
                {{ $option->name }}@if($showClinic && $option->clinic) — {{ $option->clinic->trade_name ?: $option->clinic->corporate_name }}@endif{{ $option->trashed() || ! $option->active ? ' (inativa)' : '' }}
              </option>
            @endforeach
          </select>
        </div>
        <div class="field full">
          <div class="actions">
            <button type="submit">Filtrar</button>
            <a class="button secondary" href="{{ route('sales.receivables') }}">Próximos 60 dias</a>
          </div>
        </div>
      </form>
    </div>
  </div>

  <div class="grid stats inventory-lot-stats">
    <div class="stat">
      <span>Parcelas previstas</span>
      <strong>{{ $stats['count'] }}</strong>
    </div>
    <div class="stat">
      <span>Valor bruto</span>
      <strong>{{ $money($stats['gross']) }}</strong>
    </div>
    <div class="stat">
      <span>Taxas das maquininhas</span>
      <strong>{{ $money($stats['fee']) }}</strong>
    </div>
    <div class="stat">
      <span>Líquido a receber</span>
      <strong>{{ $money($stats['net']) }}</strong>
    </div>
  </div>

  <div class="panel">
    <div class="panel-heading">
      <div>
        <h2>Por forma de pagamento</h2>
        <p>Parcelas com data prevista entre {{ \Illuminate\Support\Carbon::parse($period['from'])->format('d/m/Y') }} e {{ \Illuminate\Support\Carbon::parse($period['to'])->format('d/m/Y') }}.</p>
      </div>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Forma</th>
            <th>Parcelas</th>
            <th>Bruto</th>
            <th>Taxas</th>
            <th>Líquido</th>
          </tr>
        </thead>
        <tbody>
          @forelse($summary['by_method'] as $method)
            <tr>
              <td>{{ $method['label'] }}</td>
              <td>{{ $method['count'] }}</td>
              <td>{{ $money($method['gross']) }}</td>
              <td>{{ $money($method['fee']) }}</td>
              <td><strong>{{ $money($method['net']) }}</strong></td>
            </tr>
          @empty
            <tr>
              <td colspan="5" class="muted">Nenhum repasse previsto no período.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  @if($summary['rows']->isNotEmpty())
    <div class="panel">
      <div class="panel-heading">
        <div>
          <h2>Agenda de repasses</h2>
          <p>Uma linha por parcela. Recebimentos anteriores ao cadastro das formas de pagamento não têm data prevista e não aparecem aqui.</p>
        </div>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Previsto</th>
              <th>Venda</th>
              <th>Forma</th>
              <th>Parcela</th>
              <th>Recebido em</th>
              <th>NSU</th>
              <th>Bruto</th>
              <th>Taxa</th>
              <th>Líquido</th>
            </tr>
          </thead>
          <tbody>
            @foreach($summary['by_date'] as $date => $rows)
              @foreach($rows as $row)
                <tr>
                  <td>{{ $row['date']->format('d/m/Y') }}</td>
                  <td>
                    @if($row['sale_id'])
                      <a href="{{ route('sales.receipt', $row['sale_id']) }}">{{ $row['sale_code'] ?? '#'.$row['sale_id'] }}</a>
                    @else
                      -
                    @endif
                  </td>
                  <td>{{ $row['method_label'] }}@if($row['card_brand'])<div class="muted">{{ $row['card_brand'] }}</div>@endif</td>
                  <td>{{ $row['number'] }}/{{ $row['installments'] }}</td>
                  <td>{{ optional($row['paid_at'])->format('d/m/Y') ?: '-' }}</td>
                  <td>{{ $row['reference'] ?: '-' }}</td>
                  <td>{{ $money($row['gross']) }}</td>
                  <td>{{ $money($row['fee']) }}</td>
                  <td><strong>{{ $money($row['net']) }}</strong></td>
                </tr>
              @endforeach
              <tr class="receivables-day-total">
                <td colspan="6"><strong>Total de {{ \Illuminate\Support\Carbon::parse($date)->format('d/m/Y') }}</strong></td>
                <td>{{ $money($rows->sum('gross')) }}</td>
                <td>{{ $money($rows->sum('fee')) }}</td>
                <td><strong>{{ $money($rows->sum('net')) }}</strong></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif
@endsection
