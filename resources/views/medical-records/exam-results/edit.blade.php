@extends('layouts.admin')

@section('title', 'Resultado de exame - VetFlow')

@section('content')
  @php($result = $examRequest->result)
  @php($isEditable = ! $result || $result->isDraft())
  @php($statusClass = match($result?->status) { 'finalized' => 'success', 'cancelled' => 'danger', 'draft' => 'warning', default => 'muted-badge' })

  <header class="topbar">
    <div>
      <h1>{{ $examRequest->exam_name }}</h1>
      <p>Resultado vinculado ao prontuário #{{ $examRequest->medical_record_id }}.</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('medical-records.show', $examRequest->medical_record_id) }}">Voltar ao prontuário</a>
    </div>
  </header>

  <section class="panel">
    <div class="panel-heading">
      <div>
        <h2>Contexto clínico</h2>
        <p>O VetFlow registra o conteúdo informado; não interpreta nem classifica automaticamente o resultado.</p>
      </div>
      <span class="badge {{ $statusClass }}">{{ $result ? (\App\Modules\MedicalRecords\Models\MedicalRecordExamResult::STATUS_LABELS[$result->status] ?? $result->status) : 'Não iniciado' }}</span>
    </div>
    <div class="form-grid">
      <div class="field"><label>Paciente</label><input value="{{ $examRequest->medicalRecord?->patient?->name ?? '-' }}" disabled></div>
      <div class="field"><label>Responsável</label><input value="{{ $examRequest->medicalRecord?->patient?->tutor?->name ?? '-' }}" disabled></div>
      <div class="field"><label>Prontuário</label><input value="#{{ $examRequest->medical_record_id }}" disabled></div>
      <div class="field"><label>Solicitado em</label><input value="{{ optional($examRequest->created_at)->format('d/m/Y H:i') }}" disabled></div>
    </div>
  </section>

  @if($result?->status === 'cancelled')
    <div class="alert error">
      <strong>Resultado cancelado.</strong>
      {{ $result->cancellation_reason }}
      @if($result->cancelled_at)
        Registro realizado em {{ $result->cancelled_at->format('d/m/Y H:i') }} por {{ $result->cancelledBy?->name ?? '-' }}.
      @endif
    </div>
  @endif

  @if($isEditable)
    <form method="POST" action="{{ route('exam-results.save', $examRequest->id) }}" class="panel">
      @csrf
      @method('PUT')
      <div class="panel-heading">
        <div><h2>Conteúdo do resultado</h2><p>Salve como rascunho enquanto o documento ainda estiver em conferência.</p></div>
      </div>
      <div class="form-grid">
        <div class="field">
          <label for="collected_at">Coletado em</label>
          <input id="collected_at" type="datetime-local" name="collected_at" value="{{ old('collected_at', optional($result?->collected_at)->format('Y-m-d\TH:i')) }}">
        </div>
        <div class="field">
          <label for="resulted_at">Resultado emitido em</label>
          <input id="resulted_at" type="datetime-local" name="resulted_at" value="{{ old('resulted_at', optional($result?->resulted_at)->format('Y-m-d\TH:i')) }}">
        </div>
        <div class="field full">
          <label for="laboratory_name">Laboratório ou origem</label>
          <input id="laboratory_name" name="laboratory_name" maxlength="160" value="{{ old('laboratory_name', $result?->laboratory_name) }}">
        </div>
        <div class="field full">
          <label for="result_summary">Resumo</label>
          <textarea id="result_summary" name="result_summary" maxlength="5000" rows="4">{{ old('result_summary', $result?->result_summary) }}</textarea>
        </div>
        <div class="field full">
          <label for="result_details">Detalhes do resultado</label>
          <textarea id="result_details" name="result_details" maxlength="30000" rows="10">{{ old('result_details', $result?->result_details) }}</textarea>
        </div>
        <div class="field full">
          <label for="reference_notes">Referências informadas pelo laboratório</label>
          <textarea id="reference_notes" name="reference_notes" maxlength="5000" rows="4">{{ old('reference_notes', $result?->reference_notes) }}</textarea>
        </div>
        <div class="field full">
          <label for="notes">Observações internas</label>
          <textarea id="notes" name="notes" maxlength="5000" rows="4">{{ old('notes', $result?->notes) }}</textarea>
        </div>
      </div>
      <div class="actions">
        <button type="submit">Salvar rascunho</button>
      </div>
    </form>

    @if($result?->isDraft())
      <section class="panel">
        <h2>Finalizar resultado</h2>
        <p class="muted">Após a finalização, o conteúdo fica protegido contra alterações. Um resultado finalizado só poderá ser cancelado com motivo registrado.</p>
        <form method="POST" action="{{ route('exam-results.finalize', $examRequest->id) }}">
          @csrf
          @method('PATCH')
          <button type="submit" data-confirm="Finalizar este resultado? O conteúdo não poderá mais ser alterado.">Finalizar resultado</button>
        </form>
      </section>
    @endif
  @else
    <section class="panel">
      <div class="panel-heading">
        <div><h2>Resultado registrado</h2><p>Conteúdo histórico protegido contra alterações.</p></div>
      </div>
      <div class="form-grid">
        <div class="field"><label>Coletado em</label><input value="{{ optional($result->collected_at)->format('d/m/Y H:i') ?: '-' }}" disabled></div>
        <div class="field"><label>Resultado emitido em</label><input value="{{ optional($result->resulted_at)->format('d/m/Y H:i') ?: '-' }}" disabled></div>
        <div class="field full"><label>Laboratório ou origem</label><input value="{{ $result->laboratory_name ?: '-' }}" disabled></div>
        @foreach([
          'Resumo' => $result->result_summary,
          'Detalhes do resultado' => $result->result_details,
          'Referências informadas pelo laboratório' => $result->reference_notes,
          'Observações internas' => $result->notes,
        ] as $label => $value)
          <div class="field full"><label>{{ $label }}</label><div class="panel">{!! $value ? nl2br(e($value)) : '<span class="muted">Não informado.</span>' !!}</div></div>
        @endforeach
      </div>
      @if($result->finalized_at)
        <p class="muted">Finalizado em {{ $result->finalized_at->format('d/m/Y H:i') }} por {{ $result->finalizedBy?->name ?? '-' }}.</p>
      @endif
    </section>

    @if($result->isFinalized())
      <section class="panel">
        <h2>Cancelar resultado</h2>
        <p class="muted">O conteúdo continuará visível no histórico com o motivo e o usuário responsável.</p>
        <form method="POST" action="{{ route('exam-results.cancel', $examRequest->id) }}">
          @csrf
          @method('PATCH')
          <div class="field">
            <label for="cancellation_reason">Motivo do cancelamento</label>
            <textarea id="cancellation_reason" name="cancellation_reason" required minlength="10" maxlength="2000"></textarea>
          </div>
          <button class="danger" type="submit" data-confirm="Cancelar este resultado finalizado?">Confirmar cancelamento</button>
        </form>
      </section>
    @endif
  @endif
@endsection
