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

  <section class="grid stats inventory-lot-stats">
    <div class="stat">
      <span>Revisados e atuais</span>
      <strong>{{ $portfolio['counts']['reviewed'] }}</strong>
    </div>
    <div class="stat">
      <span>Com acompanhamento</span>
      <strong>{{ $portfolio['counts']['held'] }}</strong>
    </div>
    <div class="stat">
      <span>Revisões superadas</span>
      <strong>{{ $portfolio['counts']['stale'] }}</strong>
    </div>
    <div class="stat">
      <span>Sem revisão</span>
      <strong>{{ $portfolio['counts']['pending'] }}</strong>
    </div>
  </section>

  <section class="panel">
    <div class="panel-heading">
      <div>
        <h2>Situação consolidada dos períodos</h2>
        <p>Volume, qualidade da evidência, maturidade e última revisão de cada recorte.</p>
      </div>
      <span class="badge muted-badge">4 períodos</span>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Período</th>
            <th>Amostra</th>
            <th>Indicadores</th>
            <th>Maturidade</th>
            <th>Revisão humana</th>
            <th>Último registro</th>
          </tr>
        </thead>
        <tbody>
          @foreach($portfolio['items'] as $item)
            <tr>
              <td>
                <a href="{{ route('purchase-entries.replenishment-purchases', ['period' => $item['period']]) }}"><strong>{{ $item['period_label'] }}</strong></a>
                <div><a href="{{ route('purchase-entries.replenishment-purchases.reviews', ['period' => $item['period']]) }}">Filtrar histórico</a></div>
              </td>
              <td>
                <strong>{{ $item['metrics']['comparable'] }} comparável(is)</strong>
                <div class="muted">{{ $item['metrics']['total'] }} decisão(ões) no total</div>
              </td>
              <td>
                <div>Adesão: {{ $item['metrics']['adherence_percent'] === null ? '—' : number_format($item['metrics']['adherence_percent'], 1, ',', '.').'%' }}</div>
                <div>Evidência: {{ $item['metrics']['evidence_coverage_percent'] === null ? '—' : number_format($item['metrics']['evidence_coverage_percent'], 1, ',', '.').'%' }}</div>
              </td>
              <td>
                <span class="badge {{ $item['maturity']['tone'] }}">{{ $item['maturity']['label'] }}</span>
                <div class="muted">{{ $item['maturity']['criteria_met'] }} de {{ $item['maturity']['criteria_total'] }} critérios</div>
              </td>
              <td>
                <span class="badge {{ $item['review_status']['tone'] }}">{{ $item['review_status']['label'] }}</span>
                <div class="muted">{{ $item['review_status']['description'] }}</div>
              </td>
              <td>
                @if($item['review'])
                  <strong>{{ $item['review']['actor'] ?? 'Usuário removido' }}</strong>
                  <div class="muted">{{ $item['review']['reviewed_at']->format('d/m/Y H:i') }}</div>
                @else
                  <span class="muted">Nenhum registro</span>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <div class="panel-body">
      <p class="muted">Os períodos se sobrepõem e não representam amostras independentes. O painel organiza a revisão humana; não compara desempenho estatístico nem altera regras automaticamente.</p>
    </div>
  </section>

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
