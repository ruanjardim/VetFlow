@extends('layouts.admin')

@section('title', 'Historico da reposicao - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Historico da reposicao</h1>
      <p>Trilha append-only das revisoes humanas, vinculada aos calculos existentes em cada decisao.</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('purchase-entries.replenishment') }}">Voltar a reposicao</a>
    </div>
  </header>

  <section class="panel">
    <div class="panel-body">
      <form method="GET" action="{{ route('purchase-entries.replenishment-reviews') }}" class="form-grid compact-filter-grid">
        <div class="field">
          <label for="replenishment-review-q">Produto</label>
          <input id="replenishment-review-q" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="120" placeholder="Nome do produto">
        </div>
        <div class="field">
          <label for="replenishment-review-decision">Decisao</label>
          <select id="replenishment-review-decision" name="decision">
            <option value="">Todas</option>
            @foreach($decisions as $key => $label)
              <option value="{{ $key }}" @selected(($filters['decision'] ?? null) === $key)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="field implementation-portfolio-filter-actions">
          <button type="submit">Filtrar</button>
          <a class="button secondary" href="{{ route('purchase-entries.replenishment-reviews') }}">Limpar</a>
        </div>
      </form>
    </div>
  </section>

  <section class="panel">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Produto</th>
            <th>Decisao</th>
            <th>Evidencia registrada</th>
            <th>Vigencia</th>
            <th>Responsavel</th>
            <th>Observacao</th>
          </tr>
        </thead>
        <tbody>
          @forelse($events as $event)
            @php($snapshot = $event['snapshot'])
            <tr>
              <td>
                <strong>{{ $event['product_name'] }}</strong>
                <div class="muted">Produto #{{ $event['product_id'] ?? 'removido' }}</div>
              </td>
              <td><span class="badge {{ $event['decision_tone'] }}">{{ $event['decision_label'] }}</span></td>
              <td>
                <div>Saldo / minimo: {{ number_format((float) ($snapshot['stock_quantity'] ?? 0), 3, ',', '.') }} / {{ number_format((float) ($snapshot['minimum_stock'] ?? 0), 3, ',', '.') }}</div>
                <div class="muted">Sugestao: {{ number_format((float) ($snapshot['suggested_quantity'] ?? 0), 3, ',', '.') }}</div>
                <div class="muted">Demanda liquida: {{ number_format((float) ($snapshot['net_demand_quantity'] ?? 0), 3, ',', '.') }}</div>
                <div class="muted">Cobertura: {{ isset($snapshot['coverage_days']) ? number_format((float) $snapshot['coverage_days'], 1, ',', '.').' dias' : 'indisponivel' }}</div>
                <div class="muted">Risco: {{ $snapshot['coverage_risk'] ?? 'indisponivel' }}</div>
              </td>
              <td>
                <span class="badge {{ $event['evidence_current'] ? 'success' : 'warning' }}">
                  {{ $event['evidence_current'] ? 'Evidencia atual' : 'Evidencia superada' }}
                </span>
              </td>
              <td>
                {{ $event['actor'] ?? 'Usuario removido' }}
                <div class="muted">{{ $event['reviewed_at']->format('d/m/Y H:i') }}</div>
              </td>
              <td>{{ $event['note'] ?: 'Sem observacao.' }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="muted">Nenhuma revisao encontrada.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="panel-body">{{ $events->links() }}</div>
  </section>
@endsection
