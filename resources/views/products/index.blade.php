@extends('layouts.admin')

@section('title', 'Produtos - VetFlow')

@section('content')
  @php($stats = $intelligenceStats ?? [])
  @php($filters = $activeFilters ?? [])
  @php($statCards = [
    ['label' => 'Produtos', 'value' => $stats['total'] ?? 0, 'url' => route('products.index')],
    ['label' => 'Vinculados', 'value' => $stats['linked'] ?? 0, 'url' => route('products.index', ['intelligence' => 'linked'])],
    ['label' => 'Com EAN sem global', 'value' => $stats['unlinked_with_gtin'] ?? 0, 'url' => route('products.index', ['intelligence' => 'unlinked'])],
    ['label' => 'EAN invalido', 'value' => $stats['invalid_gtin'] ?? 0, 'url' => route('products.index', ['intelligence' => 'invalid_gtin'])],
    ['label' => 'Sem preco', 'value' => $stats['without_price'] ?? 0, 'url' => route('products.index', ['intelligence' => 'without_price'])],
    ['label' => 'Sem imagem', 'value' => $stats['without_image'] ?? 0, 'url' => route('products.index', ['intelligence' => 'without_image'])],
    ['label' => 'Global pendente', 'value' => $stats['global_pending'] ?? 0, 'url' => route('products.index', ['intelligence' => 'global_pending'])],
    ['label' => 'Conflito global', 'value' => $stats['global_conflict'] ?? 0, 'url' => route('products.index', ['intelligence' => 'global_conflict'])],
  ])

  <header class="topbar">
    <div>
      <h1>Produtos</h1>
      <p>Catalogo comercial da clinica conectado ao VetFlow Intelligence.</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('products.diagnostics') }}">Diagnostico</a>
      <a class="button secondary" href="{{ route('global-products.index') }}">Catalogo global</a>
      <a class="button" href="{{ route('products.create') }}">Novo produto</a>
    </div>
  </header>

  <section class="grid stats inventory-lot-stats">
    @foreach($statCards as $card)
      <a class="stat stat-link" href="{{ $card['url'] }}">
        <span>{{ $card['label'] }}</span>
        <strong>{{ $card['value'] }}</strong>
      </a>
    @endforeach
  </section>

  <section class="panel">
    <div class="panel-heading">
      <div>
        <h2>Filtros inteligentes</h2>
        <p>Clique nos numeros acima ou refine a busca por problema, marca e categoria.</p>
      </div>
      <span class="badge muted-badge">Cobertura global {{ number_format((float) ($stats['coverage_percent'] ?? 0), 1, ',', '.') }}%</span>
    </div>
    <div class="panel-body">
      <form class="filter-grid" action="{{ route('products.index') }}" method="GET">
        <div class="field">
          <label for="q">Busca</label>
          <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Nome, EAN, SKU, marca">
        </div>
        <div class="field">
          <label for="intelligence">Situacao</label>
          <select id="intelligence" name="intelligence">
            <option value="">Todas</option>
            @foreach($intelligenceOptions ?? [] as $value => $label)
              <option value="{{ $value }}" @selected(($filters['intelligence'] ?? null) === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="field">
          <label for="category">Categoria</label>
          <select id="category" name="category">
            <option value="">Todas</option>
            @foreach(($filterOptions['categories'] ?? []) as $category)
              <option value="{{ $category }}" @selected(($filters['category'] ?? null) === $category)>{{ $category }}</option>
            @endforeach
          </select>
        </div>
        <div class="field">
          <label for="brand">Marca</label>
          <select id="brand" name="brand">
            <option value="">Todas</option>
            @foreach(($filterOptions['brands'] ?? []) as $brand)
              <option value="{{ $brand }}" @selected(($filters['brand'] ?? null) === $brand)>{{ $brand }}</option>
            @endforeach
          </select>
        </div>
        <div class="field">
          <label for="status">Status</label>
          <select id="status" name="status">
            <option value="">Todos</option>
            <option value="active" @selected(($filters['status'] ?? null) === 'active')>Ativos</option>
            <option value="inactive" @selected(($filters['status'] ?? null) === 'inactive')>Inativos</option>
          </select>
        </div>
        <div class="filter-actions">
          <button type="submit">Filtrar</button>
          <a class="button secondary" href="{{ route('products.index') }}">Limpar</a>
        </div>
      </form>
    </div>
  </section>

  @if(! empty($intelligenceActions))
    <section class="panel nested-panel">
      <div class="panel-heading">
        <div>
          <h2>Acoes recomendadas</h2>
          <p>Atalhos para corrigir os pontos que mais afetam PDV, estoque e leitura de codigo.</p>
        </div>
      </div>
      <div class="panel-body">
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Acao</th>
                <th>Qtd</th>
                <th>Atalho</th>
              </tr>
            </thead>
            <tbody>
              @foreach($intelligenceActions as $action)
                @php($badge = $action['level'] === 'danger' ? 'danger' : ($action['level'] === 'warning' ? 'warning' : 'muted-badge'))
                <tr>
                  <td>
                    <a href="{{ $action['url'] }}">
                      <strong>{{ $action['title'] }}</strong>
                      <div class="muted">{{ $action['description'] }}</div>
                    </a>
                  </td>
                  <td><a class="badge {{ $badge }}" href="{{ $action['url'] }}">{{ $action['count'] }}</a></td>
                  <td>
                    <div class="actions">
                      <a class="button secondary" href="{{ $action['url'] }}">Ver lista</a>
                      <a class="button secondary" href="{{ $action['diagnostics_url'] }}">Diagnostico</a>
                    </div>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </section>
  @endif

  <section class="panel nested-panel">
    <div class="table-wrap">
      <table class="products-table">
        <thead>
          <tr>
            <th>Produto</th>
            <th>Inteligencia</th>
            <th>Global</th>
            <th>Categoria</th>
            <th>Estoque</th>
            <th>Venda</th>
            <th>Status</th>
            <th>Acoes</th>
          </tr>
        </thead>
        <tbody>
          @forelse($products as $product)
            @php($levelBadge = match ($product->intelligence_level ?? 'info') {
              'danger' => 'danger',
              'warning' => 'warning',
              'success' => 'success',
              default => 'muted-badge',
            })
            <tr>
              <td>
                <div class="product-list-item">
                  @if($product->image_path)
                    <img class="product-thumb" src="{{ route('products.lookup-image', ['filename' => basename($product->image_path)]) }}" alt="Foto de {{ $product->name }}">
                  @else
                    <div class="product-thumb product-thumb-empty">Sem foto</div>
                  @endif
                  <div>
                    <strong>{{ $product->name }}</strong>
                    <div class="muted">{{ $product->sku ?: 'Sem SKU' }}</div>
                    <div class="muted">{{ $product->intelligence_gtin ?: 'Sem EAN/GTIN' }}</div>
                  </div>
                </div>
              </td>
              <td>
                <div class="badge-list">
                  <span class="badge {{ $levelBadge }}">{{ $product->intelligence_label ?? 'Ok' }}</span>
                  @forelse($product->intelligence_flags ?? [] as $flag)
                    @php($flagBadge = $flag['level'] === 'danger' ? 'danger' : ($flag['level'] === 'warning' ? 'warning' : 'muted-badge'))
                    <a class="badge {{ $flagBadge }}" href="{{ $flag['url'] }}" title="{{ $flag['description'] }}">{{ $flag['label'] }}</a>
                  @empty
                    <span class="badge success">Sem pendencias</span>
                  @endforelse
                </div>
              </td>
              <td>
                @if($product->globalProduct)
                  <a href="{{ route('global-products.show', $product->globalProduct->id) }}">
                    <strong>#{{ $product->globalProduct->id }}</strong>
                    <div class="muted">{{ $product->global_status_label }}</div>
                    <div class="muted">{{ number_format((float) $product->globalProduct->source_confidence, 1, ',', '.') }}% confianca</div>
                  </a>
                @else
                  <span class="muted">Sem vinculo</span>
                @endif
              </td>
              <td>{{ $product->category ?: '-' }}</td>
              <td>{{ number_format((float) $product->stock_quantity, 3, ',', '.') }} {{ $product->unit }}</td>
              <td>R$ {{ number_format((float) $product->sale_price, 2, ',', '.') }}</td>
              <td>{{ $product->active ? 'Ativo' : 'Inativo' }}</td>
              <td>
                <div class="row-actions">
                  <a class="button secondary" href="{{ route('products.edit', $product->id) }}">Editar</a>

                  @if($product->intelligence_gtin && ! $product->globalProduct)
                    <form class="inline" action="{{ route('products.link-global', $product->id) }}" method="POST">
                      @csrf
                      <button class="secondary" type="submit">Vincular</button>
                    </form>
                  @endif

                  @if($product->intelligence_gtin)
                    <form class="inline" action="{{ route('products.enrich', $product->id) }}" method="POST">
                      @csrf
                      <button class="secondary" type="submit">Consultar EAN</button>
                    </form>
                  @endif

                  @if($product->globalProduct)
                    <form class="inline" action="{{ route('products.sync-global', $product->id) }}" method="POST">
                      @csrf
                      <button class="secondary" type="submit">Sincronizar</button>
                    </form>
                  @endif

                  <form class="inline" action="{{ route('products.destroy', $product->id) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <button class="danger" type="submit" data-confirm="Remover este produto?">Excluir</button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="muted">Nenhum produto cadastrado.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>

  {{ $products->links() }}
@endsection
