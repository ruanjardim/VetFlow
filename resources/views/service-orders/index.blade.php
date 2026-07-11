@extends('layouts.admin')

@section('title', 'Comandas - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Comandas</h1>
      <p>Atendimentos de PetShop com servicos, produtos e status operacional.</p>
    </div>
    <a class="button" href="{{ route('service-orders.create') }}">Nova comanda</a>
  </header>

  <div class="panel">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Codigo</th>
            <th>Tutor</th>
            <th>Pet</th>
            <th>Status</th>
            <th>Abertura</th>
            <th>Servicos</th>
            <th>Produtos</th>
            <th>Total</th>
            <th>Acoes</th>
          </tr>
        </thead>
        <tbody>
          @forelse($serviceOrders as $order)
            <tr>
              <td><strong>{{ $order->code }}</strong></td>
              <td>{{ $order->tutor?->name ?? '-' }}</td>
              <td>{{ $order->patient?->name ?? '-' }}</td>
              <td>
                @if($order->status === 'open')
                  Aberta
                @elseif($order->status === 'in_service')
                  Em atendimento
                @elseif($order->status === 'waiting_pickup')
                  Aguardando retirada
                @elseif($order->status === 'finished')
                  Finalizada
                @else
                  Cancelada
                @endif
              </td>
              <td>{{ optional($order->opened_at)->format('d/m/Y H:i') }}</td>
              <td>R$ {{ number_format((float) $order->services_total, 2, ',', '.') }}</td>
              <td>R$ {{ number_format((float) $order->products_total, 2, ',', '.') }}</td>
              <td>R$ {{ number_format((float) $order->total, 2, ',', '.') }}</td>
              <td>
                <a class="button secondary" href="{{ route('service-orders.edit', $order->id) }}">Editar</a>
                <form class="inline" action="{{ route('service-orders.destroy', $order->id) }}" method="POST">
                  @csrf
                  @method('DELETE')
                  <button class="danger" type="submit" data-confirm="Remover esta comanda?">Excluir</button>
                </form>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="9" class="muted">Nenhuma comanda cadastrada.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{ $serviceOrders->links() }}
@endsection
