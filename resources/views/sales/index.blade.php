@extends('layouts.admin')

@section('title', 'PDV / Vendas - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>PDV / Vendas</h1>
      <p>Fechamento de produtos, servicos e comandas com baixa de estoque e caixa.</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('sales.cashier') }}">Caixa do dia</a>
      <a class="button" href="{{ route('sales.create') }}">Nova venda</a>
    </div>
  </header>

  @php
    $statusLabels = [
      'draft' => 'Rascunho',
      'completed' => 'Concluida',
      'cancelled' => 'Cancelada',
      'returned' => 'Devolvida',
    ];
    $paymentLabels = [
      'paid' => 'Pago',
      'partial' => 'Parcial',
      'pending' => 'Pendente',
      'cancelled' => 'Cancelado',
      'refunded' => 'Estornado',
    ];
  @endphp

  <div class="panel">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Codigo</th>
            <th>Responsável</th>
            <th>Pet</th>
            <th>Comanda</th>
            <th>Status</th>
            <th>Pagamento</th>
            <th>Data</th>
            <th>Total</th>
            <th>Acoes</th>
          </tr>
        </thead>
        <tbody>
          @forelse($sales as $sale)
            <tr>
              <td><strong>{{ $sale->code }}</strong></td>
              <td>{{ $sale->tutor?->name ?? '-' }}</td>
              <td>{{ $sale->patient?->name ?? '-' }}</td>
              <td>{{ $sale->serviceOrder?->code ?? '-' }}</td>
              <td>
                {{ $statusLabels[$sale->status] ?? ucfirst($sale->status) }}
              </td>
              <td>
                {{ $paymentLabels[$sale->payment_status] ?? ucfirst($sale->payment_status) }}
              </td>
              <td>{{ optional($sale->sold_at)->format('d/m/Y H:i') }}</td>
              <td>R$ {{ number_format((float) $sale->total, 2, ',', '.') }}</td>
              <td>
                <a class="button secondary" href="{{ route('sales.edit', $sale->id) }}">Editar</a>
                <a class="button secondary" href="{{ route('sales.receipt', $sale->id) }}">Comprovante</a>
                @if($sale->status === 'completed')
                  <a class="button secondary" href="{{ route('sales.returns.create', $sale->id) }}">Devolver</a>
                  <form class="inline" action="{{ route('sales.cancel', $sale->id) }}" method="POST">
                    @csrf
                    @method('PATCH')
                    <button class="danger" type="submit" data-confirm="Cancelar esta venda e estornar estoque/financeiro?">Cancelar venda</button>
                  </form>
                @elseif($sale->status === 'draft')
                  <form class="inline" action="{{ route('sales.destroy', $sale->id) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <button class="danger" type="submit" data-confirm="Remover este rascunho?">Excluir</button>
                  </form>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="9" class="muted">Nenhuma venda cadastrada.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{ $sales->links() }}
@endsection
