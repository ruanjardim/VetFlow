@extends('layouts.admin')

@section('title', 'Internações - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Internações</h1>
      <p>Admissões e altas vinculadas ao histórico do paciente.</p>
    </div>
    <a class="button" href="{{ route('hospitalizations.create') }}">Nova internação</a>
  </header>

  <div class="panel">
    <div class="table-wrap">
      <table>
        <thead><tr><th>Paciente</th><th>Responsável</th><th>Status</th><th>Leito ou setor</th><th>Admissão</th><th>Alta</th><th>Ações</th></tr></thead>
        <tbody>
          @forelse($hospitalizations as $hospitalization)
            <tr>
              <td>{{ $hospitalization->patient?->name ?? '-' }}</td>
              <td>{{ $hospitalization->patient?->tutor?->name ?? '-' }}</td>
              <td>{{ ['hospitalized' => 'Internado', 'discharged' => 'Alta registrada', 'cancelled' => 'Cancelada'][$hospitalization->status] ?? $hospitalization->status }}</td>
              <td>{{ $hospitalization->accommodation ?: '-' }}</td>
              <td>{{ optional($hospitalization->admitted_at)->format('d/m/Y H:i') }}</td>
              <td>{{ optional($hospitalization->discharged_at)->format('d/m/Y H:i') ?: '-' }}</td>
              <td><a class="button secondary" href="{{ route('hospitalizations.edit', $hospitalization->id) }}">Abrir</a></td>
            </tr>
          @empty
            <tr><td colspan="7" class="muted">Nenhuma internação registrada.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{ $hospitalizations->links() }}
@endsection
