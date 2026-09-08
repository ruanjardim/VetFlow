@extends('layouts.admin')

@section('title', 'Comandas - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Comandas</h1>
      <p>Atendimentos de PetShop com servicos, produtos e status operacional.</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('service-orders.board') }}">Operação Banho e Tosa</a>
      <a class="button" href="{{ route('service-orders.create') }}">Nova comanda</a>
    </div>
  </header>

  <div class="panel">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Codigo</th>
            @if(auth()->user()?->clinic_id === null)
              <th>Clinica</th>
            @endif
            <th>Responsável</th>
            <th>Pet</th>
            <th>Profissional</th>
            <th>Status</th>
            <th>Abertura</th>
            <th>Marco atual</th>
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
              @if(auth()->user()?->clinic_id === null)
                <td>{{ $order->clinic?->trade_name ?? $order->clinic?->corporate_name ?? '-' }}</td>
              @endif
              <td>{{ $order->tutor?->name ?? '-' }}</td>
              <td>{{ $order->patient?->name ?? '-' }}</td>
              <td>{{ $order->assignedUser?->name ?? 'A definir' }}</td>
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
              <td>
                @if($order->closed_at)
                  Concluida em {{ $order->closed_at->format('d/m H:i') }}
                @elseif($order->ready_at)
                  Pronta em {{ $order->ready_at->format('d/m H:i') }}
                @elseif($order->started_at)
                  Iniciada em {{ $order->started_at->format('d/m H:i') }}
                @else
                  Aguardando inicio
                @endif
              </td>
              <td>R$ {{ number_format((float) $order->services_total, 2, ',', '.') }}</td>
              <td>R$ {{ number_format((float) $order->products_total, 2, ',', '.') }}</td>
              <td>R$ {{ number_format((float) $order->total, 2, ',', '.') }}</td>
              <td>
                <a class="button secondary" href="{{ route('service-orders.edit', $order->id) }}">Editar</a>
                @if($order->status === 'open' && (int) $order->sales_count === 0)
                  <form class="inline" action="{{ route('service-orders.destroy', $order->id) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <button class="danger" type="submit" data-confirm="Remover esta comanda aberta?">Excluir</button>
                  </form>
                @else
                  <span class="muted">Histórico protegido</span>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="{{ auth()->user()?->clinic_id === null ? 12 : 11 }}" class="muted">Nenhuma comanda cadastrada.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{ $serviceOrders->links() }}
@endsection
