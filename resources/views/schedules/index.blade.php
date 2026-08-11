@extends('layouts.admin')

@section('title', 'Agenda - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Agenda</h1>
      <p>Consultas e compromissos da operação em uma visão visual.</p>
    </div>
    <a class="button" href="{{ route('schedules.create') }}">Novo agendamento</a>
  </header>

  <section class="agenda-toolbar panel">
    <div class="agenda-navigation">
      <a class="button secondary" href="{{ route('schedules.index', ['view' => $calendarView, 'date' => $previousDate]) }}">Anterior</a>
      <a class="button secondary" href="{{ route('schedules.index', ['view' => $calendarView, 'date' => $todayDate]) }}">Hoje</a>
      <a class="button secondary" href="{{ route('schedules.index', ['view' => $calendarView, 'date' => $nextDate]) }}">Próximo</a>
    </div>
    <strong>{{ $calendarView === 'day' ? $anchorDate->translatedFormat('d \d\e F \d\e Y') : ($calendarView === 'month' ? $anchorDate->translatedFormat('F \d\e Y') : $periodStart->format('d/m').' a '.$periodEnd->format('d/m/Y')) }}</strong>
    <div class="agenda-views">
      @foreach(['day' => 'Dia', 'week' => 'Semana', 'month' => 'Mês'] as $view => $label)
        <a class="button {{ $calendarView === $view ? '' : 'secondary' }}" href="{{ route('schedules.index', ['view' => $view, 'date' => $anchorDate->toDateString()]) }}">{{ $label }}</a>
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
