@extends('layouts.admin')

@section('title', 'Curva ABC de produtos - VetFlow')

@section('content')
  @php
    $money = fn ($value) => 'R$ '.number_format((float) $value, 2, ',', '.');
    $percent = fn ($value) => number_format((float) $value, 2, ',', '.').'%';
    $quantity = fn ($value) => number_format((float) $value, 3, ',', '.');
  @endphp

  <header class="topbar">
    <div>
      <h1>Curva ABC de produtos</h1>
      <p>Participação dos produtos na receita líquida, depois das devoluções, com o estoque atual como contexto.</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('sales.profitability') }}">Rentabilidade</a>
      @can('inventory.manage')
        <a class="button secondary" href="{{ route('inventory-movements.radar') }}">Radar de estoque</a>
      @endcan
      <a class="button" href="{{ route('sales.index') }}">Vendas</a>
    </div>
  </header>

  <div class="alert-soft">
    <strong>Leitura observacional</strong>
    <span>A classificação usa vendas concluídas ou devolvidas no período. Ela não altera preço, compra, fornecedor ou estoque automaticamente.</span>
  </div>

  <section class="grid stats inventory-lot-stats">
    <a class="stat stat-link" href="{{ route('sales.product-abc', ['period' => $period['value']]) }}">
      <span>Produtos analisados</span>
      <strong>{{ $stats['total_products'] }}</strong>
      <div class="muted">{{ $money($stats['net_revenue']) }} de receita líquida</div>
    </a>
    @foreach($stats['classes'] as $class => $classStats)
      <a class="stat stat-link" href="{{ route('sales.product-abc', ['period' => $period['value'], 'class' => $class]) }}">
        <span>{{ $classStats['label'] }}</span>
        <strong>{{ $classStats['count'] }}</strong>
        <div class="muted">{{ $percent($classStats['revenue_share_percent']) }} da receita · {{ $money($classStats['stock_value']) }} em estoque</div>
      </a>
    @endforeach
  </section>

  <section class="panel">
    <div class="panel-heading">
      <div>
        <h2>Como as faixas são calculadas</h2>
        <p>Os produtos são ordenados pela receita líquida realizada. O item que cruza um limite conclui a faixa em que começou.</p>
      </div>
      <span class="badge muted-badge">{{ $period['label'] }}</span>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Classe</th><th>Referência inicial</th><th>Uso recomendado</th></tr>
        </thead>
        <tbody>
          <tr>
            <td><span class="badge success">Classe A</span></td>
            <td>Primeira faixa, iniciada antes de 80% da receita acumulada.</td>
            <td>Priorizar disponibilidade e revisar rupturas com maior atenção.</td>
          </tr>
          <tr>
            <td><span class="badge warning">Classe B</span></td>
            <td>Faixa seguinte, iniciada entre 80% e 95% da receita acumulada.</td>
            <td>Acompanhar reposição e evolução do giro.</td>
          </tr>
          <tr>
            <td><span class="badge muted-badge">Classe C</span></td>
            <td>Faixa final, iniciada a partir de 95%; receita zero também fica aqui.</td>
            <td>Revisar sortimento, excesso e necessidade de manter saldo.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>

  <section class="panel nested-panel">
    <div class="panel-heading">
      <div>
        <h2>Filtros</h2>
        <p>Os filtros não recalculam a curva; a classificação sempre considera a carteira completa do período.</p>
      </div>
      <span class="badge muted-badge">{{ $items->total() }} resultado(s)</span>
    </div>
    <div class="panel-body">
      <form class="filter-grid" action="{{ route('sales.product-abc') }}" method="GET">
        <div class="field">
          <label for="product-abc-period">Período</label>
          <select id="product-abc-period" name="period">
            @foreach($periods as $value => $label)
              <option value="{{ $value }}" @selected($period['value'] === (string) $value)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="field">
          <label for="product-abc-class">Classe</label>
          <select id="product-abc-class" name="class">
            <option value="">Todas</option>
            @foreach($classes as $value => $definition)
              <option value="{{ $value }}" @selected(($filters['class'] ?? null) === $value)>{{ $definition['label'] }}</option>
            @endforeach
          </select>
        </div>
        <div class="field">
          <label for="product-abc-category">Categoria</label>
          <select id="product-abc-category" name="category">
            <option value="">Todas</option>
            @foreach($filterOptions['categories'] as $category)
              <option value="{{ $category }}" @selected(($filters['category'] ?? null) === $category)>{{ $category }}</option>
            @endforeach
          </select>
        </div>
        <div class="field">
          <label for="product-abc-q">Busca</label>
          <input id="product-abc-q" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="120" placeholder="Produto, SKU, EAN, marca">
        </div>
        <div class="filter-actions">
          <button type="submit">Filtrar</button>
          <a class="button secondary" href="{{ route('sales.product-abc') }}">Limpar</a>
        </div>
      </form>
    </div>
  </section>

  <section class="grid stats">
    <div class="stat"><span>Vendas analisadas</span><strong>{{ $stats['sales_count'] }}</strong></div>
    <div class="stat"><span>Unidades líquidas</span><strong>{{ $quantity($stats['net_quantity']) }}</strong></div>
    <div class="stat"><span>Devoluções</span><strong>{{ $money($stats['returns']) }}</strong></div>
    <div class="stat"><span>Valor atual em estoque</span><strong>{{ $money($stats['stock_value']) }}</strong></div>
  </section>

  <section class="panel nested-panel">
    <div class="panel-heading">
      <div>
        <h2>Produtos por participação</h2>
        <p>{{ date('d/m/Y', strtotime($period['from'])) }} a {{ date('d/m/Y', strtotime($period['to'])) }} · receita e quantidade líquidas após devoluções.</p>
      </div>
    </div>
    <div class="table-wrap">
      <table class="products-table">
        <thead>
          <tr>
            <th>Posição</th>
            <th>Classe</th>
            <th>Produto</th>
            <th>Vendas / unidades</th>
            <th>Receita líquida</th>
            <th>Participação</th>
            <th>Acumulado</th>
            <th>Estoque atual</th>
            <th>Valor em estoque</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          @forelse($items as $item)
            <tr>
              <td><strong>#{{ $item['rank'] }}</strong></td>
              <td><span class="badge {{ $item['class_tone'] }}">{{ $item['abc_class'] }}</span></td>
              <td>
                <strong>{{ $item['description'] }}</strong>
                <div class="muted">{{ $item['category'] }}</div>
                @if(auth()->user()?->clinic_id === null)
                  <div class="muted">{{ $item['clinic_name'] ?? '-' }}</div>
                @endif
                @if(! $item['product'])
                  <span class="badge muted-badge">Fora do catálogo atual</span>
                @elseif(! $item['product']->active)
                  <span class="badge muted-badge">Produto inativo</span>
                @endif
              </td>
              <td>
                <strong>{{ $item['sales_count'] }} venda(s)</strong>
                <div class="muted">{{ $quantity($item['net_quantity']) }} unidade(s) líquida(s)</div>
              </td>
              <td>
                <strong>{{ $money($item['net_revenue']) }}</strong>
                @if((float) $item['returns'] > 0)
                  <div class="muted">{{ $money($item['returns']) }} devolvido</div>
                @endif
              </td>
              <td>{{ $percent($item['participation_percent']) }}</td>
              <td>{{ $percent($item['cumulative_percent']) }}</td>
              <td>{{ $quantity($item['stock_quantity']) }} {{ $item['product']?->unit ?: 'un' }}</td>
              <td><strong>{{ $money($item['stock_value']) }}</strong></td>
              <td>
                @if($item['product'])
                  @can('products.manage')
                    <a class="button secondary" href="{{ route('products.edit', $item['product']->id) }}">Produto</a>
                  @endcan
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="10" class="muted">Nenhum produto vendido para os filtros informados.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>

  {{ $items->appends(request()->query())->links() }}
@endsection
