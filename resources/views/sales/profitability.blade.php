@extends('layouts.admin')

@section('title', 'Rentabilidade - VetFlow')

@section('content')
  @php
    $period = $summary['period'];
    $stats = $summary['stats'];
    $money = fn ($value) => 'R$ '.number_format((float) $value, 2, ',', '.');
    $percent = fn ($value) => $value === null ? '-' : number_format((float) $value, 2, ',', '.').'%';
    $quantity = fn ($value) => number_format((float) $value, 3, ',', '.');
  @endphp

  <header class="topbar">
    <div>
      <h1>Rentabilidade das vendas</h1>
      <p>Receita, custo e margem bruta realizados por item e categoria.</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('sales.cashier') }}">Caixa</a>
      <a class="button secondary" href="{{ route('sales.index') }}">Vendas</a>
    </div>
  </header>

  <div class="alert-soft">
    <strong>Margem bruta operacional</strong>
    <span>O calculo usa os custos gravados na venda e desconta devolucoes. Impostos, despesas e comissoes nao estao incluidos. Servicos sem custo cadastrado exibem margem igual a receita.</span>
  </div>

  <div class="panel">
    <div class="panel-body">
      <form method="GET" action="{{ route('sales.profitability') }}" class="form-grid">
        <div class="field">
          <label for="from">De</label>
          <input id="from" name="from" type="date" value="{{ $period['from'] }}">
        </div>
        <div class="field">
          <label for="to">Ate</label>
          <input id="to" name="to" type="date" value="{{ $period['to'] }}">
        </div>
        <div class="field">
          <label for="type">Tipo</label>
          <select id="type" name="type">
            <option value="all" @selected($summary['filter']['type'] === 'all')>Todos</option>
            @foreach($typeLabels as $value => $label)
              <option value="{{ $value }}" @selected($summary['filter']['type'] === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="field full">
          <div class="actions">
            <button type="submit">Atualizar relatorio</button>
            <a class="button secondary" href="{{ route('sales.profitability') }}">Mes atual</a>
          </div>
        </div>
      </form>
    </div>
  </div>

  <section class="grid stats">
    <div class="stat"><span>Vendas analisadas</span><strong>{{ $stats['sales_count'] }}</strong></div>
    <div class="stat"><span>Receita antes das devolucoes</span><strong>{{ $money($stats['gross_revenue']) }}</strong></div>
    <div class="stat"><span>Devolucoes</span><strong>{{ $money($stats['returns']) }}</strong></div>
    <div class="stat"><span>Receita liquida</span><strong>{{ $money($stats['net_revenue']) }}</strong></div>
    <div class="stat"><span>Custo realizado</span><strong>{{ $money($stats['cost']) }}</strong></div>
    <div class="stat"><span>Margem bruta</span><strong>{{ $money($stats['gross_profit']) }}</strong></div>
    <div class="stat"><span>Margem percentual</span><strong>{{ $percent($stats['gross_margin_percent']) }}</strong></div>
    <div class="stat"><span>Ticket medio liquido</span><strong>{{ $money($stats['average_ticket']) }}</strong></div>
  </section>

  @if($stats['missing_cost_lines'] > 0 || $stats['negative_margin_items'] > 0)
    <div class="alert warning">
      <strong>Revisao recomendada:</strong>
      {{ $stats['missing_cost_lines'] }} linha(s) de produto sem custo e
      {{ $stats['negative_margin_items'] }} item(ns) com margem negativa no periodo.
    </div>
  @endif

  <div class="panel">
    <div class="panel-heading">
      <div>
        <h2>Resultado por tipo</h2>
        <p>{{ $period['label'] }} · filtro {{ $summary['filter']['type_label'] }}</p>
      </div>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Tipo</th><th>Vendas</th><th>Receita liquida</th><th>Custo</th><th>Margem bruta</th><th>Margem</th></tr>
        </thead>
        <tbody>
          @foreach($summary['by_type'] as $row)
            <tr>
              <td><strong>{{ $row['label'] }}</strong></td>
              <td>{{ $row['sales_count'] }}</td>
              <td>{{ $money($row['net_revenue']) }}</td>
              <td>{{ $money($row['cost']) }}</td>
              <td>{{ $money($row['gross_profit']) }}</td>
              <td>{{ $percent($row['gross_margin_percent']) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  <div class="panel">
    <div class="panel-heading">
      <div>
        <h2>Categorias</h2>
        <p>Comparativo pela categoria gravada no momento da venda.</p>
      </div>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Categoria</th><th>Tipo</th><th>Quantidade</th><th>Receita liquida</th><th>Custo</th><th>Margem bruta</th><th>Margem</th></tr>
        </thead>
        <tbody>
          @forelse($summary['by_category'] as $row)
            <tr>
              <td>
                <strong>{{ $row['category'] }}</strong>
                @if(auth()->user()?->clinic_id === null)
                  <div class="muted">{{ $row['clinic_name'] ?? '-' }}</div>
                @endif
              </td>
              <td>{{ $row['type_label'] }}</td>
              <td>{{ $quantity($row['quantity'] - $row['returned_quantity']) }}</td>
              <td>{{ $money($row['net_revenue']) }}</td>
              <td>{{ $money($row['cost']) }}</td>
              <td>{{ $money($row['gross_profit']) }}</td>
              <td>{{ $percent($row['gross_margin_percent']) }}</td>
            </tr>
          @empty
            <tr><td colspan="7" class="muted">Nenhuma venda concluida no periodo.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="panel">
    <div class="panel-heading">
      <div>
        <h2>Itens vendidos</h2>
        <p>Ordenados pela maior margem bruta realizada.</p>
      </div>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Item</th><th>Tipo</th><th>Vendida</th><th>Devolvida</th><th>Receita liquida</th><th>Custo</th><th>Margem bruta</th><th>Margem</th></tr>
        </thead>
        <tbody>
          @forelse($summary['items'] as $row)
            <tr>
              <td>
                <strong>{{ $row['description'] }}</strong>
                <div class="muted">{{ $row['category'] }}</div>
                @if(auth()->user()?->clinic_id === null)
                  <div class="muted">{{ $row['clinic_name'] ?? '-' }}</div>
                @endif
                @if($row['missing_cost'])
                  <span class="badge warning">Custo nao informado</span>
                @endif
              </td>
              <td>{{ $row['type_label'] }}</td>
              <td>{{ $quantity($row['quantity']) }}</td>
              <td>{{ $quantity($row['returned_quantity']) }}</td>
              <td>{{ $money($row['net_revenue']) }}</td>
              <td>{{ $money($row['cost']) }}</td>
              <td><strong>{{ $money($row['gross_profit']) }}</strong></td>
              <td>{{ $percent($row['gross_margin_percent']) }}</td>
            </tr>
          @empty
            <tr><td colspan="8" class="muted">Nenhum item encontrado para os filtros informados.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection
