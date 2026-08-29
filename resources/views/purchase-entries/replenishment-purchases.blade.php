@extends('layouts.admin')

@section('title', 'Decisões de compra da reposição - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Decisões de compra da reposição</h1>
      <p>Compare o que o VetFlow sugeriu com a quantidade, o custo e o fornecedor registrados pela equipe.</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('purchase-entries.replenishment-purchases.report', ['period' => $stats['period']]) }}">Baixar relatório JSON</a>
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
    <div class="panel-heading">
      <div>
        <h2>Maturidade da amostra do piloto</h2>
        <p>Referência operacional inicial para decidir quando revisar os resultados com segurança.</p>
      </div>
      <span class="badge {{ $stats['maturity']['status_tone'] }}">{{ $stats['maturity']['status_label'] }}</span>
    </div>
    <div class="panel-body">
      <div class="grid stats inventory-lot-stats">
        <div class="stat">
          <span>Decisões comparáveis</span>
          <strong>{{ $stats['maturity']['criteria']['decisions']['current'] }} / {{ $stats['maturity']['criteria']['decisions']['target'] }}</strong>
        </div>
        <div class="stat">
          <span>Produtos comparáveis</span>
          <strong>{{ $stats['maturity']['criteria']['products']['current'] }} / {{ $stats['maturity']['criteria']['products']['target'] }}</strong>
        </div>
        <div class="stat">
          <span>Evidência válida</span>
          <strong>
            {{ $stats['maturity']['criteria']['evidence']['current'] === null ? '—' : number_format($stats['maturity']['criteria']['evidence']['current'], 1, ',', '.').'%' }}
            / {{ number_format($stats['maturity']['criteria']['evidence']['target'], 0, ',', '.') }}%
          </strong>
        </div>
        <div class="stat">
          <span>Motivos registrados</span>
          <strong>
            {{ $stats['maturity']['criteria']['reasons']['current'] === null ? 'Não se aplica' : number_format($stats['maturity']['criteria']['reasons']['current'], 1, ',', '.').'%' }}
            @if($stats['maturity']['criteria']['reasons']['current'] !== null)
              / {{ number_format($stats['maturity']['criteria']['reasons']['target'], 0, ',', '.') }}%
            @endif
          </strong>
        </div>
      </div>
      <div class="intelligence-health">
        <div>
          <strong>{{ $stats['maturity']['criteria_met'] }} de {{ $stats['maturity']['criteria_total'] }} critérios atendidos</strong>
          <span>{{ $stats['maturity']['next_action'] }}</span>
        </div>
      </div>
      <p class="muted">Esta referência organiza a validação do piloto; não comprova significância estatística, não aprova fornecedor e não altera sugestões automaticamente.</p>
    </div>
  </section>

  <section class="panel">
    <div class="panel-heading">
      <div>
        <h2>Divergências por produto</h2>
        <p>Produtos com mais ajustes no período selecionado, limitados aos 10 primeiros.</p>
      </div>
      <span class="badge muted-badge">{{ $stats['product_count'] }} produto(s) analisado(s)</span>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Produto</th>
            <th>Decisões</th>
            <th>Resultado comparável</th>
            <th>Adesão</th>
            <th>Campos alterados</th>
            <th>Desvio médio absoluto</th>
          </tr>
        </thead>
        <tbody>
          @forelse($stats['products'] as $product)
            <tr>
              <td>
                <strong>{{ $product['name'] }}</strong>
                <div>
                  <a href="{{ route('purchase-entries.replenishment-purchases', ['period' => $stats['period'], 'q' => $product['name']]) }}">Ver decisões</a>
                </div>
              </td>
              <td>
                <strong>{{ $product['total'] }}</strong>
                @if($product['unavailable'] > 0)
                  <div class="muted">{{ $product['unavailable'] }} sem comparação</div>
                @endif
              </td>
              <td>
                <span class="badge success">{{ $product['kept'] }} mantida(s)</span>
                <span class="badge warning">{{ $product['adjusted'] }} ajustada(s)</span>
              </td>
              <td>
                <strong>{{ $product['adherence_percent'] === null ? '—' : number_format($product['adherence_percent'], 1, ',', '.').'%' }}</strong>
                @if($product['adjustment_rate_percent'] !== null)
                  <div class="muted">Ajustes: {{ number_format($product['adjustment_rate_percent'], 1, ',', '.') }}%</div>
                @endif
              </td>
              <td>
                <div class="badge-list">
                  <span class="badge muted-badge">Qtd: {{ $product['quantity_adjusted'] }}</span>
                  <span class="badge muted-badge">Custo: {{ $product['unit_cost_adjusted'] }}</span>
                  <span class="badge muted-badge">Fornecedor: {{ $product['supplier_adjusted'] }}</span>
                </div>
              </td>
              <td>
                <div>Qtd: {{ $product['average_abs_quantity_delta_percent'] === null ? '—' : number_format($product['average_abs_quantity_delta_percent'], 2, ',', '.').'%' }}</div>
                <div>Custo: {{ $product['average_abs_unit_cost_delta_percent'] === null ? '—' : number_format($product['average_abs_unit_cost_delta_percent'], 2, ',', '.').'%' }}</div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="muted">Nenhum produto com decisão de compra no período selecionado.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
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
            <th>Motivo do ajuste</th>
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
                @if($item['adjustment_reason_label'])
                  <strong>{{ $item['adjustment_reason_label'] }}</strong>
                  @if($item['adjustment_reason_note'])
                    <div class="muted">{{ $item['adjustment_reason_note'] }}</div>
                  @endif
                @elseif($item['classification'] === 'adjusted')
                  <span class="badge warning">Motivo não registrado</span>
                @else
                  <span class="muted">Não se aplica</span>
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
              <td colspan="8" class="muted">Nenhuma decisão de compra encontrada.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="panel-body">{{ $items->links() }}</div>
  </section>
@endsection
