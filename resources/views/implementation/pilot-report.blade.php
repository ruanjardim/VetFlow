@extends('layouts.admin')

@section('title', 'Relatório do Piloto - VetFlow')

@section('content')
  @php
    $readiness = $report['readiness'];
    $release = $report['release'];
  @endphp

  <header class="topbar pilot-report-actions">
    <div>
      <h1>Relatório do piloto</h1>
      <p>{{ $report['clinic']['name'] }}</p>
    </div>

    <div class="row-actions">
      <button class="button" type="button" onclick="window.print()">Imprimir</button>
      <a
        class="button secondary"
        href="{{ route('implementation.pilots.report-json', $report['clinic']['id']) }}"
      >
        Baixar JSON
      </a>
      <a class="button secondary" href="{{ route('implementation.index') }}">Voltar</a>
    </div>
  </header>

  <article class="pilot-report">
    <section class="panel">
      <div class="panel-body">
        <div class="implementation-readiness-header">
          <div>
            <span class="eyebrow">VetFlow · Preparação do piloto</span>
            <h2>{{ $report['clinic']['name'] }}</h2>
            <p class="muted">{{ $report['clinic']['corporate_name'] }}</p>
          </div>
          <span class="implementation-readiness-status">{{ $readiness['status']['label'] }}</span>
        </div>

        <p>{{ $readiness['status']['description'] }}</p>
        <p class="muted">
          Gerado em {{ \Illuminate\Support\Carbon::parse($report['generated_at'])->format('d/m/Y H:i') }} ·
          Evidência <code>{{ substr($readiness['evidence_hash'], 0, 16) }}…</code>
        </p>

        <div class="implementation-readiness-gates">
          @foreach($readiness['gates'] as $gate)
            <div class="implementation-readiness-gate {{ $gate['passed'] ? 'passed' : 'pending' }}">
              <span aria-hidden="true">{{ $gate['passed'] ? '✓' : '○' }}</span>
              <div><strong>{{ $gate['label'] }}</strong><small>{{ $gate['summary'] }}</small></div>
            </div>
          @endforeach
        </div>
      </div>
    </section>

    <section class="panel">
      <div class="panel-body">
        <h2>Cobertura e qualidade</h2>
        <div class="table-wrap implementation-table">
          <table>
            <thead><tr><th>Bloco</th><th>Importação</th><th>Qualidade</th><th>Critério</th></tr></thead>
            <tbody>
              @foreach($report['coverage']['blocks'] as $coverageBlock)
                @php($qualityBlock = collect($report['quality']['blocks'])->firstWhere('type', $coverageBlock['type']))
                <tr>
                  <td>{{ $coverageBlock['label'] }}</td>
                  <td>
                    {{ $coverageBlock['completed']
                      ? $coverageBlock['imported_count'].' registros via '.mb_strtoupper($coverageBlock['source'])
                      : 'Pendente' }}
                  </td>
                  <td>
                    @if(!$qualityBlock['evaluated'])
                      Aguardando
                    @elseif($qualityBlock['issue_count'] === 0)
                      Sem pendências
                    @else
                      {{ $qualityBlock['issue_count'] }} pendências
                    @endif
                  </td>
                  <td>{{ $qualityBlock['description'] }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <section class="panel">
      <div class="panel-body">
        <h2>Checklist atual</h2>
        <div class="implementation-readiness-blocks">
          @foreach($report['checklist']['checks'] as $check)
            <div class="implementation-readiness-block {{ $check['completed'] ? 'completed' : 'pending' }}">
              <span aria-hidden="true">{{ $check['completed'] ? '✓' : '○' }}</span>
              <div>
                <strong>{{ $check['label'] }}</strong>
                <small>
                  {{ $check['has_decision']
                    ? ($check['completed'] ? 'Concluído' : 'Reaberto').' por '.$check['user_name']
                    : 'Sem decisão' }}
                </small>
              </div>
            </div>
          @endforeach
        </div>
      </div>
    </section>

    <section class="panel">
      <div class="panel-body">
        <h2>Plano e decisão</h2>
        @if($release['has_release'])
          <p><strong>Revisão:</strong> {{ $release['revision'] }}</p>
          <p><strong>Operação:</strong> {{ $release['release_owner'] }}</p>
          <p><strong>Suporte:</strong> {{ $release['support_owner'] }}</p>
          <p><strong>Início previsto:</strong> {{ $release['planned_start_date']?->format('d/m/Y') ?: 'Não definido' }}</p>
          <p><strong>Escopo:</strong> {{ $release['scope'] }}</p>
          <p><strong>Notas:</strong> {{ $release['release_notes'] }}</p>
        @else
          <p class="muted">Nenhum plano de liberação registrado.</p>
        @endif

        <hr>

        @if($readiness['decision'])
          <p>
            <strong>Última decisão:</strong>
            {{ $readiness['decision'] === 'approved' ? 'Aprovado' : 'Em espera' }}
            por {{ $readiness['decision_user_name'] }}
            em {{ $readiness['decided_at']?->format('d/m/Y H:i') }}.
          </p>
          <p>{{ $readiness['decision_notes'] ?: 'Sem observação.' }}</p>
          @if(!$readiness['decision_current'])
            <div class="alert warning">A decisão não corresponde mais às evidências atuais.</div>
          @endif
        @else
          <p class="muted">Nenhuma decisão humana registrada.</p>
        @endif
      </div>
    </section>
  </article>
@endsection
