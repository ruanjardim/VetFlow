@extends('layouts.admin')

@section('title', 'Alertas - VetFlow')

@section('content')
  @php
    $levelFilter = request('level');
    $showCritical = ! $levelFilter || $levelFilter === 'critical';
    $showAttention = ! $levelFilter || $levelFilter === 'attention';
    $showCadastro = ! $levelFilter || $levelFilter === 'cadastro';
  @endphp

  <header class="topbar">
    <div>
      <h1>Alertas</h1>
      <p>Estoque, lotes e cadastro de produtos que precisam de atencao.</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('inventory-movements.index') }}">Ver estoque</a>
      <a class="button" href="{{ route('inventory-movements.create') }}">Nova movimentacao</a>
    </div>
  </header>

  <div class="grid stats inventory-lot-stats">
    <a class="stat stat-link" href="{{ route('inventory-movements.alerts') }}">
      <span>Total de alertas</span>
      <strong>{{ $stats['total'] ?? 0 }}</strong>
    </a>
    <a class="stat stat-link" href="{{ route('inventory-movements.alerts', ['level' => 'critical']) }}">
      <span>Criticos</span>
      <strong>{{ $stats['critical'] ?? 0 }}</strong>
    </a>
    <a class="stat stat-link" href="{{ route('inventory-movements.alerts', ['level' => 'attention']) }}">
      <span>Atencao</span>
      <strong>{{ $stats['attention'] ?? 0 }}</strong>
    </a>
    <a class="stat stat-link" href="{{ route('inventory-movements.alerts', ['level' => 'cadastro']) }}">
      <span>Cadastro</span>
      <strong>{{ $stats['cadastro'] ?? 0 }}</strong>
    </a>
  </div>

  <div class="actions alert-filter-actions">
    <a class="button {{ ! $levelFilter ? '' : 'secondary' }}" href="{{ route('inventory-movements.alerts') }}">Todos</a>
    <a class="button {{ $levelFilter === 'critical' ? '' : 'secondary' }}" href="{{ route('inventory-movements.alerts', ['level' => 'critical']) }}">Criticos</a>
    <a class="button {{ $levelFilter === 'attention' ? '' : 'secondary' }}" href="{{ route('inventory-movements.alerts', ['level' => 'attention']) }}">Atencao</a>
    <a class="button {{ $levelFilter === 'cadastro' ? '' : 'secondary' }}" href="{{ route('inventory-movements.alerts', ['level' => 'cadastro']) }}">Cadastro</a>
  </div>

  @if($showCritical)
  <div class="panel" id="low-stock">
    <div class="panel-heading">
      <div>
        <h2>Produtos abaixo do minimo</h2>
        <p>Reposicao recomendada para evitar falta no PDV e atendimento.</p>
      </div>
      <span class="badge danger">{{ $lowStockProducts->count() }}</span>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Produto</th>
            <th>Estoque</th>
            <th>Minimo</th>
            <th>Acoes</th>
          </tr>
        </thead>
        <tbody>
          @forelse($lowStockProducts as $product)
            <tr>
              <td>{{ $product->name }}</td>
              <td>{{ number_format((float) $product->stock_quantity, 3, ',', '.') }} {{ $product->unit }}</td>
              <td>{{ number_format((float) $product->minimum_stock, 3, ',', '.') }} {{ $product->unit }}</td>
              <td>
                <a
                  class="button secondary"
                  href="{{ route('inventory-movements.create', [
                    'product_id' => $product->id,
                    'type' => 'entry',
                    'reason' => 'Reposicao de estoque minimo',
                  ]) }}"
                >
                  Repor estoque
                </a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="4" class="muted">Nenhum produto abaixo do minimo.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  @endif

  @if($showCritical)
  <div class="panel" id="expired-lots">
    <div class="panel-heading">
      <div>
        <h2>Lotes vencidos</h2>
        <p>Itens que nao devem ser considerados estoque vendavel.</p>
      </div>
      <span class="badge danger">{{ $expiredLots->count() }}</span>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Produto</th>
            <th>Lote</th>
            <th>Validade</th>
            <th>Saldo</th>
          </tr>
        </thead>
        <tbody>
          @forelse($expiredLots as $lot)
            <tr>
              <td>{{ $lot['product']?->name ?? 'Produto removido' }}</td>
              <td>{{ $lot['lot_number'] }}</td>
              <td>{{ optional($lot['expires_at'])->format('d/m/Y') ?: '-' }}</td>
              <td>{{ number_format((float) $lot['quantity'], 3, ',', '.') }} {{ $lot['unit'] }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="4" class="muted">Nenhum lote vencido.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  @endif

  @if($showAttention)
  <div class="panel" id="expiring-lots">
    <div class="panel-heading">
      <div>
        <h2>Proximos de vencer</h2>
        <p>Lotes com validade nos proximos 30 dias.</p>
      </div>
      <span class="badge warning">{{ $expiringLots->count() }}</span>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Produto</th>
            <th>Lote</th>
            <th>Validade</th>
            <th>Saldo</th>
          </tr>
        </thead>
        <tbody>
          @forelse($expiringLots as $lot)
            <tr>
              <td>{{ $lot['product']?->name ?? 'Produto removido' }}</td>
              <td>{{ $lot['lot_number'] }}</td>
              <td>{{ optional($lot['expires_at'])->format('d/m/Y') ?: '-' }}</td>
              <td>{{ number_format((float) $lot['quantity'], 3, ',', '.') }} {{ $lot['unit'] }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="4" class="muted">Nenhum lote proximo do vencimento.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  @endif

  @if($showAttention)
  <div class="panel" id="untracked-stock">
    <div class="panel-heading">
      <div>
        <h2>Estoque sem lote</h2>
        <p>Saldo atual que ainda precisa ser vinculado a lote e validade.</p>
      </div>
      <span class="badge warning">{{ $untrackedProducts->count() }}</span>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Produto</th>
            <th>Saldo sem lote</th>
            <th>Acoes</th>
          </tr>
        </thead>
        <tbody>
          @forelse($untrackedProducts as $item)
            <tr>
              <td>{{ $item['product']->name }}</td>
              <td>{{ number_format((float) $item['quantity'], 3, ',', '.') }} {{ $item['unit'] }}</td>
              <td>
                <a
                  class="button secondary"
                  href="{{ route('inventory-movements.create', [
                    'product_id' => $item['product']->id,
                    'type' => 'lot_assignment',
                    'quantity' => $item['quantity'],
                    'reason' => 'Cadastro de lote do estoque atual',
                  ]) }}"
                >
                  Vincular lote
                </a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="3" class="muted">Todo estoque atual esta vinculado a lote.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  @endif

  @if($showCritical)
  <div class="panel" id="without-price">
    <div class="panel-heading">
      <div>
        <h2>Produtos sem preco</h2>
        <p>Itens que nao conseguem finalizar venda ate receberem preco.</p>
      </div>
      <span class="badge danger">{{ $withoutPriceProducts->count() }}</span>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Produto</th>
            <th>Marca</th>
            <th>Acoes</th>
          </tr>
        </thead>
        <tbody>
          @forelse($withoutPriceProducts as $product)
            <tr>
              <td>{{ $product->name }}</td>
              <td>{{ $product->brand ?: '-' }}</td>
              <td><a class="button secondary" href="{{ route('products.edit', $product->id) }}">Definir preco</a></td>
            </tr>
          @empty
            <tr>
              <td colspan="3" class="muted">Todos os produtos ativos tem preco de venda.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  @endif

  @if($showCadastro)
  <div class="panel" id="without-image">
    <div class="panel-heading">
      <div>
        <h2>Produtos sem imagem</h2>
        <p>Fotos ajudam na conferencia visual no cadastro, estoque e PDV.</p>
      </div>
      <span class="badge muted-badge">{{ $withoutImageProducts->count() }}</span>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Produto</th>
            <th>EAN/GTIN</th>
            <th>Acoes</th>
          </tr>
        </thead>
        <tbody>
          @forelse($withoutImageProducts as $product)
            <tr>
              <td>{{ $product->name }}</td>
              <td>{{ $product->gtin ?: ($product->barcode ?: '-') }}</td>
              <td><a class="button secondary" href="{{ route('products.edit', $product->id) }}">Enviar imagem</a></td>
            </tr>
          @empty
            <tr>
              <td colspan="3" class="muted">Todos os produtos ativos tem imagem.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  @endif
@endsection
