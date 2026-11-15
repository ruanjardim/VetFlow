@extends('layouts.admin')

@section('title', 'Tributação de produtos - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Tributação de produtos</h1>
      <p>Cadastre a classificação mínima usada para preparar NFC-e em homologação.</p>
    </div>
    <a class="button secondary" href="{{ route('fiscal.index', $requiresClinic ? ['clinic_id' => $selectedClinicId] : []) }}">Central fiscal</a>
  </header>

  <div class="alert warning">
    Estes campos não calculam imposto e não substituem a orientação contábil. O CFOP é apenas o padrão interno; destino, operação e regras vigentes ainda devem ser resolvidos na emissão.
  </div>

  @if($requiresClinic)
    <form method="GET" action="{{ route('fiscal.products.index') }}" class="panel panel-body">
      <div class="field">
        <label for="fiscal-product-clinic">Estabelecimento</label>
        <select id="fiscal-product-clinic" name="clinic_id" data-auto-submit-select>
          @foreach($clinics as $clinic)
            <option value="{{ $clinic->id }}" @selected($selectedClinicId === $clinic->id)>{{ $clinic->trade_name ?: $clinic->corporate_name }}</option>
          @endforeach
        </select>
      </div>
    </form>
  @endif

  <section class="grid stats">
    <div class="stat"><span>Produtos ativos</span><strong>{{ $stats['total'] }}</strong></div>
    <div class="stat"><span>Prontos para homologação</span><strong>{{ $stats['ready'] }}</strong></div>
    <div class="stat"><span>Pendentes</span><strong>{{ $stats['pending'] }}</strong></div>
  </section>

  <form method="GET" action="{{ route('fiscal.products.index') }}" class="panel panel-body">
    @if($requiresClinic)<input type="hidden" name="clinic_id" value="{{ $selectedClinicId }}">@endif
    <div class="form-grid">
      <div class="field">
        <label for="product-tax-search">Buscar</label>
        <input id="product-tax-search" name="search" value="{{ request('search') }}" placeholder="Nome, EAN/GTIN ou SKU">
      </div>
      <div class="field">
        <label for="product-tax-status">Situação</label>
        <select id="product-tax-status" name="status">
          <option value="all" @selected($status === 'all')>Todos</option>
          <option value="pending" @selected($status === 'pending')>Pendentes</option>
          <option value="ready" @selected($status === 'ready')>Prontos</option>
        </select>
      </div>
      <div class="field full"><div class="actions"><button type="submit">Filtrar</button></div></div>
    </div>
  </form>

  <section class="panel">
    <div class="table-wrap">
      <table>
        <thead><tr><th>Produto</th><th>NCM</th><th>CFOP padrão</th><th>ICMS</th><th>Situação</th><th>Ação</th></tr></thead>
        <tbody>
          @forelse($products as $product)
            <tr>
              <td><strong>{{ $product->name }}</strong><div class="muted">{{ $product->gtin ?: $product->sku ?: 'Sem código comercial' }}</div></td>
              <td>{{ $product->fiscalProfile?->ncm ?? '-' }}</td>
              <td>{{ $product->fiscalProfile?->default_cfop ?? '-' }}</td>
              <td>{{ $product->fiscalProfile?->icms_code ?? '-' }}</td>
              <td><span class="badge {{ $product->fiscalProfile?->ready ? 'success' : 'warning' }}">{{ $product->fiscalProfile?->ready ? 'Pronto' : 'Pendente' }}</span></td>
              <td><a class="button secondary" href="{{ route('fiscal.products.edit', array_filter(['product' => $product->id, 'clinic_id' => $requiresClinic ? $selectedClinicId : null])) }}">Classificar</a></td>
            </tr>
          @empty
            <tr><td colspan="6" class="muted">Nenhum produto encontrado.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>

  {{ $products->links() }}
@endsection
