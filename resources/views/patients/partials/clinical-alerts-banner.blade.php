@php($clinicalAlerts = $patient?->activeClinicalAlerts ?? collect())

@if($clinicalAlerts->isNotEmpty())
  <section class="panel clinical-alerts-banner prescription-print-actions" role="alert" aria-label="Alertas clínicos ativos">
    <div class="panel-heading">
      <div>
        <h2>Alertas clínicos ativos</h2>
        <p>Informações registradas pela equipe. O sistema não atribui gravidade nem interpreta o conteúdo.</p>
      </div>
      <span class="badge danger">{{ $clinicalAlerts->count() }} {{ $clinicalAlerts->count() === 1 ? 'ativo' : 'ativos' }}</span>
    </div>
    <div class="clinical-alerts-list">
      @foreach($clinicalAlerts as $clinicalAlert)
        <article class="clinical-alert-card">
          <strong>{{ $clinicalAlert->title }}</strong>
          @if($clinicalAlert->details)<p>{!! nl2br(e($clinicalAlert->details)) !!}</p>@endif
          <small>Registrado em {{ optional($clinicalAlert->created_at)->format('d/m/Y H:i') }} por {{ $clinicalAlert->createdBy?->name ?? '-' }}.</small>
        </article>
      @endforeach
    </div>
  </section>
@endif
