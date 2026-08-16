@extends('layouts.admin')

@section('title', 'Prontuário - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Prontuário de {{ $medicalRecord->patient?->name }}</h1>
      <p>Consulta: {{ $medicalRecord->appointment?->title }} em {{ optional($medicalRecord->appointment?->scheduled_at)->format('d/m/Y H:i') }}</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('medical-records.edit', $medicalRecord->id) }}">Editar</a>
      <a class="button secondary" href="{{ route('medical-records.index') }}">Voltar</a>
    </div>
  </header>

  <section class="panel">
    <div class="form-grid">
      <div class="field">
        <label>Paciente</label>
        <input value="{{ $medicalRecord->patient?->name }}" disabled>
      </div>
      <div class="field">
        <label>Responsável</label>
        <input value="{{ $medicalRecord->patient?->tutor?->name ?? $medicalRecord->appointment?->tutor?->name ?? '-' }}" disabled>
      </div>
      <div class="field">
        <label>Atendimento</label>
        <input value="{{ optional($medicalRecord->examined_at)->format('d/m/Y H:i') }}" disabled>
      </div>
      <div class="field">
        <label>Registrado por</label>
        <input value="{{ $medicalRecord->createdBy?->name ?? '-' }}" disabled>
      </div>
    </div>
  </section>

  <section class="panel">
    <h2>Sinais vitais</h2>
    <div class="form-grid">
      <div class="field"><label>Peso</label><input value="{{ $medicalRecord->weight !== null ? $medicalRecord->weight.' kg' : '-' }}" disabled></div>
      <div class="field"><label>Temperatura</label><input value="{{ $medicalRecord->temperature !== null ? $medicalRecord->temperature.' °C' : '-' }}" disabled></div>
      <div class="field"><label>Frequência cardíaca</label><input value="{{ $medicalRecord->heart_rate !== null ? $medicalRecord->heart_rate.' bpm' : '-' }}" disabled></div>
      <div class="field"><label>Frequência respiratória</label><input value="{{ $medicalRecord->respiratory_rate !== null ? $medicalRecord->respiratory_rate.' mpm' : '-' }}" disabled></div>
      <div class="field full"><label>Hidratação</label><input value="{{ $medicalRecord->hydration ?? '-' }}" disabled></div>
    </div>
  </section>

  <section class="panel">
    <h2>Registro clínico</h2>
    <div class="form-grid">
      <div class="field full">
        <label>Patologias padronizadas</label>
        @if($medicalRecord->pathologies->isNotEmpty())
          <div class="pathology-list">
            @foreach($medicalRecord->pathologies as $pathology)
              <span class="badge {{ $pathology->system ? 'muted-badge' : 'success' }}">{{ $pathology->name }}</span>
            @endforeach
          </div>
        @else
          <div class="panel"><span class="muted">Nenhuma patologia padronizada vinculada.</span></div>
        @endif
      </div>
      @foreach([
        'Queixa principal' => $medicalRecord->chief_complaint,
        'Anamnese' => $medicalRecord->anamnesis,
        'Achados clínicos' => $medicalRecord->clinical_findings,
        'Diagnóstico' => $medicalRecord->diagnosis,
        'Plano terapêutico' => $medicalRecord->treatment_plan,
        'Orientações e prescrição anotada' => $medicalRecord->prescription_notes,
        'Observações adicionais' => $medicalRecord->notes,
      ] as $label => $value)
        <div class="field full">
          <label>{{ $label }}</label>
          <div class="panel">{!! $value ? nl2br(e($value)) : '<span class="muted">Não informado.</span>' !!}</div>
        </div>
      @endforeach
    </div>
  </section>

  <section class="panel">
    <h2>Exames solicitados</h2>
    @if($medicalRecord->examRequests->isNotEmpty())
      <div class="table-wrap"><table><thead><tr><th>Exame</th><th>Solicitado em</th></tr></thead><tbody>
        @foreach($medicalRecord->examRequests as $examRequest)
          <tr><td>{{ $examRequest->exam_name }}</td><td>{{ optional($examRequest->created_at)->format('d/m/Y H:i') }}</td></tr>
        @endforeach
      </tbody></table></div>
    @else
      <p class="muted">Nenhum exame estruturado foi solicitado neste prontuário.</p>
    @endif
  </section>
@endsection
