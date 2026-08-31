@extends('layouts.admin')

@section('title', 'Radar de estoque - VetFlow')

@section('content')
  @php($money = fn ($value) => 'R$ '.number_format((float) $value, 2, ',', '.'))
  @php($quantity = fn ($value) => number_format((float) $value, 3, ',', '.'))

  <header class="topbar">
    <div>
      <h1>Radar de estoque</h1>
      <p>Visão financeira e operacional do saldo atual com sinais explicáveis de demanda e cobertura.</p>
    </div>
    <div class="actions">
      @can('purchase-entries.manage')
        <a class="button secondary" href="{{ route('purchase-entries.replenishment') }}">Reposição inteligente</a>
      @endcan
      <a class="button secondary" href="{{ route('inventory-movements.alerts') }}">Alertas</a>
      <a class="button" href="{{ route('inventory-movements.index') }}">Movimentações</a>
    </div>
  </header>

  <section class="grid stats inventory-lot-stats">
    <a class="stat stat-link" href="{{ route('inventory-movements.radar') }}">
      <span>Todos os produtos</span>
      <strong>{{ $stats['total'] ?? 0 }}</strong>
      <div class="muted">{{ $money($stats['stock_value'] ?? 0) }} ao custo</div>
    </a>
    @foreach(($stats['categories'] ?? []) as $key => $categoryStats)
      <a class="stat stat-link" href="{{ route('inventory-movements.radar', ['category' => $key]) }}">
        <span>{{ $categoryStats['label'] }}</span>
        <strong>{{ $categoryStats['count'] }}</strong>
        <div class="muted">{{ $money($categoryStats['stock_value']) }} ao custo</div>
      </a>
    @endforeach
  </section>

  <section class="panel">
    <div class="panel-heading">
      <div>
        <h2>Leitura observacional</h2>
        <p>Os sinais ajudam a priorizar a revisão; nenhum deles movimenta estoque ou cria uma compra automaticamente.</p>
      </div>
      <span class="badge muted-badge">Regra inicial transparente</span>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Sinal</th>
            <th>Regra visivel</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><span class="badge danger">Repor</span></td>
            <td>Estoque atual no mínimo configurado ou abaixo dele.</td>
          </tr>
          <tr>
            <td><span class="badge muted-badge">Novos</span></td>
            <td>Cadastro criado nos últimos {{ $newProductDays }} dias, desde que não esteja em reposição.</td>
          </tr>
          <tr>
            <td><span class="badge warning">Sem saída recente</span></td>
            <td>Saldo positivo sem demanda líquida em vendas concluídas nos últimos {{ $demandWindowDays }} dias, depois das devoluções.</td>
          </tr>
          <tr>
            <td><span class="badge warning">Cobertura alta</span></td>
            <td>Mais de {{ $highCoverageDays }} dias de cobertura pelo ritmo líquido observado nos últimos {{ $demandWindowDays }} dias.</td>
          </tr>
          <tr>
            <td><span class="badge muted-badge">Sem mínimo</span></td>
            <td>Produto sem estoque mínimo configurado e sem outro sinal prioritário.</td>
          </tr>
          <tr>
            <td><span class="badge success">Adequado</span></td>
            <td>Produto parametrizado sem outro sinal prioritário nesta leitura.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>

  <section class="panel nested-panel">
    <div class="panel-heading">
      <div>
        <h2>Filtros</h2>
        <p>Refine a carteira sem alterar os totais consolidados dos cartões.</p>
      </div>
      <span class="badge muted-badge">{{ $items->total() }} resultado(s)</span>
    </div>
    <div class="panel-body">
      <form class="filter-grid" action="{{ route('inventory-movements.radar') }}" method="GET">
        <div class="field">
          <label for="stock-radar-q">Busca</label>
          <input id="stock-radar-q" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="120" placeholder="Nome, SKU, EAN, marca">
        </div>
        <div class="field">
          <label for="stock-radar-category">Sinal</label>
          <select id="stock-radar-category" name="category">
            <option value="">Todos</option>
            @foreach($categories as $key => $definition)
              <option value="{{ $key }}" @selected(($filters['category'] ?? null) === $key)>{{ $definition['label'] }}</option>
            @endforeach
          </select>
        </div>
        <div class="field">
          <label for="stock-radar-product-category">Categoria</label>
          <select id="stock-radar-product-category" name="product_category">
            <option value="">Todas</option>
            @foreach(($filterOptions['product_categories'] ?? []) as $productCategory)
              <option value="{{ $productCategory }}" @selected(($filters['product_category'] ?? null) === $productCategory)>{{ $productCategory }}</option>
            @endforeach
          </select>
        </div>
        <div class="field">
          <label for="stock-radar-brand">Marca</label>
          <select id="stock-radar-brand" name="brand">
            <option value="">Todas</option>
            @foreach(($filterOptions['brands'] ?? []) as $brand)
              <option value="{{ $brand }}" @selected(($filters['brand'] ?? null) === $brand)>{{ $brand }}</option>
            @endforeach
          </select>
        </div>
        <div class="filter-actions">
          <button type="submit">Filtrar</button>
          <a class="button secondary" href="{{ route('inventory-movements.radar') }}">Limpar</a>
        </div>
      </form>
    </div>
  </section>

  <section class="panel nested-panel">
    <div class="table-wrap">
      <table class="products-table">
        <thead>
          <tr>
            <th>Sinal</th>
            <th>Produto</th>
            <th>Estoque / mínimo</th>
            <th>Demanda líquida</th>
            <th>Cobertura</th>
            <th>Custo unitário</th>
            <th>Valor em estoque</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          @forelse($items as $item)
            @php($product = $item['product'])
            <tr>
              <td>
                <span class="badge {{ $item['category_tone'] }}">{{ $item['category_label'] }}</span>
                <div class="muted">{{ $item['category_description'] }}</div>
              </td>
              <td>
                <strong>{{ $product->name }}</strong>
                <div class="muted">{{ $product->sku ?: 'Sem SKU' }}</div>
                <div class="muted">{{ $product->category ?: 'Sem categoria' }}{{ $product->brand ? ' · '.$product->brand : '' }}</div>
              </td>
              <td>
                <strong>{{ $quantity($product->stock_quantity) }} {{ $product->unit }}</strong>
                <div class="muted">Mínimo: {{ $quantity($product->minimum_stock) }} {{ $product->unit }}</div>
              </td>
              <td>
                <strong>{{ $quantity($item['net_demand']) }} {{ $product->unit }}</strong>
                <div class="muted">{{ $item['demand_signal']['sales_count'] ?? 0 }} venda(s) em {{ $demandWindowDays }} dias</div>
              </td>
              <td>
                @if($item['coverage_days'] !== null)
                  <strong>{{ number_format((float) $item['coverage_days'], 1, ',', '.') }} dias</strong>
                @else
                  <span class="muted">Sem comparação</span>
                @endif
              </td>
              <td>{{ $money($product->cost_price) }}</td>
              <td><strong>{{ $money($item['stock_value']) }}</strong></td>
              <td>
                <div class="row-actions">
                  @can('products.manage')
                    <a class="button secondary" href="{{ route('products.edit', $product->id) }}">Produto</a>
                  @endcan
                  @if($item['category'] === 'replenish')
                    @can('purchase-entries.manage')
                      <a class="button secondary" href="{{ route('purchase-entries.replenishment') }}#replenishment-product-{{ $product->id }}">Repor</a>
                    @endcan
                  @endif
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="muted">Nenhum produto encontrado para os filtros informados.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>

  {{ $items->appends(request()->query())->links() }}
@endsection
