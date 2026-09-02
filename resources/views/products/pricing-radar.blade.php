@extends('layouts.admin')

@section('title', 'Radar de preços - VetFlow')

@section('content')
  @php
    $money = fn ($value) => 'R$ '.number_format((float) $value, 2, ',', '.');
    $percent = fn ($value) => $value === null ? '-' : number_format((float) $value, 2, ',', '.').'%';
    $quantity = fn ($value) => number_format((float) $value, 3, ',', '.');
  @endphp

  <header class="topbar">
    <div>
      <h1>Radar de preços</h1>
      <p>Diagnóstico do preço cadastrado, margem bruta unitária e exposição financeira do estoque atual.</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('products.diagnostics') }}">Diagnóstico cadastral</a>
      @can('sales.manage')
        <a class="button secondary" href="{{ route('sales.profitability') }}">Rentabilidade realizada</a>
      @endcan
      <a class="button" href="{{ route('products.index') }}">Produtos</a>
    </div>
  </header>

  <div class="alert-soft">
    <strong>Margem cadastral, não realizada</strong>
    <span>O cálculo compara o custo e o preço atuais do produto. Não inclui impostos, descontos, comissões, despesas ou o custo histórico gravado nas vendas.</span>
  </div>

  <section class="grid stats inventory-lot-stats">
    <a class="stat stat-link" href="{{ route('products.pricing-radar') }}">
      <span>Produtos ativos</span>
      <strong>{{ $stats['total'] }}</strong>
      <div class="muted">{{ $money($stats['stock_value']) }} ao custo</div>
    </a>
    @foreach($stats['signals'] as $signal => $signalStats)
      <a class="stat stat-link" href="{{ route('products.pricing-radar', ['signal' => $signal]) }}">
        <span>{{ $signalStats['label'] }}</span>
        <strong>{{ $signalStats['count'] }}</strong>
        <div class="muted">{{ $money($signalStats['stock_value']) }} ao custo</div>
      </a>
    @endforeach
  </section>

  <section class="panel">
    <div class="panel-heading">
      <div>
        <h2>Regras transparentes</h2>
        <p>Cada produto ativo recebe um único sinal, seguindo esta ordem de prioridade.</p>
      </div>
      <span class="badge muted-badge">Referência inicial: {{ number_format($lowMarginPercent, 0, ',', '.') }}%</span>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Sinal</th><th>Regra visível</th></tr></thead>
        <tbody>
          @foreach($signals as $definition)
            <tr>
              <td><span class="badge {{ $definition['tone'] }}">{{ $definition['label'] }}</span></td>
              <td>{{ $definition['description'] }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>

  <section class="panel nested-panel">
    <div class="panel-heading">
      <div>
        <h2>Filtros</h2>
        <p>Os totais dos cartões sempre representam todo o catálogo ativo da clínica.</p>
      </div>
      <span class="badge muted-badge">{{ $items->total() }} resultado(s)</span>
    </div>
    <div class="panel-body">
      <form class="filter-grid" action="{{ route('products.pricing-radar') }}" method="GET">
        <div class="field">
          <label for="pricing-radar-q">Busca</label>
          <input id="pricing-radar-q" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="120" placeholder="Produto, SKU, EAN, marca">
        </div>
        <div class="field">
          <label for="pricing-radar-signal">Sinal</label>
          <select id="pricing-radar-signal" name="signal">
            <option value="">Todos</option>
            @foreach($signals as $value => $definition)
              <option value="{{ $value }}" @selected(($filters['signal'] ?? null) === $value)>{{ $definition['label'] }}</option>
            @endforeach
          </select>
        </div>
        <div class="field">
          <label for="pricing-radar-category">Categoria</label>
          <select id="pricing-radar-category" name="category">
            <option value="">Todas</option>
            @foreach($filterOptions['categories'] as $category)
              <option value="{{ $category }}" @selected(($filters['category'] ?? null) === $category)>{{ $category }}</option>
            @endforeach
          </select>
        </div>
        <div class="field">
          <label for="pricing-radar-brand">Marca</label>
          <select id="pricing-radar-brand" name="brand">
            <option value="">Todas</option>
            @foreach($filterOptions['brands'] as $brand)
              <option value="{{ $brand }}" @selected(($filters['brand'] ?? null) === $brand)>{{ $brand }}</option>
            @endforeach
          </select>
        </div>
        <div class="filter-actions">
          <button type="submit">Filtrar</button>
          <a class="button secondary" href="{{ route('products.pricing-radar') }}">Limpar</a>
        </div>
      </form>
    </div>
  </section>

  <section class="grid stats">
    <div class="stat"><span>Margem calculável</span><strong>{{ $stats['known_margin_products'] }} / {{ $stats['total'] }}</strong></div>
    <div class="stat"><span>Receita potencial do saldo</span><strong>{{ $money($stats['projected_revenue']) }}</strong></div>
    <div class="stat"><span>Margem bruta potencial conhecida</span><strong>{{ $money($stats['projected_gross_profit']) }}</strong></div>
  </section>

  <section class="panel nested-panel">
    <div class="table-wrap">
      <table class="products-table">
        <thead>
          <tr>
            <th>Sinal</th>
            <th>Produto</th>
            <th>Custo</th>
            <th>Preço</th>
            <th>Margem unitária</th>
            <th>Margem / markup</th>
            <th>Estoque</th>
            <th>Valor ao custo</th>
            <th>Receita potencial</th>
            <th>Margem potencial</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          @forelse($items as $item)
            @php($product = $item['product'])
            <tr>
              <td>
                <span class="badge {{ $item['signal_tone'] }}">{{ $item['signal_label'] }}</span>
                <div class="muted">{{ $item['signal_description'] }}</div>
              </td>
              <td>
                <strong>{{ $product->name }}</strong>
                <div class="muted">{{ $product->sku ?: 'Sem SKU' }}</div>
                <div class="muted">{{ $product->category ?: 'Sem categoria' }}{{ $product->brand ? ' · '.$product->brand : '' }}</div>
              </td>
              <td>{{ $money($product->cost_price) }}</td>
              <td>{{ $money($product->sale_price) }}</td>
              <td>
                @if($item['margin_amount'] !== null)
                  <strong>{{ $money($item['margin_amount']) }}</strong>
                @else
                  <span class="muted">Sem cálculo</span>
                @endif
              </td>
              <td>
                <strong>{{ $percent($item['margin_percent']) }}</strong>
                <div class="muted">Markup: {{ $percent($item['markup_percent']) }}</div>
              </td>
              <td>{{ $quantity($product->stock_quantity) }} {{ $product->unit ?: 'un' }}</td>
              <td>{{ $money($item['stock_value']) }}</td>
              <td>{{ $money($item['projected_revenue']) }}</td>
              <td>
                @if($item['projected_gross_profit'] !== null)
                  <strong>{{ $money($item['projected_gross_profit']) }}</strong>
                @else
                  <span class="muted">Sem cálculo</span>
                @endif
              </td>
              <td><a class="button secondary" href="{{ route('products.edit', $product->id) }}">Revisar</a></td>
            </tr>
          @empty
            <tr><td colspan="11" class="muted">Nenhum produto encontrado para os filtros informados.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>

  {{ $items->appends(request()->query())->links() }}
@endsection
