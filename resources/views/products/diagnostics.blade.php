@extends('layouts.admin')

@section('title', 'Diagnostico de Produtos - VetFlow')

@section('content')
  @php($stats = $intelligenceStats ?? [])
  @php($filters = $activeFilters ?? [])
  @php($statCards = [
    ['label' => 'Cobertura global', 'value' => number_format((float) ($stats['coverage_percent'] ?? 0), 1, ',', '.').'%'],
    ['label' => 'Produtos locais', 'value' => $stats['total'] ?? 0],
    ['label' => 'Sem global', 'value' => $stats['unlinked_with_gtin'] ?? 0],
    ['label' => 'Pendencias criticas', 'value' => ($stats['global_conflict'] ?? 0) + ($stats['invalid_gtin'] ?? 0) + ($stats['low_stock'] ?? 0)],
  ])

  <header class="topbar">
    <div>
      <h1>Diagnostico de produtos</h1>
      <p>Mapa de qualidade dos cadastros locais e do vinculo com o Catalogo Global.</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('products.index') }}">Produtos</a>
      <a class="button secondary" href="{{ route('global-products.index') }}">Catalogo global</a>
      <a class="button" href="{{ route('products.create') }}">Novo produto</a>
    </div>
  </header>

  <section class="grid stats inventory-lot-stats">
    @foreach($statCards as $card)
      <div class="stat">
        <span>{{ $card['label'] }}</span>
        <strong>{{ $card['value'] }}</strong>
      </div>
    @endforeach
  </section>

  <section class="panel">
    <div class="panel-heading">
      <div>
        <h2>Prioridades</h2>
        <p>Use estes atalhos para entrar no problema sem procurar produto por produto.</p>
      </div>
    </div>
    <div class="panel-body">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Prioridade</th>
              <th>Qtd</th>
              <th>Destino</th>
            </tr>
          </thead>
          <tbody>
            @forelse($intelligenceActions ?? [] as $action)
              @php($badge = $action['level'] === 'danger' ? 'danger' : ($action['level'] === 'warning' ? 'warning' : 'muted-badge'))
              <tr>
                <td>
                  <a href="{{ $action['url'] }}">
                    <strong>{{ $action['title'] }}</strong>
                    <div class="muted">{{ $action['description'] }}</div>
                  </a>
                </td>
                <td><a class="badge {{ $badge }}" href="{{ $action['url'] }}">{{ $action['count'] }}</a></td>
                <td><a class="button secondary" href="{{ $action['url'] }}">Abrir produtos</a></td>
              </tr>
            @empty
              <tr>
                <td colspan="3" class="muted">Nenhuma prioridade aberta no momento.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <section class="content-grid nested-panel">
    <div class="panel">
      <div class="panel-heading">
        <div>
          <h2>Produtos afetados</h2>
          <p>Lista filtrada do diagnostico atual.</p>
        </div>
      </div>
      <div class="panel-body">
        <form class="filter-grid compact-filter-grid" action="{{ route('products.diagnostics') }}" method="GET">
          <div class="field">
            <label for="q">Busca</label>
            <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Nome, EAN, SKU">
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
          <div class="filter-actions">
            <button type="submit">Filtrar</button>
            <a class="button secondary" href="{{ route('products.diagnostics') }}">Limpar</a>
          </div>
        </form>

        <div class="table-wrap nested-panel">
          <table>
            <thead>
              <tr>
                <th>Produto</th>
                <th>Pendencias</th>
                <th>Global</th>
                <th>Acao</th>
              </tr>
            </thead>
            <tbody>
              @forelse($products as $product)
                <tr>
                  <td>
                    <strong>{{ $product->name }}</strong>
                    <div class="muted">{{ $product->intelligence_gtin ?: 'Sem EAN/GTIN' }}</div>
                  </td>
                  <td>
                    <div class="badge-list">
                      @forelse($product->intelligence_flags ?? [] as $flag)
                        @php($badge = $flag['level'] === 'danger' ? 'danger' : ($flag['level'] === 'warning' ? 'warning' : 'muted-badge'))
                        <a class="badge {{ $badge }}" href="{{ $flag['url'] }}" title="{{ $flag['description'] }}">{{ $flag['label'] }}</a>
                      @empty
                        <span class="badge success">Sem pendencias</span>
                      @endforelse
                    </div>
                  </td>
                  <td>
                    @if($product->globalProduct)
                      <a href="{{ route('global-products.show', $product->globalProduct->id) }}">#{{ $product->globalProduct->id }} - {{ $product->global_status_label }}</a>
                    @else
                      <span class="muted">Sem vinculo</span>
                    @endif
                  </td>
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
                    </div>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="4" class="muted">Nenhum produto encontrado para o filtro atual.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        {{ $products->links() }}
      </div>
    </div>

    <div class="panel">
      <div class="panel-heading">
        <div>
          <h2>Ultimos vinculados</h2>
          <p>Produtos locais ja conectados ao Catalogo Global.</p>
        </div>
      </div>
      <div class="panel-body">
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Produto</th>
                <th>Global</th>
              </tr>
            </thead>
            <tbody>
              @forelse($recentGlobalLinks ?? [] as $product)
                <tr>
                  <td>
                    <strong>{{ $product->name }}</strong>
                    <div class="muted">{{ $product->intelligence_gtin }}</div>
                  </td>
                  <td>
                    @if($product->globalProduct)
                      <a href="{{ route('global-products.show', $product->globalProduct->id) }}">#{{ $product->globalProduct->id }}</a>
                    @else
                      <span class="muted">-</span>
                    @endif
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="2" class="muted">Nenhum vinculo global recente.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </section>
@endsection
