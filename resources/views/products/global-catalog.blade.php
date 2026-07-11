@extends('layouts.admin')

@section('title', 'Catalogo Global - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Catalogo Global</h1>
      <p>Produtos aprendidos pelo Product Intelligence Service.</p>
    </div>
    <a class="button" href="{{ route('products.create') }}">Novo produto local</a>
  </header>

  <div class="grid stats inventory-lot-stats">
    <div class="stat">
      <span>Total global</span>
      <strong>{{ $stats['total'] }}</strong>
    </div>
    <div class="stat">
      <span>Pendentes</span>
      <strong>{{ $stats['pending'] }}</strong>
    </div>
    <div class="stat">
      <span>Verificados</span>
      <strong>{{ $stats['verified'] }}</strong>
    </div>
    <div class="stat">
      <span>Conflitos</span>
      <strong>{{ $stats['conflict'] }}</strong>
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
            <th>Confianca</th>
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
                  @endif
                  <div>
                    <strong>{{ $product->name ?: 'Produto sem nome' }}</strong>
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
                <div class="muted">{{ $product->sources_count }} fonte(s)</div>
              </td>
              <td>{{ number_format((float) $product->source_confidence, 2, ',', '.') }}%</td>
              <td>
                @if($product->status === 'VERIFIED')
                  <span class="badge success">Verificado</span>
                @elseif($product->status === 'CONFLICT')
                  <span class="badge danger">Conflito</span>
                @else
                  <span class="badge warning">Pendente</span>
                @endif
              </td>
              <td>
                {{ $product->products_count }} produto(s)
                <div class="muted">{{ optional($product->last_lookup_at)->format('d/m/Y H:i') ?: 'Sem consulta' }}</div>
              </td>
              <td>
                <form class="inline" method="POST" action="{{ route('global-products.status', $product->id) }}">
                  @csrf
                  @method('PATCH')
                  <input type="hidden" name="status" value="VERIFIED">
                  <button class="secondary" type="submit">Verificar</button>
                </form>
                <form class="inline" method="POST" action="{{ route('global-products.status', $product->id) }}">
                  @csrf
                  @method('PATCH')
                  <input type="hidden" name="status" value="PENDING">
                  <button class="secondary" type="submit">Pendente</button>
                </form>
                <form class="inline" method="POST" action="{{ route('global-products.status', $product->id) }}">
                  @csrf
                  @method('PATCH')
                  <input type="hidden" name="status" value="CONFLICT">
                  <button class="danger" type="submit">Conflito</button>
                </form>
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
