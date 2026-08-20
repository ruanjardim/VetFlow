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
      @can('hospitalizations.manage')
        <a class="button secondary" href="{{ route('hospitalizations.create', ['patient_id' => $patient->id]) }}">Internar</a>
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

  @if($visibility['medicalRecords'])
    <section class="panel clinical-alert-management">
      <div class="panel-heading">
        <div>
          <h2>Alertas clínicos</h2>
          <p>Registros objetivos e auditáveis exibidos na ficha, no prontuário, na prescrição e na internação.</p>
        </div>
        <span class="badge {{ $activeClinicalAlerts->isNotEmpty() ? 'danger' : 'success' }}">
          {{ $activeClinicalAlerts->count() }} {{ $activeClinicalAlerts->count() === 1 ? 'ativo' : 'ativos' }}
        </span>
      </div>

      @if($activeClinicalAlerts->isNotEmpty())
        <div class="clinical-alerts-list" role="alert" aria-label="Alertas clínicos ativos">
          @foreach($activeClinicalAlerts as $clinicalAlert)
            <article class="clinical-alert-card">
              <strong>{{ $clinicalAlert->title }}</strong>
              @if($clinicalAlert->details)<p>{!! nl2br(e($clinicalAlert->details)) !!}</p>@endif
              <small>Registrado em {{ optional($clinicalAlert->created_at)->format('d/m/Y H:i') }} por {{ $clinicalAlert->createdBy?->name ?? '-' }}.</small>
              <form class="clinical-alert-resolution" method="POST" action="{{ route('patient-clinical-alerts.resolve', [$patient->id, $clinicalAlert->id]) }}">
                @csrf
                @method('PATCH')
                <div class="field">
                  <label for="resolution_notes_{{ $clinicalAlert->id }}">Motivo da resolução</label>
                  <textarea id="resolution_notes_{{ $clinicalAlert->id }}" name="resolution_notes" required minlength="10" maxlength="2000" placeholder="Descreva o fato que permite encerrar este alerta."></textarea>
                </div>
                <button class="secondary" type="submit" data-confirm="Resolver este alerta mantendo-o no histórico?">Resolver alerta</button>
              </form>
            </article>
          @endforeach
        </div>
      @else
        <p class="muted">Nenhum alerta clínico ativo para este paciente.</p>
      @endif

      <form method="POST" action="{{ route('patient-clinical-alerts.store', $patient->id) }}">
        @csrf
        <div class="form-grid">
          <div class="field">
            <label for="clinical_alert_title">Novo alerta</label>
            <input id="clinical_alert_title" name="title" required maxlength="160" value="{{ old('title') }}" placeholder="Ex.: reação documentada a medicamento">
          </div>
          <div class="field full">
            <label for="clinical_alert_details">Detalhes observados</label>
            <textarea id="clinical_alert_details" name="details" maxlength="5000" rows="3">{{ old('details') }}</textarea>
            <small>Registre fatos conhecidos. O VetFlow não classifica gravidade nem gera interpretação clínica.</small>
          </div>
        </div>
        <button type="submit">Registrar alerta</button>
      </form>

      @if($resolvedClinicalAlerts->isNotEmpty())
        <div class="table-wrap">
          <table>
            <thead><tr><th>Alerta resolvido</th><th>Registrado por</th><th>Resolução</th><th>Resolvido por</th></tr></thead>
            <tbody>
              @foreach($resolvedClinicalAlerts as $clinicalAlert)
                <tr>
                  <td><strong>{{ $clinicalAlert->title }}</strong><br><span class="muted">{{ optional($clinicalAlert->created_at)->format('d/m/Y H:i') }}</span></td>
                  <td>{{ $clinicalAlert->createdBy?->name ?? '-' }}</td>
                  <td>{!! nl2br(e($clinicalAlert->resolution_notes)) !!}<br><span class="muted">{{ optional($clinicalAlert->resolved_at)->format('d/m/Y H:i') }}</span></td>
                  <td>{{ $clinicalAlert->resolvedBy?->name ?? '-' }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </section>
  @endif

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

  @if($visibility['prescriptions'])
    <section class="panel">
      <div class="panel-heading"><div><h2>Prescrições</h2><p>Até 10 documentos terapêuticos mais recentes.</p></div></div>
      <div class="table-wrap"><table><thead><tr><th>Data</th><th>Prontuário</th><th>Itens</th><th>Status</th><th>Ações</th></tr></thead><tbody>
        @forelse($prescriptions as $prescription)
          @php($prescriptionStatusClass = match($prescription->status) { 'finalized' => 'success', 'cancelled' => 'danger', default => 'warning' })
          <tr>
            <td>{{ optional($prescription->prescribed_at)->format('d/m/Y H:i') }}</td>
            <td>
              @if($visibility['medicalRecords'] && $prescription->medicalRecord)
                <a href="{{ route('medical-records.show', $prescription->medicalRecord->id) }}">#{{ $prescription->medicalRecord->id }}</a>
              @else
                <span class="muted">Acesso restrito</span>
              @endif
            </td>
            <td>
              <strong>{{ $prescription->items->count() }} {{ $prescription->items->count() === 1 ? 'item' : 'itens' }}</strong>
              <br><span class="muted">{{ $prescription->items->pluck('medication_name')->join(', ') }}</span>
            </td>
            <td><span class="badge {{ $prescriptionStatusClass }}">{{ \App\Modules\Prescriptions\Models\Prescription::STATUS_LABELS[$prescription->status] ?? $prescription->status }}</span></td>
            <td><a class="button secondary" href="{{ route('prescriptions.show', $prescription->id) }}">Abrir</a></td>
          </tr>
        @empty
          <tr><td colspan="5" class="muted">Nenhuma prescrição registrada para este paciente.</td></tr>
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

  @if($visibility['hospitalizations'])
    <section class="panel">
      <div class="panel-heading"><div><h2>Internações</h2><p>Até 10 admissões mais recentes.</p></div></div>
      <div class="table-wrap"><table><thead><tr><th>Admissão</th><th>Status</th><th>Leito ou setor</th><th>Evoluções</th><th>Alta</th><th>Ações</th></tr></thead><tbody>
        @forelse($hospitalizations as $hospitalization)
          <tr>
            <td>{{ optional($hospitalization->admitted_at)->format('d/m/Y H:i') }}</td>
            <td>{{ ['hospitalized' => 'Internado', 'discharged' => 'Alta registrada', 'cancelled' => 'Cancelada'][$hospitalization->status] ?? $hospitalization->status }}</td>
            <td>{{ $hospitalization->accommodation ?: '-' }}</td>
            <td>{{ $hospitalization->evolutions_count }}</td>
            <td>{{ optional($hospitalization->discharged_at)->format('d/m/Y H:i') ?: '-' }}</td>
            <td><a class="button secondary" href="{{ route('hospitalizations.edit', $hospitalization->id) }}">Abrir</a></td>
          </tr>
        @empty
          <tr><td colspan="6" class="muted">Nenhuma internação registrada para este paciente.</td></tr>
        @endforelse
      </tbody></table></div>
    </section>
  @endif
@endsection
