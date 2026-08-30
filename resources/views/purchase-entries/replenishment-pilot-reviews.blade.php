@extends('layouts.admin')

@section('title', 'Histórico de revisão do piloto - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Histórico de revisão do piloto</h1>
      <p>Acompanhe as decisões humanas registradas para cada período da validação de reposição.</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('purchase-entries.replenishment-purchases', ['period' => $filters['period'] ?? '90']) }}">Voltar às decisões de compra</a>
      <a class="button secondary" href="{{ route('purchase-entries.replenishment') }}">Voltar à reposição</a>
    </div>
  </header>

  <section class="panel">
    <div class="panel-body">
      <form method="GET" action="{{ route('purchase-entries.replenishment-purchases.reviews') }}" class="form-grid compact-filter-grid">
        <div class="field">
          <label for="replenishment-pilot-review-period">Período revisado</label>
          <select id="replenishment-pilot-review-period" name="period">
            <option value="">Todos</option>
            @foreach($periods as $key => $label)
              <option value="{{ $key }}" @selected(($filters['period'] ?? null) === $key)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="field">
          <label for="replenishment-pilot-review-filter-decision">Decisão</label>
          <select id="replenishment-pilot-review-filter-decision" name="decision">
            <option value="">Todas</option>
            @foreach($decisions as $key => $label)
              <option value="{{ $key }}" @selected(($filters['decision'] ?? null) === $key)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="field implementation-portfolio-filter-actions">
          <button type="submit">Filtrar</button>
          <a class="button secondary" href="{{ route('purchase-entries.replenishment-purchases.reviews') }}">Limpar</a>
        </div>
      </form>
    </div>
  </section>

  <section class="panel">
    <div class="panel-heading">
      <div>
        <h2>Revisões registradas</h2>
        <p>As evidências são reavaliadas contra o relatório atual do mesmo período.</p>
      </div>
      <span class="badge muted-badge">{{ $events->total() }} evento(s)</span>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Registrada em</th>
            <th>Escopo</th>
            <th>Período</th>
            <th>Decisão</th>
            <th>Responsável</th>
            <th>Evidência</th>
            <th>Observação</th>
          </tr>
        </thead>
        <tbody>
          @forelse($events as $event)
            <tr>
              <td>{{ $event['reviewed_at']->format('d/m/Y H:i') }}</td>
              <td>{{ $event['scope_label'] }}</td>
              <td>{{ $event['period_label'] }}</td>
              <td><span class="badge {{ $event['decision_tone'] }}">{{ $event['decision_label'] }}</span></td>
              <td>{{ $event['actor'] ?? 'Usuário removido' }}</td>
              <td><span class="badge {{ $event['evidence_tone'] }}">{{ $event['evidence_label'] }}</span></td>
              <td>{{ $event['note'] ?? 'Sem observação' }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="muted">Nenhuma revisão do piloto encontrada para os filtros selecionados.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="panel-body">{{ $events->links() }}</div>
  </section>

  <p class="muted">Este histórico é somente leitura, não expõe hashes ou snapshots e não altera regras de reposição automaticamente.</p>
@endsection
