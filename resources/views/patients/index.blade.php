@extends('layouts.admin')

@section('title', 'Pacientes - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Pacientes</h1>
      <p>Cadastre o pet e siga para a agenda ou para a consulta.</p>
    </div>
    <a class="button" href="{{ route('patients.create') }}">Novo paciente</a>
  </header>

  <div class="panel">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Nome</th>
            <th>Responsável</th>
            <th>Espécie</th>
            <th>Raça</th>
            <th>Peso</th>
            <th>Acoes</th>
          </tr>
        </thead>
        <tbody>
          @forelse($patients as $patient)
            <tr>
              <td>{{ $patient->name }}</td>
              <td>{{ $patient->tutor?->name ?? 'Sem responsável vinculado' }}</td>
              <td>{{ $patient->species }}</td>
              <td>{{ $patient->breed }}</td>
              <td>{{ $patient->weight }}</td>
              <td>
                @can('schedules.manage')
                  <a class="button secondary" href="{{ route('schedules.create', ['patient_id' => $patient->id, 'tutor_id' => $patient->tutor_id]) }}">Agendar</a>
                @endcan
                @can('appointments.manage')
                  <a class="button secondary" href="{{ route('appointments.create', ['patient_id' => $patient->id, 'tutor_id' => $patient->tutor_id]) }}">Consulta</a>
                @endcan
                @can('vaccinations.manage')
                  <a class="button secondary" href="{{ route('vaccinations.create', ['patient_id' => $patient->id]) }}">Vacinas</a>
                @endcan
                <a class="button secondary" href="{{ route('patients.edit', $patient->id) }}">Editar</a>
                <form class="inline" action="{{ route('patients.destroy', $patient->id) }}" method="POST">
                  @csrf
                  @method('DELETE')
                  <button class="danger" type="submit" data-confirm="Remover este paciente?">Excluir</button>
                </form>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="muted">Nenhum paciente cadastrado.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{ $patients->links() }}
@endsection
