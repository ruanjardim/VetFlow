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
    <form method="GET" action="{{ route('patients.index') }}" class="form-grid">
      <div class="field">
        <label for="patient-search">Buscar paciente</label>
        <input
          id="patient-search"
          name="q"
          value="{{ $search }}"
          maxlength="100"
          placeholder="Nome, responsável, microchip, pedigree ou marcação"
          autocomplete="off"
        >
      </div>
      <div class="field">
        <label for="patient-status">Estado da ficha</label>
        <select id="patient-status" name="status">
          @foreach($lifecycleLabels as $statusValue => $statusLabel)
            <option value="{{ $statusValue }}" @selected($lifecycleStatus === $statusValue)>{{ $statusLabel }}</option>
          @endforeach
          <option value="all" @selected($lifecycleStatus === 'all')>Todos os estados</option>
        </select>
      </div>
      <div class="field full">
        <div class="actions">
          <button type="submit">Buscar</button>
          @if($search !== '' || $lifecycleStatus !== 'active')
            <a class="button secondary" href="{{ route('patients.index') }}">Limpar busca</a>
          @endif
        </div>
      </div>
    </form>
  </div>

  <div class="panel">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Nome</th>
            <th>Responsável</th>
            <th>Espécie</th>
            <th>Raça</th>
            <th>Microchip</th>
            <th>Estado</th>
            <th>Esterilização</th>
            <th>Peso</th>
            <th>Acoes</th>
          </tr>
        </thead>
        <tbody>
          @forelse($patients as $patient)
            <tr>
              <td>
                <div class="patient-list-identity">
                  <div class="patient-photo-frame patient-photo-frame-small">
                    @if($patient->photo_path)
                      <img src="{{ route('patients.photo', $patient->id) }}" alt="Foto de {{ $patient->name }}">
                    @else
                      <span aria-hidden="true">🐾</span>
                    @endif
                  </div>
                  <strong>{{ $patient->name }}</strong>
                </div>
              </td>
              <td>{{ $patient->tutor?->name ?? 'Sem responsável vinculado' }}</td>
              <td>{{ $patient->animalSpecies?->name ?? $patient->species ?? '-' }}</td>
              <td>{{ $patient->animalBreed?->name ?? $patient->breed ?? '-' }}</td>
              <td>{{ $patient->microchip ?? '-' }}</td>
              <td>
                <span class="badge {{ $patient->lifecycle_status === 'active' ? 'success' : ($patient->lifecycle_status === 'deceased' ? 'danger' : 'muted-badge') }}">
                  {{ $lifecycleLabels[$patient->lifecycle_status] ?? 'Não informado' }}
                </span>
              </td>
              <td>{{ $patient->neutered === null ? 'Não informado' : ($patient->neutered ? 'Sim' : 'Não') }}</td>
              <td>{{ $patient->weight !== null ? $patient->weight.' kg' : '-' }}</td>
              <td>
                <a class="button secondary" href="{{ route('patients.show', $patient->id) }}">Ficha</a>
                @if($patient->isAvailable())
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
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="9" class="muted">
                {{ $search !== '' ? 'Nenhum paciente encontrado para esta busca.' : 'Nenhum paciente cadastrado.' }}
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{ $patients->links() }}
@endsection
