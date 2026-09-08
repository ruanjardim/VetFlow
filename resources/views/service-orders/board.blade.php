@extends('layouts.admin')

@section('title', 'Operação Banho e Tosa - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Operação Banho e Tosa</h1>
      <p>Acompanhe cada pet da chegada até a retirada e leve a comanda pronta ao PDV.</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('service-orders.index') }}">Todas as comandas</a>
      <a class="button" href="{{ route('service-orders.create') }}">Nova comanda</a>
    </div>
  </header>

  <div class="panel service-order-board-toolbar">
    <a class="button secondary" href="{{ route('service-orders.board', ['date' => $date->subDay()->toDateString()]) }}">Dia anterior</a>
    <form method="GET" action="{{ route('service-orders.board') }}">
      <label for="board_date">Data de operação</label>
      <input id="board_date" name="date" type="date" value="{{ $date->toDateString() }}">
      <button type="submit">Atualizar</button>
    </form>
    <a class="button secondary" href="{{ route('service-orders.board', ['date' => $date->addDay()->toDateString()]) }}">Próximo dia</a>
  </div>

  <div class="service-order-board">
    @foreach($columns as $status => $column)
      <section class="service-order-column service-order-column--{{ $status }}">
        <header>
          <h2>{{ $column['label'] }}</h2>
          <span>{{ $column['orders']->count() }}</span>
        </header>

        <div class="service-order-cards">
          @forelse($column['orders'] as $order)
            <article class="service-order-card">
              <div class="service-order-card-heading">
                <strong>{{ $order->patient?->name ?? 'Pet não informado' }}</strong>
                <span>{{ $order->code }}</span>
              </div>
              <p>{{ $order->tutor?->name ?? 'Responsável não informado' }}</p>
              <p><strong>Profissional:</strong> {{ $order->assignedUser?->name ?? 'A definir' }}</p>
              @if(auth()->user()?->clinic_id === null)
                <p class="muted">{{ $order->clinic?->trade_name ?? $order->clinic?->corporate_name ?? 'Clinica não informada' }}</p>
              @endif
              <dl>
                <div>
                  <dt>Horário</dt>
                  <dd>{{ optional($order->scheduled_at ?? $order->opened_at)->format('d/m H:i') ?? '-' }}</dd>
                </div>
                <div>
                  <dt>Total</dt>
                  <dd>R$ {{ number_format((float) $order->total, 2, ',', '.') }}</dd>
                </div>
                @if($order->started_at)
                  <div>
                    <dt>Inicio</dt>
                    <dd>{{ $order->started_at->format('d/m H:i') }}</dd>
                  </div>
                @endif
                @if($order->ready_at)
                  <div>
                    <dt>Pronto</dt>
                    <dd>{{ $order->ready_at->format('d/m H:i') }}</dd>
                  </div>
                @endif
                @if($order->closed_at)
                  <div>
                    <dt>Conclusao</dt>
                    <dd>{{ $order->closed_at->format('d/m H:i') }}</dd>
                  </div>
                @endif
              </dl>

              @if($order->items->isNotEmpty())
                <ul class="service-order-card-items">
                  @foreach($order->items->take(3) as $orderItem)
                    <li>{{ rtrim(rtrim(number_format((float) $orderItem->quantity, 3, ',', '.'), '0'), ',') }}× {{ $orderItem->description }}</li>
                  @endforeach
                  @if($order->items->count() > 3)
                    <li>+ {{ $order->items->count() - 3 }} item(ns)</li>
                  @endif
                </ul>
              @endif

              <div class="service-order-card-actions">
                @if($status === 'open')
                  <form method="POST" action="{{ route('service-orders.status', $order->id) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="in_service">
                    <button type="submit">Iniciar</button>
                  </form>
                @elseif($status === 'in_service')
                  <form method="POST" action="{{ route('service-orders.status', $order->id) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="waiting_pickup">
                    <button type="submit">Aguardar retirada</button>
                  </form>
                @elseif($status === 'waiting_pickup')
                  @can('sales.manage')
                    <a class="button" href="{{ route('sales.create', ['service_order_id' => $order->id]) }}">Receber no PDV</a>
                  @endcan
                  <form method="POST" action="{{ route('service-orders.status', $order->id) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="finished">
                    <button class="secondary" type="submit">Finalizar</button>
                  </form>
                @endif
                <a class="button secondary" href="{{ route('service-orders.edit', $order->id) }}">Detalhes</a>
              </div>
            </article>
          @empty
            <p class="service-order-column-empty">Nenhuma comanda nesta etapa.</p>
          @endforelse
        </div>
      </section>
    @endforeach
  </div>
@endsection
