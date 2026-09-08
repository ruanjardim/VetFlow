@extends('layouts.admin')

@section('title', 'Prontuários - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Prontuários</h1>
      <p>Histórico clínico registrado por consulta.</p>
    </div>
    <a class="button" href="{{ route('medical-records.create') }}">Novo prontuário</a>
  </header>

  <div class="panel">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Atendimento</th>
            <th>Paciente</th>
            <th>Consulta</th>
            <th>Diagnóstico</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          @forelse($medicalRecords as $medicalRecord)
            <tr>
              <td>{{ optional($medicalRecord->examined_at)->format('d/m/Y H:i') }}</td>
              <td>{{ $medicalRecord->patient?->name ?? '-' }}</td>
              <td>{{ $medicalRecord->appointment?->title ?? '-' }}</td>
              <td>{{ \Illuminate\Support\Str::limit($medicalRecord->diagnosis, 80) ?: '-' }}</td>
              <td>
                <a class="button secondary" href="{{ route('medical-records.show', $medicalRecord->id) }}">Abrir</a>
                <a class="button secondary" href="{{ route('medical-records.edit', $medicalRecord->id) }}">Editar</a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="5" class="muted">Nenhum prontuário registrado.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{ $medicalRecords->links() }}
@endsection
