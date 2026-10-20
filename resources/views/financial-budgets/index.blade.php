@extends('layouts.admin')

@section('title', 'Orçamento mensal - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Orçamento mensal</h1>
      <p>Planeje entradas e saídas por categoria e acompanhe o previsto contra o realizado.</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('financial-budgets.export', ['competence' => $filters['competence'], 'basis' => $filters['basis'], ...($requiresClinic ? ['clinic_id' => $selectedClinicId] : [])]) }}">Exportar CSV</a>
      <form method="POST" action="{{ route('financial-budgets.copy-previous') }}" class="inline">
        @csrf
        <input type="hidden" name="competence" value="{{ $filters['competence'] }}">
        <input type="hidden" name="basis" value="{{ $filters['basis'] }}">
        @if($requiresClinic)
          <input type="hidden" name="clinic_id" value="{{ $selectedClinicId }}">
        @endif
        <button class="secondary" type="submit" data-confirm="Copiar os valores do mês anterior? Os valores já preenchidos em {{ $reference['label'] }} serão preservados.">Copiar mês anterior</button>
      </form>
      <a class="button secondary" href="{{ route('financial-categories.index', $requiresClinic ? ['clinic_id' => $selectedClinicId] : []) }}">Categorias</a>
      <a class="button" href="{{ route('financial-results.index', ['competence' => $filters['competence'], 'basis' => $filters['basis']]) }}">Resultado gerencial</a>
    </div>
  </header>

  <form method="GET" action="{{ route('financial-budgets.index') }}" class="panel panel-body">
    <div class="filter-grid budget-filter-grid">
      @if($requiresClinic)
        <div>
          <label for="clinic_id">Estabelecimento</label>
          <select id="clinic_id" name="clinic_id" required>
            @foreach($clinics as $clinic)
              <option value="{{ $clinic->id }}" @selected($selectedClinicId === $clinic->id)>
                {{ $clinic->trade_name ?: $clinic->corporate_name }}
              </option>
            @endforeach
          </select>
        </div>
      @endif
      <div>
        <label for="competence">Competência</label>
        <input id="competence" name="competence" type="month" value="{{ $filters['competence'] }}" required>
      </div>
      <div>
        <label for="basis">Realizado pela visão</label>
        <select id="basis" name="basis">
          @foreach($basisLabels as $value => $label)
            <option value="{{ $value }}" @selected($filters['basis'] === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="filter-actions">
        <button type="submit">Atualizar comparação</button>
        <a class="button secondary" href="{{ route('financial-budgets.index', $requiresClinic ? ['clinic_id' => $selectedClinicId] : []) }}">Mês atual</a>
      </div>
    </div>
  </form>

  <div class="alert warning">
    O orçamento é uma meta gerencial interna. O realizado usa
    @if($filters['basis'] === 'cash')
      somente pagamentos concluídos pela data de pagamento em {{ $reference['label'] }}.
    @else
      lançamentos não cancelados pela data de vencimento em {{ $reference['label'] }} e não substitui a competência contábil.
    @endif
  </div>

  @if($excluded_without_date > 0)
    <div class="alert warning">
      Há {{ $excluded_without_date }} lançamento(s) sem a data exigida pela visão escolhida; eles não entram no realizado.
    </div>
  @endif

  @if($expense_alerts->isNotEmpty())
    <section class="panel budget-alert-panel" aria-labelledby="budget-alert-title">
      <div class="panel-heading">
        <div>
          <h2 id="budget-alert-title">Alertas do orçamento</h2>
          <p>{{ $stats['expense_alerts_count'] }} categoria(s) exigem atenção, com R$ {{ number_format($stats['expense_overrun_cents'] / 100, 2, ',', '.') }} acima do total planejado.</p>
        </div>
        <span class="badge danger">Atenção</span>
      </div>
      <div class="budget-alert-list">
        @foreach($expense_alerts as $alert)
          <a class="budget-alert-item" href="{{ route('financial-budgets.transactions', ['competence' => $filters['competence'], 'basis' => $filters['basis'], 'type' => 'expense', ...($alert['category_id'] ? ['financial_category_id' => $alert['category_id']] : []), ...($requiresClinic ? ['clinic_id' => $selectedClinicId] : [])]) }}">
            <span>
              <strong>{{ $alert['category_name'] }}</strong>
              <small>{{ $alert['unplanned'] ? 'Despesa sem orçamento definido' : 'Limite mensal ultrapassado' }}</small>
            </span>
            <strong>R$ {{ number_format($alert['excess_cents'] / 100, 2, ',', '.') }} acima</strong>
          </a>
        @endforeach
      </div>
    </section>
  @else
    <div class="alert success"><strong>Orçamento sob controle:</strong> nenhuma despesa ultrapassou o limite planejado nesta visão.</div>
  @endif

  <section class="grid stats budget-stats">
    <div class="stat">
      <span>Entradas previstas</span>
      <strong>R$ {{ number_format($stats['planned_income_cents'] / 100, 2, ',', '.') }}</strong>
    </div>
    <div class="stat">
      <span>Entradas realizadas</span>
      <strong>R$ {{ number_format($stats['actual_income_cents'] / 100, 2, ',', '.') }}</strong>
    </div>
    <div class="stat{{ $stats['income_variance_cents'] < 0 ? ' stat--alert' : '' }}">
      <span>Diferença das entradas</span>
      <strong>{{ $stats['income_variance_cents'] >= 0 ? '+' : '-' }} R$ {{ number_format(abs($stats['income_variance_cents']) / 100, 2, ',', '.') }}</strong>
    </div>
    <div class="stat">
      <span>Saídas previstas</span>
      <strong>R$ {{ number_format($stats['planned_expense_cents'] / 100, 2, ',', '.') }}</strong>
    </div>
    <div class="stat{{ $stats['actual_expense_cents'] > $stats['planned_expense_cents'] ? ' stat--alert' : '' }}">
      <span>Saídas realizadas</span>
      <strong>R$ {{ number_format($stats['actual_expense_cents'] / 100, 2, ',', '.') }}</strong>
    </div>
    <div class="stat{{ $stats['expense_variance_cents'] > 0 ? ' stat--alert' : '' }}">
      <span>Diferença das saídas</span>
      <strong>{{ $stats['expense_variance_cents'] >= 0 ? '+' : '-' }} R$ {{ number_format(abs($stats['expense_variance_cents']) / 100, 2, ',', '.') }}</strong>
    </div>
  </section>

  <section class="grid stats budget-stats">
    <div class="stat{{ $stats['planned_balance_cents'] < 0 ? ' stat--alert' : '' }}">
      <span>Saldo previsto</span>
      <strong>R$ {{ number_format($stats['planned_balance_cents'] / 100, 2, ',', '.') }}</strong>
    </div>
    <div class="stat{{ $stats['actual_balance_cents'] < 0 ? ' stat--alert' : '' }}">
      <span>Saldo realizado</span>
      <strong>R$ {{ number_format($stats['actual_balance_cents'] / 100, 2, ',', '.') }}</strong>
    </div>
    <div class="stat{{ $stats['balance_variance_cents'] < 0 ? ' stat--alert' : '' }}">
      <span>Diferença do saldo</span>
      <strong>{{ $stats['balance_variance_cents'] >= 0 ? '+' : '-' }} R$ {{ number_format(abs($stats['balance_variance_cents']) / 100, 2, ',', '.') }}</strong>
    </div>
  </section>

  <form method="POST" action="{{ route('financial-budgets.update') }}">
    @csrf
    @method('PUT')
    <input type="hidden" name="competence" value="{{ $filters['competence'] }}">
    <input type="hidden" name="basis" value="{{ $filters['basis'] }}">
    @if($requiresClinic)
      <input type="hidden" name="clinic_id" value="{{ $selectedClinicId }}">
    @endif

    @foreach(['income' => ['title' => 'Orçamento de entradas', 'description' => 'Metas de receita por categoria.', 'rows' => $income_rows], 'expense' => ['title' => 'Orçamento de saídas', 'description' => 'Limites de despesa por categoria.', 'rows' => $expense_rows]] as $group)
      <section class="panel budget-panel" @if($loop->last) id="budget-expenses" @endif>
        <div class="panel-heading">
          <div>
            <h2>{{ $group['title'] }}</h2>
            <p>{{ $group['description'] }}</p>
          </div>
        </div>
        <div class="table-wrap">
          <table class="financial-budget-table">
            <thead>
              <tr>
                <th>Categoria</th>
                <th>Previsto</th>
                <th>Realizado</th>
                <th>Diferença</th>
                <th>Execução</th>
                <th>Situação</th>
                <th>Composição</th>
              </tr>
            </thead>
            <tbody>
              @foreach($group['rows'] as $row)
                <tr class="{{ ! $row['active'] && $row['editable'] ? 'budget-row-inactive' : '' }}">
                  <td>
                    <strong>{{ $row['category_name'] }}</strong>
                    @if(! $row['active'] && $row['editable'])
                      <span class="badge muted-badge">Inativa</span>
                    @endif
                  </td>
                  <td>
                    @if($row['editable'])
                      <label class="sr-only" for="budget-{{ $row['category_id'] }}">Previsto para {{ $row['category_name'] }}</label>
                      <div class="money-input budget-amount-input">
                        <span>R$</span>
                        <input
                          id="budget-{{ $row['category_id'] }}"
                          name="budgets[{{ $row['category_id'] }}]"
                          type="text"
                          inputmode="decimal"
                          value="{{ old('budgets.'.$row['category_id'], number_format($row['planned_cents'] / 100, 2, ',', '.')) }}"
                          data-money-input
                          required
                        >
                      </div>
                    @else
                      <span class="muted">Não editável</span>
                    @endif
                  </td>
                  <td><strong>R$ {{ number_format($row['actual_cents'] / 100, 2, ',', '.') }}</strong></td>
                  <td class="{{ $row['variance_cents'] < 0 ? 'text-danger' : '' }}">
                    {{ $row['variance_cents'] >= 0 ? '+' : '-' }} R$ {{ number_format(abs($row['variance_cents']) / 100, 2, ',', '.') }}
                  </td>
                  <td>{{ $row['execution_percent'] === null ? '—' : number_format($row['execution_percent'], 2, ',', '.').'%' }}</td>
                  <td><span class="badge {{ $row['status_class'] }}">{{ $row['status_label'] }}</span></td>
                  <td>
                    @if($row['transactions_count'] > 0)
                      <a href="{{ route('financial-budgets.transactions', ['competence' => $filters['competence'], 'basis' => $filters['basis'], 'type' => $row['type'], ...($row['category_id'] ? ['financial_category_id' => $row['category_id']] : []), ...($requiresClinic ? ['clinic_id' => $selectedClinicId] : [])]) }}">
                        Ver {{ $row['transactions_count'] }} {{ $row['transactions_count'] === 1 ? 'lançamento' : 'lançamentos' }}
                      </a>
                    @else
                      <span class="muted">Sem lançamentos</span>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </section>
    @endforeach

    <div class="form-actions budget-save-actions">
      <button type="submit">Salvar orçamento de {{ $reference['label'] }}</button>
    </div>
  </form>

  <section class="panel budget-history-panel">
    <div class="panel-heading">
      <div>
        <h2>Histórico de 12 meses</h2>
        <p>Evolução mensal do orçamento e do realizado na visão {{ strtolower($basisLabels[$filters['basis']]) }}.</p>
      </div>
    </div>
    <div class="table-wrap">
      <table class="financial-budget-history-table">
        <thead>
          <tr>
            <th>Competência</th>
            <th>Entradas previstas</th>
            <th>Entradas realizadas</th>
            <th>Saídas previstas</th>
            <th>Saídas realizadas</th>
            <th>Saldo previsto</th>
            <th>Saldo realizado</th>
            <th>Diferença do saldo</th>
          </tr>
        </thead>
        <tbody>
          @foreach($history as $month)
            <tr class="{{ $month['selected'] ? 'budget-history-current' : '' }}">
              <td>
                <a href="{{ route('financial-budgets.index', ['competence' => $month['competence'], 'basis' => $filters['basis'], ...($requiresClinic ? ['clinic_id' => $selectedClinicId] : [])]) }}">
                  <strong>{{ $month['label'] }}</strong>
                </a>
                @if($month['selected']) <span class="badge success">Atual</span> @endif
              </td>
              <td>R$ {{ number_format($month['planned_income_cents'] / 100, 2, ',', '.') }}</td>
              <td>R$ {{ number_format($month['actual_income_cents'] / 100, 2, ',', '.') }}</td>
              <td>R$ {{ number_format($month['planned_expense_cents'] / 100, 2, ',', '.') }}</td>
              <td>R$ {{ number_format($month['actual_expense_cents'] / 100, 2, ',', '.') }}</td>
              <td>R$ {{ number_format($month['planned_balance_cents'] / 100, 2, ',', '.') }}</td>
              <td>R$ {{ number_format($month['actual_balance_cents'] / 100, 2, ',', '.') }}</td>
              <td class="{{ $month['balance_variance_cents'] < 0 ? 'text-danger' : '' }}">
                {{ $month['balance_variance_cents'] >= 0 ? '+' : '-' }} R$ {{ number_format(abs($month['balance_variance_cents']) / 100, 2, ',', '.') }}
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>

  <section class="panel budget-audit-panel">
    <div class="panel-heading">
      <div>
        <h2>Alterações do orçamento</h2>
        <p>Últimas atualizações registradas para {{ $reference['label'] }}.</p>
      </div>
    </div>
    <div class="table-wrap">
      <table class="financial-budget-audit-table">
        <thead>
          <tr>
            <th>Data</th>
            <th>Ação</th>
            <th>Categoria</th>
            <th>Responsável</th>
            <th>Alteração</th>
          </tr>
        </thead>
        <tbody>
          @forelse($audit_history as $event)
            <tr>
              <td>{{ $event['occurred_at']?->format('d/m/Y H:i') }}</td>
              <td>
                {{ $event['label'] }}
                @if($event['source_competence'])
                  <div class="muted">Origem: {{ \Carbon\CarbonImmutable::createFromFormat('Y-m-d', $event['source_competence'].'-01')->format('m/Y') }}</div>
                @endif
              </td>
              <td>{{ $event['subject_label'] }}</td>
              <td>{{ $event['actor_name'] }}</td>
              <td>
                @if($event['before_cents'] !== null)
                  R$ {{ number_format($event['before_cents'] / 100, 2, ',', '.') }} →
                @endif
                {{ $event['after_cents'] === null ? '—' : 'R$ '.number_format($event['after_cents'] / 100, 2, ',', '.') }}
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="muted">Nenhuma alteração registrada para esta competência.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>
@endsection
