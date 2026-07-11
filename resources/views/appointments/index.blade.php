@extends('layouts.admin')

@section('title', 'Consultas - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Consultas</h1>
      <p>Atendimentos clinicos agendados.</p>
    </div>
    <a class="button" href="{{ route('appointments.create') }}">Nova consulta</a>
  </header>

  <div class="panel">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Titulo</th>
            <th>Data</th>
            <th>Paciente</th>
            <th>Tutor</th>
            <th>Status</th>
            <th>Acoes</th>
          </tr>
        </thead>
        <tbody>
          @forelse($appointments as $appointment)
            <tr>
              <td>{{ $appointment->title }}</td>
              <td>{{ optional($appointment->scheduled_at)->format('d/m/Y H:i') }}</td>
              <td>{{ $appointment->patient_id }}</td>
              <td>{{ $appointment->tutor_id }}</td>
              <td>{{ $appointment->status }}</td>
              <td>
                <a class="button secondary" href="{{ route('appointments.edit', $appointment->id) }}">Editar</a>
                <form class="inline" action="{{ route('appointments.destroy', $appointment->id) }}" method="POST">
                  @csrf
                  @method('DELETE')
                  <button class="danger" type="submit" data-confirm="Remover esta consulta?">Excluir</button>
                </form>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="muted">Nenhuma consulta cadastrada.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{ $appointments->links() }}
@endsection
