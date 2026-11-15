@extends('layouts.admin')

@section('title', 'Dossiê de homologação fiscal - VetFlow')

@section('content')
  @php
    $decisionLabels = [
      'approved_for_production_review' => 'Aprovada para análise técnica',
      'follow_up_required' => 'Acompanhamento necessário',
    ];
  @endphp

  <header class="topbar pilot-report-actions">
    <div>
      <h1>Dossiê de homologação fiscal</h1>
      <p>{{ $report['clinic']['name'] }} · gerado em {{ \Illuminate\Support\Carbon::parse($report['generated_at'])->format('d/m/Y H:i') }}</p>
    </div>
    <div class="row-actions">
      <button class="button" type="button" data-print-page>Imprimir</button>
      <a class="button secondary" href="{{ route('fiscal.homologation-report.json', $requiresClinic ? ['clinic_id' => $selectedClinicId] : []) }}">Baixar JSON</a>
      <a class="button secondary" href="{{ route('fiscal.index', $requiresClinic ? ['clinic_id' => $selectedClinicId] : []) }}">Voltar</a>
    </div>
  </header>

  <article class="pilot-report">
    <section class="panel">
      <div class="panel-heading">
        <div>
          <span class="eyebrow">{{ $report['scope']['environment'] }} · versão {{ $report['scope']['profile_version'] }}</span>
          <h2>{{ $report['status']['ready_count'] }}/{{ $report['status']['total_count'] }} evidências comprovadas</h2>
          <p>{{ $report['clinic']['corporate_name'] }} · CNPJ {{ $report['clinic']['cnpj'] ?: 'não informado' }}</p>
        </div>
        <span class="badge {{ $report['status']['eligible_for_production_review'] ? 'success' : 'warning' }}">
          {{ $report['status']['eligible_for_production_review'] ? 'Apta para análise técnica' : 'Homologação pendente' }}
        </span>
      </div>
      <div class="panel-body">
        <div class="alert warning">
          <strong>Produção bloqueada:</strong> este relatório comprova somente a preparação de homologação. Não é autorização fiscal nem habilitação do ambiente produtivo.
        </div>
        <p class="muted">Evidência do relatório <code>{{ substr($report['report_hash'], 0, 16) }}…</code></p>
      </div>
    </section>

    <section class="panel">
      <div class="panel-heading"><div><h2>Checklist e plano de ação</h2></div></div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Área</th><th>Evidência</th><th>Status</th><th>Próximo passo</th></tr></thead>
          <tbody>
            @foreach($report['checks'] as $check)
              <tr>
                <td>{{ $check['category'] }}</td>
                <td>
                  {{ $check['label'] }}
                  @if($check['detail'])<div class="muted">{{ $check['detail'] }}</div>@endif
                </td>
                <td><span class="badge {{ $check['ready'] ? 'success' : 'warning' }}">{{ $check['ready'] ? 'Comprovada' : 'Pendente' }}</span></td>
                <td>{{ $check['next_action'] ?? 'Nenhuma ação necessária.' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </section>

    <section class="panel">
      <div class="panel-heading"><div><h2>Última revisão humana</h2></div></div>
      <div class="panel-body">
        @if($report['latest_review'])
          <div class="definition-grid">
            <div><dt>Decisão</dt><dd>{{ $decisionLabels[$report['latest_review']['decision']] ?? $report['latest_review']['decision'] }}</dd></div>
            <div><dt>Vigência</dt><dd>{{ $report['latest_review']['current'] ? 'Atual' : 'Superada por novas evidências' }}</dd></div>
            <div><dt>Responsável informado</dt><dd>{{ $report['latest_review']['reviewer_name'] }}</dd></div>
            <div><dt>Registrado por</dt><dd>{{ $report['latest_review']['recorded_by'] ?? 'Usuário removido' }}</dd></div>
            <div><dt>Data</dt><dd>{{ \Illuminate\Support\Carbon::parse($report['latest_review']['occurred_at'])->format('d/m/Y H:i') }}</dd></div>
            <div><dt>Parecer</dt><dd>{{ $report['latest_review']['notes'] ?: 'Sem observação.' }}</dd></div>
          </div>
        @else
          <p class="muted">Nenhuma revisão humana foi registrada para esta clínica.</p>
        @endif
      </div>
    </section>

    @if(count($report['review_history']) > 0)
      <section class="panel">
        <div class="panel-heading"><div><h2>Histórico recente</h2></div></div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Decisão</th><th>Responsável</th><th>Vigência</th><th>Data</th></tr></thead>
            <tbody>
              @foreach($report['review_history'] as $review)
                <tr>
                  <td>{{ $decisionLabels[$review['decision']] ?? $review['decision'] }}</td>
                  <td>{{ $review['reviewer_name'] }} · {{ $review['recorded_by'] ?? 'Usuário removido' }}</td>
                  <td>{{ $review['current'] ? 'Atual' : 'Superada' }}</td>
                  <td>{{ \Illuminate\Support\Carbon::parse($review['occurred_at'])->format('d/m/Y H:i') }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </section>
    @endif
  </article>
@endsection
