@extends('layouts.admin')

@section('title', 'Estoque - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Estoque</h1>
      <p>Entradas, saidas e ajustes de produtos.</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('inventory-movements.alerts') }}">Ver alertas</a>
      <a class="button" href="{{ route('inventory-movements.create') }}">Nova movimentacao</a>
    </div>
  </header>

  <div class="grid stats inventory-lot-stats">
    <div class="stat">
      <span>Lotes ativos</span>
      <strong>{{ $lotStats['total'] ?? 0 }}</strong>
    </div>
    <div class="stat">
      <span>Proximos de vencer</span>
      <strong>{{ $lotStats['expiring'] ?? 0 }}</strong>
    </div>
    <div class="stat">
      <span>Vencidos</span>
      <strong>{{ $lotStats['expired'] ?? 0 }}</strong>
    </div>
    <div class="stat">
      <span>Sem validade</span>
      <strong>{{ $lotStats['without_expiration'] ?? 0 }}</strong>
    </div>
  </div>

  @if(($lotStats['untracked'] ?? 0) > 0)
    <div class="panel inventory-lots-panel">
      <div class="panel-heading">
        <div>
          <h2>Estoque sem lote</h2>
          <p>Produtos com saldo atual que ainda nao foi vinculado a um lote.</p>
        </div>
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
            @foreach($untrackedProducts as $item)
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
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif

  <div class="panel inventory-lots-panel">
    <div class="panel-heading">
      <div>
        <h2>Lotes e vencimentos</h2>
        <p>Controle por lote para medicamentos, vacinas, antiparasitarios e itens sensiveis.</p>
      </div>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Produto</th>
            <th>Lote</th>
            <th>Validade</th>
            <th>Saldo do lote</th>
            <th>Status</th>
            <th>Ultima movimentacao</th>
          </tr>
        </thead>
        <tbody>
          @forelse($lotSummaries as $lot)
            <tr>
              <td>{{ $lot['product']?->name ?? 'Produto removido' }}</td>
              <td>{{ $lot['lot_number'] }}</td>
              <td>{{ optional($lot['expires_at'])->format('d/m/Y') ?: '-' }}</td>
              <td>{{ number_format((float) $lot['quantity'], 3, ',', '.') }} {{ $lot['unit'] }}</td>
              <td>
                @if($lot['status'] === 'expired')
                  <span class="badge danger">Vencido</span>
                @elseif($lot['status'] === 'expiring')
                  <span class="badge warning">Proximo</span>
                @elseif($lot['status'] === 'without_expiration')
                  <span class="badge muted-badge">Sem validade</span>
                @else
                  <span class="badge success">Dentro da validade</span>
                @endif
              </td>
              <td>{{ optional($lot['last_movement_at'])->format('d/m/Y H:i') ?: '-' }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="muted">Nenhum lote com validade cadastrado ainda.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="panel">
    <div class="panel-heading">
      <div>
        <h2>Historico de movimentacoes</h2>
        <p>Entradas, saidas e ajustes lancados no estoque.</p>
      </div>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Produto</th>
            <th>Tipo</th>
            <th>Quantidade</th>
            <th>Custo unitario</th>
            <th>Lote</th>
            <th>Validade</th>
            <th>Saldo apos</th>
            <th>Data</th>
            <th>Acoes</th>
          </tr>
        </thead>
        <tbody>
          @forelse($inventoryMovements as $movement)
            <tr>
              <td>{{ $movement->product?->name ?? 'Produto removido' }}</td>
              <td>
                @if($movement->type === 'entry')
                  Entrada
                @elseif($movement->type === 'exit')
                  Saida
                @elseif($movement->type === 'lot_assignment')
                  Lote do estoque atual
                @else
                  Ajuste
                @endif
              </td>
              <td>{{ number_format((float) $movement->quantity, 3, ',', '.') }}</td>
              <td>R$ {{ number_format((float) $movement->unit_cost, 2, ',', '.') }}</td>
              <td>{{ $movement->lot_number ?: '-' }}</td>
              <td>{{ optional($movement->expires_at)->format('d/m/Y') ?: '-' }}</td>
              <td>{{ number_format((float) $movement->balance_after, 3, ',', '.') }}</td>
              <td>{{ optional($movement->occurred_at)->format('d/m/Y H:i') }}</td>
              <td>
                <a class="button secondary" href="{{ route('inventory-movements.edit', $movement->id) }}">Editar</a>
                <form class="inline" action="{{ route('inventory-movements.destroy', $movement->id) }}" method="POST">
                  @csrf
                  @method('DELETE')
                  <button class="danger" type="submit" data-confirm="Remover esta movimentacao? O saldo do produto sera recalculado.">Excluir</button>
                </form>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="9" class="muted">Nenhuma movimentacao de estoque cadastrada.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{ $inventoryMovements->links() }}
@endsection
