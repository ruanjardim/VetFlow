@extends('layouts.admin')

@section('title', 'Projeção de caixa - VetFlow')

@section('content')
  @php
    $money = fn (int $cents) => number_format($cents / 100, 2, ',', '.');
    $routeFilters = [
      'from' => $filters['from'],
      'days' => $filters['days'],
      'include_overdue' => $filters['include_overdue'] ? 1 : 0,
      ...($requiresClinic ? ['clinic_id' => $selectedClinicId] : []),
    ];
  @endphp

  <header class="topbar">
    <div>
      <h1>Projeção de fluxo de caixa</h1>
      <p>Saldo acumulado diário e semanal com base nos lançamentos registrados no VetFlow.</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('financial-bank-reconciliation.index', $requiresClinic ? ['clinic_id' => $selectedClinicId] : []) }}">Conciliação bancária</a>
      <a class="button secondary" href="{{ route('financial-obligations.index', $requiresClinic ? ['clinic_id' => $selectedClinicId] : []) }}">Contas a pagar e receber</a>
      <a class="button secondary" href="{{ route('financial-results.index') }}">Resultado gerencial</a>
      <a class="button secondary" href="{{ route('financial-transactions.index') }}">Lançamentos</a>
      <a class="button" href="{{ route('financial-transactions.cash-flow.export', $routeFilters) }}">Exportar CSV</a>
    </div>
  </header>

  <div class="alert info">
    <strong>Projeção operacional, não saldo bancário.</strong>
    <span>O saldo inicial soma entradas e saídas pagas registradas antes do período. Valores fora do VetFlow e conciliação bancária não estão incluídos.</span>
  </div>

  <form method="GET" action="{{ route('financial-transactions.cash-flow') }}" class="panel panel-body">
    <div class="form-grid">
      @if($requiresClinic)
        <div class="field">
          <label for="clinic_id">Estabelecimento</label>
          <select id="clinic_id" name="clinic_id" required>
            @foreach($clinics as $clinic)
              <option value="{{ $clinic->id }}" @selected($selectedClinicId === $clinic->id)>{{ $clinic->trade_name ?: $clinic->corporate_name }}</option>
            @endforeach
          </select>
        </div>
      @endif
      <div class="field">
        <label for="cash-flow-from">Data inicial</label>
        <input id="cash-flow-from" name="from" type="date" value="{{ $filters['from'] }}">
      </div>
      <div class="field">
        <label for="cash-flow-days">Horizonte</label>
        <select id="cash-flow-days" name="days">
          @foreach($horizonLabels as $days => $label)
            <option value="{{ $days }}" @selected($filters['days'] === $days)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="field">
        <label for="include-overdue">Lançamentos vencidos</label>
        <select id="include-overdue" name="include_overdue">
          <option value="1" @selected($filters['include_overdue'])>Assumir na data inicial</option>
          <option value="0" @selected(! $filters['include_overdue'])>Mostrar como risco, sem projetar</option>
        </select>
      </div>
      <div class="field full">
        <div class="actions">
          <button type="submit">Atualizar projeção</button>
          <a class="button secondary" href="{{ route('financial-transactions.cash-flow', $requiresClinic ? ['clinic_id' => $selectedClinicId] : []) }}">Limpar</a>
        </div>
      </div>
    </div>
  </form>

  <section class="grid stats">
    <div class="stat">
      <span>Saldo inicial registrado</span>
      <strong>R$ {{ $money($stats['opening_balance_cents']) }}</strong>
    </div>
    <div class="stat">
      <span>Entradas no período</span>
      <strong>R$ {{ $money($stats['income_cents']) }}</strong>
      <small>Realizadas e previstas no cenário.</small>
    </div>
    <div class="stat">
      <span>Saídas no período</span>
      <strong>R$ {{ $money($stats['expense_cents']) }}</strong>
      <small>Realizadas e previstas no cenário.</small>
    </div>
    <div class="stat">
      <span>Saldo projetado final</span>
      <strong>R$ {{ $money($stats['projected_balance_cents']) }}</strong>
      <small>{{ $reference['end']->format('d/m/Y') }}</small>
    </div>
    <div class="stat">
      <span>Contas vencidas</span>
      <strong>R$ {{ $money($stats['overdue_income_cents'] - $stats['overdue_expense_cents']) }}</strong>
      <small>{{ $stats['overdue_count'] }} lançamento(s); impacto líquido.</small>
    </div>
    <div class="stat">
      <span>Menor saldo projetado</span>
      <strong>R$ {{ $money($stats['lowest_balance_cents']) }}</strong>
      <small>{{ $stats['lowest_balance_date']->format('d/m/Y') }}</small>
    </div>
    <div class="stat">
      <span>Dias com saldo negativo</span>
      <strong>{{ $stats['negative_days'] }}</strong>
      <small>{{ $stats['first_negative_date'] ? 'Primeiro em '.$stats['first_negative_date']->format('d/m/Y') : 'Nenhum no cenário.' }}</small>
    </div>
    <div class="stat">
      <span>Sem vencimento</span>
      <strong>{{ $stats['undated_count'] }}</strong>
      <small>Fora da projeção por falta de data.</small>
    </div>
  </section>

  @if($stats['negative_days'] > 0)
    <div class="alert warning">
      <strong>Risco de caixa no cenário.</strong>
      <span>O saldo fica negativo pela primeira vez em {{ $stats['first_negative_date']->format('d/m/Y') }} e atinge R$ {{ $money($stats['lowest_balance_cents']) }} em {{ $stats['lowest_balance_date']->format('d/m/Y') }}.</span>
    </div>
  @endif

  @if($stats['overdue_count'] > 0)
    <div class="alert-soft">
      <strong>Vencidos tratados de forma explícita</strong>
      <span>
        @if($filters['include_overdue'])
          O cenário assume todas as contas vencidas na data inicial. Isso é uma hipótese operacional, não uma baixa automática.
        @else
          As contas vencidas estão visíveis nos indicadores, mas não alteram o saldo projetado.
        @endif
      </span>
    </div>
  @endif

  @if($stats['undated_count'] > 0)
    <div class="alert warning">
      <strong>{{ $stats['undated_count'] }} lançamento(s) ativo(s) sem vencimento ficaram fora da projeção.</strong>
      <span>Entradas: R$ {{ $money($stats['undated_income_cents']) }} · Saídas: R$ {{ $money($stats['undated_expense_cents']) }}.</span>
    </div>
  @endif

  <div class="panel">
    <div class="panel-heading">
      <div>
        <h2>Projeção diária</h2>
        <p>{{ $reference['label'] }}. Clique na composição para conferir os lançamentos de cada dia.</p>
      </div>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Data</th>
            <th>Realizado</th>
            <th>Previsto</th>
            <th>Entradas</th>
            <th>Saídas</th>
            <th>Saldo do dia</th>
            <th>Saldo acumulado</th>
            <th>Composição</th>
          </tr>
        </thead>
        <tbody>
          @foreach($rows as $row)
            @php
              $actualNet = $row['actual_income_cents'] - $row['actual_expense_cents'];
              $scheduledNet = $row['scheduled_income_cents'] + $row['overdue_income_cents'] - $row['scheduled_expense_cents'] - $row['overdue_expense_cents'];
            @endphp
            <tr>
              <td>
                <strong>{{ $row['date']->format('d/m/Y') }}</strong>
                <div class="muted">{{ ucfirst($row['date']->locale('pt_BR')->dayName) }}</div>
                @if($row['includes_overdue'])
                  <span class="badge warning">Inclui vencidos</span>
                @endif
              </td>
              <td>R$ {{ $money($actualNet) }}</td>
              <td>R$ {{ $money($scheduledNet) }}</td>
              <td>R$ {{ $money($row['income_cents']) }}</td>
              <td>R$ {{ $money($row['expense_cents']) }}</td>
              <td>R$ {{ $money($row['net_cents']) }}</td>
              <td><span class="badge {{ $row['balance_cents'] < 0 ? 'danger' : 'success' }}">R$ {{ $money($row['balance_cents']) }}</span></td>
              <td>
                @if($row['transactions_count'] > 0)
                  <a class="button secondary" href="{{ route('financial-transactions.cash-flow.transactions', $routeFilters + ['date' => $row['date']->toDateString()]) }}">{{ $row['transactions_count'] }} lançamento(s)</a>
                @else
                  <span class="muted">Sem movimento</span>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  <div class="panel">
    <div class="panel-heading">
      <div>
        <h2>Resumo semanal</h2>
        <p>Agrupamento da mesma projeção diária, sem arredondar ou duplicar movimentos.</p>
      </div>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Semana</th><th>Entradas</th><th>Saídas</th><th>Saldo da semana</th><th>Saldo final</th><th>Dias negativos</th></tr>
        </thead>
        <tbody>
          @foreach($weekly_rows as $week)
            <tr>
              <td>{{ $week['label'] }}</td>
              <td>R$ {{ $money($week['income_cents']) }}</td>
              <td>R$ {{ $money($week['expense_cents']) }}</td>
              <td>R$ {{ $money($week['net_cents']) }}</td>
              <td><span class="badge {{ $week['ending_balance_cents'] < 0 ? 'danger' : 'success' }}">R$ {{ $money($week['ending_balance_cents']) }}</span></td>
              <td>{{ $week['negative_days'] }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
@endsection
