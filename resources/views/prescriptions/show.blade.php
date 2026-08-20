@extends('layouts.admin')

@section('title', 'Prescrição #'.$prescription->id.' - VetFlow')

@section('content')
  @php($statusClass = match($prescription->status) { 'finalized' => 'success', 'cancelled' => 'danger', default => 'warning' })

  <header class="topbar prescription-print-actions">
    <div>
      <h1>Prescrição #{{ $prescription->id }}</h1>
      <p>Documento clínico vinculado ao prontuário #{{ $prescription->medical_record_id }}.</p>
    </div>
    <div class="actions">
      <button class="button secondary" type="button" data-print-page>Imprimir</button>
      @if($prescription->isDraft())
        <a class="button secondary" href="{{ route('prescriptions.edit', $prescription->id) }}">Editar rascunho</a>
        <form method="POST" action="{{ route('prescriptions.finalize', $prescription->id) }}">
          @csrf
          @method('PATCH')
          <button type="submit" data-confirm="Finalizar esta prescrição? Depois disso o conteúdo não poderá ser alterado.">Finalizar</button>
        </form>
      @endif
      <a class="button secondary" href="{{ route('prescriptions.index') }}">Voltar</a>
    </div>
  </header>

  <article class="panel prescription-document">
    <div class="prescription-document-header">
      <div>
        <strong class="prescription-brand">VetFlow</strong>
        <span>Prescrição veterinária</span>
      </div>
      <span class="badge {{ $statusClass }}">{{ \App\Modules\Prescriptions\Models\Prescription::STATUS_LABELS[$prescription->status] ?? $prescription->status }}</span>
    </div>

    @if($prescription->status === 'cancelled')
      <div class="prescription-cancelled-notice">
        <strong>DOCUMENTO CANCELADO</strong>
        <span>{{ $prescription->cancellation_reason }}</span>
      </div>
    @endif

    <section class="prescription-recipient">
      <div><span>Paciente</span><strong>{{ $prescription->patient?->name ?? '-' }}</strong></div>
      <div><span>Responsável</span><strong>{{ $prescription->patient?->tutor?->name ?? '-' }}</strong></div>
      <div><span>Data</span><strong>{{ optional($prescription->prescribed_at)->format('d/m/Y H:i') }}</strong></div>
      <div><span>Registrado por</span><strong>{{ $prescription->createdBy?->name ?? '-' }}</strong></div>
    </section>

    <ol class="prescription-document-items">
      @foreach($prescription->items as $item)
        <li>
          <div class="prescription-item-title">
            <strong>{{ $item->medication_name }}</strong>
            @if($item->concentration)<span>{{ $item->concentration }}</span>@endif
          </div>
          <div class="prescription-item-directions">
            <span><strong>Dose:</strong> {{ $item->dosage }}</span>
            @if($item->route)<span><strong>Via:</strong> {{ $item->route }}</span>@endif
            <span><strong>Frequência:</strong> {{ $item->frequency }}</span>
            @if($item->duration)<span><strong>Duração:</strong> {{ $item->duration }}</span>@endif
            @if($item->quantity)<span><strong>Quantidade:</strong> {{ $item->quantity }}</span>@endif
          </div>
          @if($item->instructions)<p>{{ $item->instructions }}</p>@endif
        </li>
      @endforeach
    </ol>

    @if($prescription->general_instructions)
      <section class="prescription-general-instructions">
        <h2>Orientações gerais</h2>
        <p>{{ $prescription->general_instructions }}</p>
      </section>
    @endif

    <footer class="prescription-document-footer">
      @if($prescription->finalized_at)
        <span>Finalizada em {{ $prescription->finalized_at->format('d/m/Y H:i') }} por {{ $prescription->finalizedBy?->name ?? '-' }}.</span>
      @else
        <span>Rascunho sem validade de documento final.</span>
      @endif
      <span>O VetFlow registra o histórico; esta versão não aplica assinatura digital nem validação regulatória.</span>
    </footer>
  </article>

  @if($prescription->isFinalized())
    <section class="panel prescription-cancel-panel prescription-print-actions">
      <div class="panel-body">
        <h2>Cancelar prescrição</h2>
        <p class="muted">O documento permanecerá no histórico com o motivo e o usuário responsável.</p>
        <form method="POST" action="{{ route('prescriptions.cancel', $prescription->id) }}">
          @csrf
          @method('PATCH')
          <div class="field">
            <label for="cancellation_reason">Motivo do cancelamento</label>
            <textarea id="cancellation_reason" name="cancellation_reason" required maxlength="2000"></textarea>
          </div>
          <button class="danger" type="submit" data-confirm="Cancelar esta prescrição finalizada?">Confirmar cancelamento</button>
        </form>
      </div>
    </section>
  @endif
@endsection
