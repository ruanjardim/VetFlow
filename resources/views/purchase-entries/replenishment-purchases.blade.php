@extends('layouts.admin')

@section('title', 'Decisões de compra da reposição - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Decisões de compra da reposição</h1>
      <p>Compare o que o VetFlow sugeriu com a quantidade, o custo e o fornecedor registrados pela equipe.</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('purchase-entries.replenishment-reviews') }}">Histórico de revisões</a>
      <a class="button secondary" href="{{ route('purchase-entries.replenishment') }}">Voltar à reposição</a>
    </div>
  </header>

  <section class="grid stats inventory-lot-stats">
    <div class="stat">
      <span>Compras comparáveis</span>
      <strong>{{ $stats['comparable'] }}</strong>
    </div>
    <div class="stat">
      <span>Sugestões mantidas</span>
      <strong>{{ $stats['kept'] }}</strong>
    </div>
    <div class="stat">
      <span>Sugestões ajustadas</span>
      <strong>{{ $stats['adjusted'] }}</strong>
    </div>
    <div class="stat">
      <span>Adesão às sugestões</span>
      <strong>{{ $stats['adherence_percent'] === null ? '—' : number_format($stats['adherence_percent'], 1, ',', '.').'%' }}</strong>
    </div>
    <div class="stat">
      <span>Evidências indisponíveis</span>
      <strong>{{ $stats['unavailable'] }}</strong>
    </div>
  </section>

  <section class="intelligence-health">
    <div>
      <strong>Validação: {{ $stats['scope_label'] }}</strong>
      <span>{{ $stats['period_label'] }}, pela data da compra: {{ $stats['total'] }} decisão(ões) registrada(s); somente evidências válidas entram nas métricas.</span>
    </div>
    <div class="badge-list">
      <span class="badge muted-badge">Quantidade alterada: {{ $stats['quantity_adjusted'] }}</span>
      <span class="badge muted-badge">Custo alterado: {{ $stats['unit_cost_adjusted'] }}</span>
      <span class="badge muted-badge">Fornecedor alterado: {{ $stats['supplier_adjusted'] }}</span>
    </div>
    <div class="muted">
      Desvio percentual médio absoluto:
      quantidade {{ $stats['average_abs_quantity_delta_percent'] === null ? 'indisponível' : number_format($stats['average_abs_quantity_delta_percent'], 2, ',', '.').'%' }};
      custo {{ $stats['average_abs_unit_cost_delta_percent'] === null ? 'indisponível' : number_format($stats['average_abs_unit_cost_delta_percent'], 2, ',', '.').'%' }}.
    </div>
  </section>

  <section class="panel">
    <div class="panel-body">
      <form method="GET" action="{{ route('purchase-entries.replenishment-purchases') }}" class="form-grid compact-filter-grid">
        <div class="field">
          <label for="replenishment-purchase-q">Produto ou entrada</label>
          <input id="replenishment-purchase-q" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="120" placeholder="Nome do produto ou código">
        </div>
        <div class="field">
          <label for="replenishment-purchase-period">Período analisado</label>
          <select id="replenishment-purchase-period" name="period">
            @foreach($periods as $key => $label)
              <option value="{{ $key }}" @selected(($filters['period'] ?? null) === $key)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="field">
          <label for="replenishment-purchase-classification">Decisão</label>
          <select id="replenishment-purchase-classification" name="classification">
            <option value="">Todas</option>
            @foreach($classifications as $key => $label)
              <option value="{{ $key }}" @selected(($filters['classification'] ?? null) === $key)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="field">
          <label for="replenishment-purchase-status">Status da entrada</label>
          <select id="replenishment-purchase-status" name="status">
            <option value="">Todos</option>
            @foreach($purchaseStatuses as $key => $label)
              <option value="{{ $key }}" @selected(($filters['status'] ?? null) === $key)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="field implementation-portfolio-filter-actions">
          <button type="submit">Filtrar</button>
          <a class="button secondary" href="{{ route('purchase-entries.replenishment-purchases') }}">Limpar</a>
        </div>
      </form>
    </div>
  </section>

  <section class="panel">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Entrada</th>
            <th>Produto</th>
            <th>Decisão</th>
            <th>Quantidade</th>
            <th>Custo unitário</th>
            <th>Fornecedor</th>
            <th>Evidência</th>
          </tr>
        </thead>
        <tbody>
          @forelse($items as $item)
            <tr>
              <td>
                <a href="{{ $item['edit_url'] }}"><strong>{{ $item['entry_code'] }}</strong></a>
                <div><span class="badge {{ $item['entry_status_tone'] }}">{{ $item['entry_status_label'] }}</span></div>
                <div class="muted">{{ $item['entry_date']?->format('d/m/Y') ?? 'Data não informada' }}</div>
              </td>
              <td>
                <strong>{{ $item['product_name'] }}</strong>
                <div class="muted">Unidade: {{ $item['product_unit'] }}</div>
              </td>
              <td><span class="badge {{ $item['classification_tone'] }}">{{ $item['classification_label'] }}</span></td>
              <td>
                <strong>{{ number_format($item['quantity_actual'], 3, ',', '.') }}</strong>
                @if($item['quantity_suggested'] !== null)
                  <div class="muted">Sugerida: {{ number_format($item['quantity_suggested'], 3, ',', '.') }}</div>
                  <div class="muted">
                    Variação: {{ $item['quantity_delta'] >= 0 ? '+' : '' }}{{ number_format($item['quantity_delta'], 3, ',', '.') }}
                    @if($item['quantity_delta_percent'] !== null)
                      ({{ $item['quantity_delta_percent'] >= 0 ? '+' : '' }}{{ number_format($item['quantity_delta_percent'], 2, ',', '.') }}%)
                    @endif
                  </div>
                @else
                  <div class="muted">Sugestão indisponível</div>
                @endif
              </td>
              <td>
                <strong>R$ {{ number_format($item['unit_cost_actual'], 2, ',', '.') }}</strong>
                @if($item['unit_cost_suggested'] !== null)
                  <div class="muted">Sugerido: R$ {{ number_format($item['unit_cost_suggested'], 2, ',', '.') }}</div>
                  <div class="muted">
                    Variação: {{ $item['unit_cost_delta'] >= 0 ? '+' : '' }}R$ {{ number_format($item['unit_cost_delta'], 2, ',', '.') }}
                    @if($item['unit_cost_delta_percent'] !== null)
                      ({{ $item['unit_cost_delta_percent'] >= 0 ? '+' : '' }}{{ number_format($item['unit_cost_delta_percent'], 2, ',', '.') }}%)
                    @endif
                  </div>
                @else
                  <div class="muted">Sugestão indisponível</div>
                @endif
              </td>
              <td>
                <strong>{{ $item['supplier_actual_name'] }}</strong>
                <div class="muted">Sugerido: {{ $item['supplier_suggested_name'] }}</div>
                @if($item['supplier_status'] === 'changed')
                  <span class="badge warning">Alterado</span>
                @elseif($item['supplier_status'] === 'kept')
                  <span class="badge success">Mantido</span>
                @else
                  <span class="badge muted-badge">Sem comparação</span>
                @endif
              </td>
              <td>
                <span class="badge {{ $item['evidence_tone'] }}">{{ $item['evidence_label'] }}</span>
                @if($item['evaluated_at'])
                  <div class="muted">Registrada em {{ $item['evaluated_at']->format('d/m/Y H:i') }}</div>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="muted">Nenhuma decisão de compra encontrada.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="panel-body">{{ $items->links() }}</div>
  </section>
@endsection
