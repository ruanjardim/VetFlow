@extends('layouts.admin')

@section('title', 'Produtos - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Produtos</h1>
      <p>Catalogo comercial do PetShop, farmacia e loja.</p>
    </div>
    <a class="button" href="{{ route('products.create') }}">Novo produto</a>
  </header>

  <div class="panel">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Produto</th>
            <th>Categoria</th>
            <th>Marca</th>
            <th>Estoque</th>
            <th>Minimo</th>
            <th>Venda</th>
            <th>Status</th>
            <th>Acoes</th>
          </tr>
        </thead>
        <tbody>
          @forelse($products as $product)
            <tr>
              <td>
                <div class="product-list-item">
                  @if($product->image_path)
                    <img class="product-thumb" src="{{ route('products.lookup-image', ['filename' => basename($product->image_path)]) }}" alt="Foto de {{ $product->name }}">
                  @endif
                  <div>
                    <strong>{{ $product->name }}</strong>
                    <div class="muted">{{ $product->sku ?: ($product->gtin ?: $product->barcode) }}</div>
                  </div>
                </div>
              </td>
              <td>{{ $product->category }}</td>
              <td>{{ $product->brand }}</td>
              <td>{{ number_format((float) $product->stock_quantity, 3, ',', '.') }} {{ $product->unit }}</td>
              <td>{{ number_format((float) $product->minimum_stock, 3, ',', '.') }} {{ $product->unit }}</td>
              <td>R$ {{ number_format((float) $product->sale_price, 2, ',', '.') }}</td>
              <td>{{ $product->active ? 'Ativo' : 'Inativo' }}</td>
              <td>
                <a class="button secondary" href="{{ route('products.edit', $product->id) }}">Editar</a>
                <form class="inline" action="{{ route('products.destroy', $product->id) }}" method="POST">
                  @csrf
                  @method('DELETE')
                  <button class="danger" type="submit" data-confirm="Remover este produto?">Excluir</button>
                </form>
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
  </div>

  {{ $products->links() }}
@endsection
