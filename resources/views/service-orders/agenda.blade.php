@extends('layouts.admin')

@section('title', 'Agenda Banho e Tosa - VetFlow')

@php
  $rowHeight = 44;
  $statusClass = [
    'scheduled' => 'is-scheduled',
    'confirmed' => 'is-confirmed',
    'open' => 'is-waiting',
    'in_service' => 'is-in-service',
    'waiting_pickup' => 'is-ready',
    'finished' => 'is-finished',
    'no_show' => 'is-no-show',
  ];
  $gridStartMinutes = $gridStart->hour * 60 + $gridStart->minute;
  $clinicQuery = $selectedClinicId && auth()->user()?->clinic_id === null ? ['clinic_id' => $selectedClinicId] : [];
@endphp

@section('content')
  <header class="topbar">
    <div>
      <h1>Agenda banho e tosa</h1>
      <p>{{ ucfirst($day->locale('pt_BR')->translatedFormat('l, d \d\e F \d\e Y')) }} · clique em um horário livre para agendar.</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('service-orders.board', ['date' => $day->toDateString()]) }}">Operação do dia</a>
      <a class="button" href="{{ route('service-orders.create', array_merge($clinicQuery, ['status' => 'scheduled', 'scheduled_at' => $day->toDateString().' '.$gridStart->format('H:i')])) }}">Novo agendamento</a>
    </div>
  </header>

  <div class="panel grooming-agenda-toolbar">
    <a class="button secondary" href="{{ route('service-orders.agenda', array_merge($clinicQuery, ['date' => $day->subDay()->toDateString()])) }}">‹ Dia anterior</a>
    <a class="button secondary" href="{{ route('service-orders.agenda', $clinicQuery) }}">Hoje</a>
    <a class="button secondary" href="{{ route('service-orders.agenda', array_merge($clinicQuery, ['date' => $day->addDay()->toDateString()])) }}">Próximo dia ›</a>
    <form method="GET" action="{{ route('service-orders.agenda') }}" class="grooming-agenda-filter">
      @if($clinics->isNotEmpty())
        <label for="agenda_clinic" class="sr-only">Clínica</label>
        <select id="agenda_clinic" name="clinic_id">
          @foreach($clinics as $clinic)
            <option value="{{ $clinic->id }}" @selected((int) $selectedClinicId === $clinic->id)>{{ $clinic->trade_name ?? $clinic->corporate_name }}</option>
          @endforeach
        </select>
      @endif
      <label for="agenda_date" class="sr-only">Data</label>
      <input id="agenda_date" name="date" type="date" value="{{ $day->toDateString() }}">
      <button type="submit">Ir</button>
    </form>
  </div>

  <section class="grid stats grooming-agenda-stats" aria-label="Resumo do dia">
    <div class="stat"><span>Agendamentos</span><strong>{{ $summary['total'] }}</strong></div>
    <div class="stat"><span>Aguardando chegada</span><strong>{{ $summary['waiting'] }}</strong></div>
    <div class="stat {{ $summary['late'] > 0 ? 'stat--alert' : '' }}"><span>Atrasados</span><strong>{{ $summary['late'] }}</strong></div>
    <div class="stat"><span>Em atendimento</span><strong>{{ $summary['in_progress'] }}</strong></div>
    <div class="stat"><span>Animal pronto</span><strong>{{ $summary['ready'] }}</strong></div>
    <div class="stat"><span>Previsto no dia</span><strong>R$ {{ number_format($summary['expected_revenue'], 2, ',', '.') }}</strong></div>
  </section>

  @if(! $selectedClinicId)
    <div class="panel"><p>Selecione uma clínica para ver a agenda.</p></div>
  @else
    <div class="panel grooming-agenda-wrap">
      <div
        class="grooming-agenda"
        style="--grooming-columns: {{ $columns->count() }}; --grooming-row: {{ $rowHeight }}px; --grooming-rows: {{ $slots->count() }};"
      >
        <div class="grooming-agenda-corner">Horário</div>
        @foreach($columns as $column)
          <div class="grooming-agenda-head">
            <strong>{{ $column['name'] }}</strong>
            <span>{{ $column['orders']->count() }} atendimento(s)</span>
          </div>
        @endforeach

        <div class="grooming-agenda-times">
          @foreach($slots as $slot)
            <div class="grooming-agenda-time">{{ $slot->format('H:i') }}</div>
          @endforeach
        </div>

        @foreach($columns as $column)
          <div class="grooming-agenda-column">
            @foreach($slots as $slot)
              @php
                $slotEnd = $slot->addMinutes($slotMinutes);
                $busy = $column['orders']->contains(function ($order) use ($slot, $slotEnd) {
                    return ! in_array($order->status, ['no_show', 'finished'], true)
                        && $order->scheduled_at->lt($slotEnd)
                        && $order->scheduledEnd()->gt($slot);
                });
                $isPast = $slot->lt(now()->subMinutes($slotMinutes));
              @endphp
              @if($busy || $isPast)
                <div class="grooming-agenda-slot is-blocked" aria-hidden="true"></div>
              @else
                <a
                  class="grooming-agenda-slot"
                  href="{{ route('service-orders.create', array_merge($clinicQuery, array_filter(['status' => 'scheduled', 'scheduled_at' => $slot->format('Y-m-d H:i'), 'assigned_user_id' => $column['id']]))) }}"
                  title="Agendar {{ $slot->format('H:i') }}{{ $column['id'] ? ' com '.$column['name'] : '' }}"
                ><span>+ {{ $slot->format('H:i') }}</span></a>
              @endif
            @endforeach

            @foreach($column['orders'] as $order)
              @php
                $startMinutes = $order->scheduled_at->hour * 60 + $order->scheduled_at->minute;
                $top = max(0, ($startMinutes - $gridStartMinutes) / $slotMinutes * $rowHeight);
                $height = max($rowHeight * 0.9, $order->effectiveDuration() / $slotMinutes * $rowHeight - 4);
              @endphp
              <article
                class="grooming-agenda-event {{ $statusClass[$order->status] ?? '' }} {{ $order->isLate() ? 'is-late' : '' }} {{ $order->effectiveDuration() <= $slotMinutes ? 'is-tiny' : ($order->effectiveDuration() <= 60 ? 'is-compact' : '') }}"
                tabindex="-1"
                style="top: {{ $top }}px; height: {{ $height }}px;"
              >
                <a class="grooming-agenda-event-link" href="{{ route('service-orders.edit', $order->id) }}">
                  <span class="grooming-agenda-event-time">
                    {{ $order->scheduled_at->format('H:i') }}–{{ $order->scheduledEnd()->format('H:i') }}
                    · {{ $order->isLate() ? 'Atrasado' : $order->statusLabel() }}
                  </span>
                  <strong><span class="grooming-agenda-event-inline-time">{{ $order->scheduled_at->format('H:i') }}</span> {{ $order->patient?->name ?? 'Pet não informado' }}</strong>
                  <span>{{ $order->tutor?->name ?? 'Sem responsável' }}</span>
                  @if($order->items->isNotEmpty())
                    <span class="grooming-agenda-event-items">{{ $order->items->pluck('description')->take(2)->implode(' + ') }}</span>
                  @endif
                </a>
                @if(in_array($order->status, ['scheduled', 'confirmed', 'open', 'in_service', 'waiting_pickup'], true))
                  <div class="grooming-agenda-event-actions">
                    @if($order->status === 'scheduled')
                      <form method="POST" action="{{ route('service-orders.status', $order->id) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="confirmed"><button type="submit" class="secondary">Confirmar</button></form>
                    @endif
                    @if(in_array($order->status, ['scheduled', 'confirmed'], true))
                      <form method="POST" action="{{ route('service-orders.status', $order->id) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="open"><button type="submit">Chegou</button></form>
                      <form method="POST" action="{{ route('service-orders.status', $order->id) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="no_show"><button type="submit" class="secondary">Faltou</button></form>
                    @elseif($order->status === 'open')
                      <form method="POST" action="{{ route('service-orders.status', $order->id) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="in_service"><button type="submit">Iniciar</button></form>
                    @elseif($order->status === 'in_service')
                      <form method="POST" action="{{ route('service-orders.status', $order->id) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="waiting_pickup"><button type="submit">Animal pronto</button></form>
                    @elseif($order->status === 'waiting_pickup')
                      @can('sales.manage')
                        <a class="button" href="{{ route('sales.create', ['service_order_id' => $order->id]) }}">Receber</a>
                      @endcan
                    @endif
                  </div>
                @endif
              </article>
            @endforeach
          </div>
        @endforeach
      </div>
    </div>

    @if($columns->count() === 1 && $columns->first()['id'] === null)
      <p class="muted">Nenhum profissional ativo nesta clínica. Cadastre os tosadores em Administração › Usuários e acessos para ter uma coluna por profissional.</p>
    @elseif(! $hasFlaggedProfessionals)
      <p class="muted">Dica: em Administração › Usuários e acessos, marque "Atende banho e tosa" nos tosadores e banhistas para a agenda mostrar só eles.</p>
    @endif
  @endif
@endsection
