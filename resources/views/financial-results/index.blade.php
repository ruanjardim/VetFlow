@extends('layouts.admin')

@section('title', 'Resultado gerencial mensal - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Resultado gerencial mensal</h1>
      <p>Entradas, saídas e saldo por categoria, com leitura por caixa ou competência operacional.</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('financial-budgets.index', $filters) }}">Orçamento mensal</a>
      <a class="button secondary" href="{{ route('financial-transactions.index') }}">Ver lançamentos</a>
      <a class="button" href="{{ route('financial-results.export', $filters) }}">Exportar CSV</a>
    </div>
  </header>

  <form method="GET" action="{{ route('financial-results.index') }}" class="panel panel-body">
    <div class="filter-grid compact-filter-grid">
      <div>
        <label for="competence">Competência</label>
        <input id="competence" name="competence" type="month" value="{{ $filters['competence'] }}" required>
      </div>
      <div>
        <label for="basis">Visão</label>
        <select id="basis" name="basis">
          @foreach($basisLabels as $value => $label)
            <option value="{{ $value }}" @selected($filters['basis'] === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="filter-actions">
        <button type="submit">Atualizar resultado</button>
        <a class="button secondary" href="{{ route('financial-results.index') }}">Mês atual</a>
      </div>
    </div>
  </form>

  <div class="alert warning">
    @if($filters['basis'] === 'cash')
      A visão de caixa considera somente lançamentos pagos em {{ $reference['label'] }}, pela data do pagamento.
    @else
      A competência operacional considera lançamentos não cancelados com vencimento em {{ $reference['label'] }}. Ela não substitui a competência contábil definida pelo contador.
    @endif
  </div>

  @if($stats['excluded_without_date'] > 0)
    <div class="alert warning">
      Há {{ $stats['excluded_without_date'] }} lançamento(s) desta clínica sem {{ strtolower($reference['date_label']) }}; eles não entram em nenhuma competência desta visão.
    </div>
  @endif

  <section class="grid stats">
    <div class="stat">
      <span>Entradas</span>
      <strong>R$ {{ number_format($stats['income_cents'] / 100, 2, ',', '.') }}</strong>
    </div>
    <div class="stat">
      <span>Saídas</span>
      <strong>R$ {{ number_format($stats['expense_cents'] / 100, 2, ',', '.') }}</strong>
    </div>
    <div class="stat{{ $stats['balance_cents'] < 0 ? ' stat--alert' : '' }}">
      <span>Saldo</span>
      <strong>R$ {{ number_format($stats['balance_cents'] / 100, 2, ',', '.') }}</strong>
    </div>
    <div class="stat">
      <span>Lançamentos considerados</span>
      <strong>{{ $stats['transactions_count'] }}</strong>
    </div>
  </section>

  <section class="content-grid financial-result-grid">
    <div class="panel">
      <div class="panel-heading">
        <div>
          <h2>Entradas por categoria</h2>
          <p>Participação de cada origem no total de entradas.</p>
        </div>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Categoria</th>
              <th>Lançamentos</th>
              <th>Participação</th>
              <th>Valor</th>
            </tr>
          </thead>
          <tbody>
            @forelse($income_categories as $row)
              <tr>
                <td>{{ $row['category_name'] }}</td>
                <td>{{ $row['transactions_count'] }}</td>
                <td>{{ number_format($row['share_percent'], 2, ',', '.') }}%</td>
                <td><strong>R$ {{ number_format($row['amount_cents'] / 100, 2, ',', '.') }}</strong></td>
              </tr>
            @empty
              <tr><td colspan="4" class="muted">Nenhuma entrada nesta visão.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <div class="panel">
      <div class="panel-heading">
        <div>
          <h2>Saídas por categoria</h2>
          <p>Participação de cada destino no total de saídas.</p>
        </div>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Categoria</th>
              <th>Lançamentos</th>
              <th>Participação</th>
              <th>Valor</th>
            </tr>
          </thead>
          <tbody>
            @forelse($expense_categories as $row)
              <tr>
                <td>{{ $row['category_name'] }}</td>
                <td>{{ $row['transactions_count'] }}</td>
                <td>{{ number_format($row['share_percent'], 2, ',', '.') }}%</td>
                <td><strong>R$ {{ number_format($row['amount_cents'] / 100, 2, ',', '.') }}</strong></td>
              </tr>
            @empty
              <tr><td colspan="4" class="muted">Nenhuma saída nesta visão.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </section>
@endsection
