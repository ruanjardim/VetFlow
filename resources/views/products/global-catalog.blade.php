@extends('layouts.admin')

@section('title', 'Catalogo Global - VetFlow')

@section('content')
  @php
    $statusBadges = [
      'VERIFIED' => 'success',
      'CONFLICT' => 'danger',
      'PENDING' => 'warning',
    ];
  @endphp

  <header class="topbar">
    <div>
      <h1>Catalogo Global</h1>
      <p>Produtos aprendidos pelo Product Intelligence Service.</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('global-products.suggestions') }}">Sugestoes</a>
      <a class="button secondary" href="{{ route('global-products.export', request()->query()) }}">Exportar CSV</a>
      <a class="button" href="{{ route('products.create') }}">Novo produto local</a>
    </div>
  </header>

  <div class="grid stats inventory-lot-stats">
    <a class="stat stat-link" href="{{ route('global-products.index') }}">
      <span>Total global</span>
      <strong>{{ $stats['total'] }}</strong>
    </a>
    <a class="stat stat-link" href="{{ route('global-products.index', ['status' => 'PENDING']) }}">
      <span>Pendentes</span>
      <strong>{{ $stats['pending'] }}</strong>
    </a>
    <a class="stat stat-link" href="{{ route('global-products.index', ['status' => 'VERIFIED']) }}">
      <span>Verificados</span>
      <strong>{{ $stats['verified'] }}</strong>
    </a>
    <a class="stat stat-link" href="{{ route('global-products.index', ['status' => 'CONFLICT']) }}">
      <span>Conflitos</span>
      <strong>{{ $stats['conflict'] }}</strong>
    </a>
  </div>

  <div class="grid stats inventory-lot-stats">
    <a class="stat stat-link" href="{{ route('global-products.index', ['missing_image' => 1]) }}">
      <span>Sem imagem</span>
      <strong>{{ $stats['missing_image'] }}</strong>
    </a>
    <a class="stat stat-link" href="{{ route('global-products.index', ['stale' => 1]) }}">
      <span>Consulta antiga</span>
      <strong>{{ $stats['stale'] }}</strong>
    </a>
    <a class="stat stat-link" href="{{ route('global-products.suggestions', ['status' => 'PENDING']) }}">
      <span>Sugestoes pendentes</span>
      <strong>{{ $stats['suggestions_pending'] }}</strong>
    </a>
    <div class="stat">
      <span>Produtos locais vinculados</span>
      <strong>{{ $stats['linked_products'] }}</strong>
    </div>
  </div>

  <div class="panel">
    <div class="panel-body">
      <form method="GET" action="{{ route('global-products.index') }}" class="form-grid">
        <div class="field">
          <label for="q">Buscar</label>
          <input id="q" name="q" value="{{ request('q') }}" placeholder="GTIN, nome, marca ou fabricante">
        </div>
        <div class="field">
          <label for="status">Status</label>
          <select id="status" name="status">
            <option value="">Todos</option>
            @foreach($statuses as $value => $label)
              <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="field">
          <label for="category">Categoria</label>
          <select id="category" name="category">
            <option value="">Todas</option>
            @foreach($categories as $category)
              <option value="{{ $category }}" @selected(request('category') === $category)>{{ $category }}</option>
            @endforeach
          </select>
        </div>
        <div class="field">
          <label for="source">Origem</label>
          <select id="source" name="source">
            <option value="">Todas</option>
            @foreach($sources as $source)
              <option value="{{ $source }}" @selected(request('source') === $source)>{{ $source }}</option>
            @endforeach
          </select>
        </div>
        <div class="field">
          <label for="quality">Qualidade</label>
          <select id="quality" name="quality">
            <option value="">Todas</option>
            @foreach($qualityBuckets as $value => $bucket)
              <option value="{{ $value }}" @selected(request('quality') === $value)>{{ $bucket['label'] }}</option>
            @endforeach
          </select>
        </div>
        <div class="field">
          <label for="missing_image">Pendencias</label>
          <select id="missing_image" name="missing_image">
            <option value="">Todas</option>
            <option value="1" @selected(request('missing_image') === '1')>Sem imagem</option>
          </select>
        </div>
        <div class="field full">
          <label>
            <input type="checkbox" name="stale" value="1" @checked(request()->boolean('stale'))>
            Mostrar apenas produtos com consulta antiga
          </label>
        </div>
        <div class="field full">
          <div class="actions">
            <button type="submit">Filtrar</button>
            <a class="button secondary" href="{{ route('global-products.index') }}">Limpar</a>
          </div>
        </div>
      </form>
    </div>
  </div>

  <div class="panel nested-panel">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Produto global</th>
            <th>Marca / Fabricante</th>
            <th>Categoria</th>
            <th>Origem</th>
            <th>Qualidade</th>
            <th>Status</th>
            <th>Uso</th>
            <th>Acoes</th>
          </tr>
        </thead>
        <tbody>
          @forelse($globalProducts as $product)
            <tr>
              <td>
                <div class="product-list-item">
                  @if($product->image_path)
                    <img class="product-thumb" src="{{ route('products.lookup-image', ['filename' => basename($product->image_path)]) }}" alt="Foto de {{ $product->name }}">
                  @elseif($product->image_url)
                    <img class="product-thumb" src="{{ $product->image_url }}" alt="Foto de {{ $product->name }}">
                  @endif
                  <div>
                    <a href="{{ route('global-products.show', $product->id) }}"><strong>{{ $product->name ?: 'Produto sem nome' }}</strong></a>
                    <div class="muted">{{ $product->gtin }}</div>
                  </div>
                </div>
              </td>
              <td>
                <strong>{{ $product->brand ?: '-' }}</strong>
                <div class="muted">{{ $product->manufacturer ?: '-' }}</div>
              </td>
              <td>
                {{ $product->category ?: '-' }}
                @if($product->subcategory)
                  <div class="muted">{{ $product->subcategory }}</div>
                @endif
              </td>
              <td>
                {{ $product->api_source ?: 'vetflow' }}
                <div class="muted">{{ $product->sources_count }} fonte(s) - {{ number_format((float) $product->source_confidence, 2, ',', '.') }}%</div>
              </td>
              <td>
                <strong>{{ $product->quality_score }}%</strong>
                <div class="muted">{{ $product->quality_status }}</div>
              </td>
              <td>
                <span class="badge {{ $statusBadges[$product->status] ?? 'warning' }}">
                  {{ $statuses[$product->status] ?? $product->status }}
                </span>
              </td>
              <td>
                {{ $product->products_count }} produto(s)
                <div class="muted">{{ optional($product->last_lookup_at)->format('d/m/Y H:i') ?: 'Sem consulta' }}</div>
              </td>
              <td>
                <div class="actions">
                  <a class="button secondary" href="{{ route('global-products.show', $product->id) }}">Abrir</a>
                  <form class="inline" method="POST" action="{{ route('global-products.enrich', $product->id) }}">
                    @csrf
                    <button class="secondary" type="submit">Enriquecer</button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="muted">Nenhum produto global encontrado.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{ $globalProducts->links() }}
@endsection
