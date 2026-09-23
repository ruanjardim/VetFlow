@extends('layouts.admin')

@section('title', 'Agenda - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Agenda</h1>
      <p>Consultas, compromissos e banho e tosa em uma visão visual.</p>
    </div>
    <div class="actions">
      @can('service-orders.manage')
        <a class="button secondary" href="{{ route('service-orders.agenda', ['date' => $anchorDate->toDateString()]) }}">Agenda banho e tosa</a>
      @endcan
      <a class="button" href="{{ route('schedules.create') }}">Novo agendamento</a>
    </div>
  </header>

  <section class="agenda-toolbar panel">
    <div class="agenda-navigation">
      <a class="button secondary" href="{{ route('schedules.index', ['view' => $calendarView, 'date' => $previousDate, 'area' => $calendarArea]) }}">Anterior</a>
      <a class="button secondary" href="{{ route('schedules.index', ['view' => $calendarView, 'date' => $todayDate, 'area' => $calendarArea]) }}">Hoje</a>
      <a class="button secondary" href="{{ route('schedules.index', ['view' => $calendarView, 'date' => $nextDate, 'area' => $calendarArea]) }}">Próximo</a>
    </div>
    <strong>{{ $calendarView === 'day' ? $anchorDate->translatedFormat('d \d\e F \d\e Y') : ($calendarView === 'month' ? $anchorDate->translatedFormat('F \d\e Y') : $periodStart->format('d/m').' a '.$periodEnd->format('d/m/Y')) }}</strong>
    @if(! empty($calendarAreas))
      <div class="agenda-views" role="group" aria-label="Filtrar por área">
        @foreach($calendarAreas as $areaValue => $areaLabel)
          <a class="button {{ $calendarArea === $areaValue ? '' : 'secondary' }}" href="{{ route('schedules.index', ['view' => $calendarView, 'date' => $anchorDate->toDateString(), 'area' => $areaValue]) }}">{{ $areaLabel }}</a>
        @endforeach
      </div>
    @endif
    <div class="agenda-views">
      @foreach(['day' => 'Dia', 'week' => 'Semana', 'month' => 'Mês'] as $view => $label)
        <a class="button {{ $calendarView === $view ? '' : 'secondary' }}" href="{{ route('schedules.index', ['view' => $view, 'date' => $anchorDate->toDateString(), 'area' => $calendarArea]) }}">{{ $label }}</a>
      @endforeach
    </div>
  </section>

  <section class="agenda-calendar agenda-{{ $calendarView }}">
    @if($calendarView !== 'day')
      <div class="agenda-weekdays">
        @foreach(['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'] as $weekday)
          <span>{{ $weekday }}</span>
        @endforeach
      </div>
    @endif

    <div class="agenda-days">
      @foreach($calendarDays as $day)
        @php($events = $eventsByDate->get($day->toDateString(), collect()))
        <article class="agenda-day {{ $day->isToday() ? 'is-today' : '' }} {{ $calendarView === 'month' && ! $day->isSameMonth($anchorDate) ? 'is-outside-month' : '' }}">
          <header><strong>{{ $calendarView === 'day' ? $day->translatedFormat('l, d \d\e F') : $day->format('d') }}</strong><span>{{ $events->count() }} evento(s)</span></header>
          <div class="agenda-events">
            @forelse($events as $event)
              <a class="agenda-event is-{{ $event['kind'] }}" href="{{ $event['url'] }}">
                <span class="agenda-event-meta">{{ $event['time'] ?? 'Sem horário' }} · {{ $event['kind_label'] }}</span>
                <strong>{{ $event['title'] }}</strong>
                <small>{{ $event['patient'] ?? 'Sem paciente' }}{{ $event['tutor'] ? ' · '.$event['tutor'] : '' }}</small>
              </a>
            @empty
              <span class="muted agenda-empty">Nenhum evento.</span>
            @endforelse
          </div>
        </article>
      @endforeach
    </div>
  </section>
@endsection
