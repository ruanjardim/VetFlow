@extends('layouts.admin')

@section('title', 'Reposicao - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Reposicao</h1>
      <p>Produtos abaixo do estoque minimo para priorizar compras.</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('purchase-entries.index') }}">Entradas</a>
      <a class="button" href="{{ route('purchase-entries.create') }}">Nova entrada</a>
    </div>
  </header>

  <section class="grid stats inventory-lot-stats">
    <div class="stat">
      <span>Produtos para repor</span>
      <strong>{{ $stats['products'] ?? 0 }}</strong>
    </div>
    <div class="stat">
      <span>Criticos</span>
      <strong>{{ $stats['critical'] ?? 0 }}</strong>
    </div>
    <div class="stat">
      <span>Abaixo do minimo</span>
      <strong>{{ $stats['below_minimum'] ?? 0 }}</strong>
    </div>
    <div class="stat">
      <span>Custo estimado</span>
      <strong>R$ {{ number_format((float) ($stats['estimated_cost'] ?? 0), 2, ',', '.') }}</strong>
    </div>
  </section>

  <section class="panel">
    <div class="panel-heading">
      <div>
        <h2>Lista sugerida</h2>
        <p>Quantidade sugerida considera estoque minimo e saldo atual.</p>
      </div>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Produto</th>
            <th>Saldo atual</th>
            <th>Minimo</th>
            <th>Sugestao compra</th>
            <th>Custo unit.</th>
            <th>Custo estimado</th>
            <th>Global</th>
            <th>Acao</th>
          </tr>
        </thead>
        <tbody>
          @forelse($items as $item)
            <tr>
              <td>
                <strong>{{ $item['product']->name }}</strong>
                <div class="muted">{{ $item['product']->gtin ?: $item['product']->barcode ?: 'Sem EAN/GTIN' }}</div>
              </td>
              <td>{{ number_format((float) $item['stock_quantity'], 3, ',', '.') }} {{ $item['unit'] }}</td>
              <td>{{ number_format((float) $item['minimum_stock'], 3, ',', '.') }} {{ $item['unit'] }}</td>
              <td>{{ number_format((float) $item['suggested_quantity'], 3, ',', '.') }} {{ $item['unit'] }}</td>
              <td>R$ {{ number_format((float) $item['unit_cost'], 2, ',', '.') }}</td>
              <td>R$ {{ number_format((float) $item['estimated_cost'], 2, ',', '.') }}</td>
              <td>
                @if($item['product']->globalProduct)
                  <a href="{{ route('global-products.show', $item['product']->globalProduct->id) }}">#{{ $item['product']->globalProduct->id }}</a>
                @else
                  <span class="muted">Sem vinculo</span>
                @endif
              </td>
              <td>
                <div class="row-actions">
                  <a class="button secondary" href="{{ $item['scan_url'] }}">Adicionar na entrada</a>
                  <a class="button secondary" href="{{ route('products.edit', $item['product']->id) }}">Editar produto</a>
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="muted">Nenhum produto abaixo do minimo agora.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>
@endsection
