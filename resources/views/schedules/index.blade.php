@extends('layouts.admin')

@section('title', 'Agenda - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Agenda</h1>
      <p>Compromissos e eventos da operacao.</p>
    </div>
    <a class="button" href="{{ route('schedules.create') }}">Novo agendamento</a>
  </header>

  <div class="panel">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Titulo</th>
            <th>Data</th>
            <th>Hora</th>
            <th>Pet</th>
            <th>Tutor</th>
            <th>Tipo</th>
            <th>Status</th>
            <th>Acoes</th>
          </tr>
        </thead>
        <tbody>
          @forelse($schedules as $schedule)
            <tr>
              <td>{{ $schedule->title }}</td>
              <td>{{ $schedule->scheduled_date }}</td>
              <td>{{ $schedule->scheduled_time }}</td>
              <td>{{ $schedule->patient?->name ?? '-' }}</td>
              <td>{{ $schedule->tutor?->name ?? '-' }}</td>
              <td>{{ $schedule->type }}</td>
              <td>{{ $schedule->status }}</td>
              <td>
                <a class="button secondary" href="{{ route('schedules.edit', $schedule->id) }}">Editar</a>
                <form class="inline" action="{{ route('schedules.destroy', $schedule->id) }}" method="POST">
                  @csrf
                  @method('DELETE')
                  <button class="danger" type="submit" data-confirm="Remover este agendamento?">Excluir</button>
                </form>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="muted">Nenhum agendamento cadastrado.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{ $schedules->links() }}
@endsection
