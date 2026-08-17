@extends('layouts.admin')

@section('title', 'Ficha do paciente - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Ficha de {{ $patient->name }}</h1>
      <p>Histórico consolidado do paciente, sem alterar os registros clínicos originais.</p>
    </div>
    <div class="actions">
      @can('schedules.manage')
        <a class="button secondary" href="{{ route('schedules.create', ['patient_id' => $patient->id, 'tutor_id' => $patient->tutor_id]) }}">Agendar</a>
      @endcan
      @can('appointments.manage')
        <a class="button secondary" href="{{ route('appointments.create', ['patient_id' => $patient->id, 'tutor_id' => $patient->tutor_id]) }}">Nova consulta</a>
      @endcan
      @can('vaccinations.manage')
        <a class="button secondary" href="{{ route('vaccinations.create', ['patient_id' => $patient->id]) }}">Nova vacina</a>
      @endcan
      <a class="button secondary" href="{{ route('patients.edit', $patient->id) }}">Editar cadastro</a>
      <a class="button secondary" href="{{ route('patients.index') }}">Voltar</a>
    </div>
  </header>

  <section class="panel">
    <h2>Identificação</h2>
    <div class="form-grid">
      <div class="field"><label>Responsável</label><input value="{{ $patient->tutor?->name ?? 'Sem responsável vinculado' }}" disabled></div>
      <div class="field"><label>Espécie</label><input value="{{ $patient->animalSpecies?->name ?? $patient->species ?? '-' }}" disabled></div>
      <div class="field"><label>Raça ou variedade</label><input value="{{ $patient->animalBreed?->name ?? $patient->breed ?? '-' }}" disabled></div>
      <div class="field"><label>Pelagem ou padrão</label><input value="{{ $patient->animalCoat?->name ?? '-' }}" disabled></div>
      <div class="field"><label>Sexo</label><input value="{{ $patient->gender ?? '-' }}" disabled></div>
      <div class="field"><label>Data de nascimento</label><input value="{{ optional($patient->birth_date)->format('d/m/Y') ?: '-' }}" disabled></div>
      <div class="field"><label>Peso cadastrado</label><input value="{{ $patient->weight !== null ? $patient->weight.' kg' : '-' }}" disabled></div>
      <div class="field full"><label>Observações do cadastro</label><div class="panel">{!! $patient->notes ? nl2br(e($patient->notes)) : '<span class="muted">Nenhuma observação cadastrada.</span>' !!}</div></div>
    </div>
  </section>

  @if($visibility['appointments'])
    <section class="panel">
      <div class="panel-heading"><div><h2>Consultas</h2><p>Até 10 atendimentos mais recentes.</p></div></div>
      <div class="table-wrap"><table><thead><tr><th>Data</th><th>Atendimento</th><th>Status</th><th>Prontuário</th></tr></thead><tbody>
        @forelse($appointments as $appointment)
          <tr>
            <td>{{ optional($appointment->scheduled_at)->format('d/m/Y H:i') }}</td>
            <td>{{ $appointment->title }}</td>
            <td>{{ $appointment->status }}</td>
            <td>
              @can('medical-records.manage')
                @if($appointment->medicalRecord)
                  <a class="button secondary" href="{{ route('medical-records.show', $appointment->medicalRecord->id) }}">Ver prontuário</a>
                @else
                  <a class="button secondary" href="{{ route('medical-records.create', ['appointment_id' => $appointment->id, 'patient_id' => $patient->id]) }}">Criar prontuário</a>
                @endif
              @else
                <span class="muted">Sem permissão clínica</span>
              @endcan
            </td>
          </tr>
        @empty
          <tr><td colspan="4" class="muted">Nenhuma consulta registrada para este paciente.</td></tr>
        @endforelse
      </tbody></table></div>
    </section>
  @endif

  @if($visibility['medicalRecords'])
    <section class="panel">
      <div class="panel-heading"><div><h2>Prontuários</h2><p>Até 10 registros clínicos mais recentes.</p></div></div>
      <div class="table-wrap"><table><thead><tr><th>Atendimento</th><th>Consulta</th><th>Diagnóstico</th><th>Ações</th></tr></thead><tbody>
        @forelse($medicalRecords as $medicalRecord)
          <tr>
            <td>{{ optional($medicalRecord->examined_at)->format('d/m/Y H:i') }}</td>
            <td>{{ $medicalRecord->appointment?->title ?? '-' }}</td>
            <td>{{ \Illuminate\Support\Str::limit($medicalRecord->diagnosis ?: 'Não informado', 100) }}</td>
            <td><a class="button secondary" href="{{ route('medical-records.show', $medicalRecord->id) }}">Abrir</a></td>
          </tr>
        @empty
          <tr><td colspan="4" class="muted">Nenhum prontuário registrado para este paciente.</td></tr>
        @endforelse
      </tbody></table></div>
    </section>
  @endif

  @if($visibility['vaccinations'])
    <section class="panel">
      <div class="panel-heading"><div><h2>Carteira de vacinação</h2><p>Até 10 aplicações ou agendamentos mais recentes.</p></div></div>
      <div class="table-wrap"><table><thead><tr><th>Vacina</th><th>Status</th><th>Agendada</th><th>Aplicada</th><th>Próxima dose</th><th>Ações</th></tr></thead><tbody>
        @forelse($vaccinations as $vaccination)
          <tr>
            <td>{{ $vaccination->vaccine_name }}</td>
            <td>{{ ['scheduled' => 'Agendada', 'applied' => 'Aplicada', 'skipped' => 'Não aplicada'][$vaccination->status] ?? $vaccination->status }}</td>
            <td>{{ optional($vaccination->scheduled_for)->format('d/m/Y') }}</td>
            <td>{{ optional($vaccination->applied_at)->format('d/m/Y H:i') ?: '-' }}</td>
            <td>{{ optional($vaccination->next_due_at)->format('d/m/Y') ?: '-' }}</td>
            <td><a class="button secondary" href="{{ route('vaccinations.edit', $vaccination->id) }}">Editar</a></td>
          </tr>
        @empty
          <tr><td colspan="6" class="muted">Nenhuma vacina registrada para este paciente.</td></tr>
        @endforelse
      </tbody></table></div>
    </section>
  @endif
@endsection
