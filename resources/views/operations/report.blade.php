@extends('layouts.admin')

@section('title', 'Relatório de prontidão operacional - VetFlow')

@section('content')
  <section class="page-heading">
    <div>
      <span class="eyebrow">Relatório operacional</span>
      <h1>Prontidão da release</h1>
      <p>Gerado em {{ \Carbon\CarbonImmutable::parse($report['generated_at'])->format('d/m/Y H:i') }}.</p>
    </div>
    <div class="row-actions">
      <a class="button secondary" href="{{ route('operations.index') }}">Voltar</a>
      <a class="button secondary" href="{{ route('operations.report.json') }}">Baixar JSON</a>
      <button type="button" onclick="window.print()">Imprimir</button>
    </div>
  </section>

  <section class="panel">
    <div class="panel-heading">
      <div>
        <span class="eyebrow">{{ $report['environment'] }}</span>
        <h2>{{ $report['status']['label'] }}</h2>
        <p>{{ $report['status']['description'] }}</p>
      </div>
      <span class="badge {{ $report['status']['key'] === 'approved' ? 'success' : 'warning' }}">
        Commit {{ $report['release']['short_sha'] ?? 'indisponível' }}
      </span>
    </div>
    <div class="panel-body implementation-readiness-gates">
      @foreach($report['gates'] as $gate)
        <div class="implementation-readiness-gate {{ $gate['passed'] ? 'passed' : 'pending' }}">
          <span aria-hidden="true">{{ $gate['passed'] ? '✓' : '○' }}</span>
          <div><strong>{{ $gate['label'] }}</strong><small>{{ $gate['summary'] }}</small></div>
        </div>
      @endforeach
    </div>
  </section>

  <section class="panel">
    <div class="panel-heading"><div><h2>Verificações técnicas</h2></div></div>
    <div class="panel-body table-wrap">
      <table>
        <thead><tr><th>Verificação</th><th>Status</th><th>Detalhe</th></tr></thead>
        <tbody>
          @foreach($report['technical_checks'] as $check)
            <tr>
              <td>{{ $check['check'] }}</td>
              <td>{{ $check['status'] }}</td>
              <td>{{ $check['detail'] }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>

  <section class="panel">
    <div class="panel-heading">
      <div>
        <h2>Smoke test</h2>
        <p>{{ $report['smoke_checklist']['completed'] }} de {{ $report['smoke_checklist']['total'] }} itens concluídos.</p>
      </div>
    </div>
    <div class="panel-body table-wrap">
      <table>
        <thead><tr><th>Item</th><th>Status</th><th>Responsável</th><th>Decisão</th></tr></thead>
        <tbody>
          @foreach($report['smoke_checklist']['items'] as $item)
            <tr>
              <td>{{ $item['label'] }}</td>
              <td>{{ $item['completed'] ? 'Concluído' : 'Pendente' }}</td>
              <td>{{ $item['actor'] ?? '-' }}</td>
              <td>{{ $item['decided_at'] ? \Carbon\CarbonImmutable::parse($item['decided_at'])->format('d/m/Y H:i') : '-' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>

  @if($report['decision'])
    <section class="panel">
      <div class="panel-heading"><div><h2>Decisão humana</h2></div></div>
      <div class="panel-body definition-grid">
        <div><dt>Decisão</dt><dd>{{ $report['decision']['decision'] === 'approved' ? 'Aprovada' : 'Em espera' }}</dd></div>
        <div><dt>Vigência</dt><dd>{{ $report['decision']['current'] ? 'Atual' : 'Superada' }}</dd></div>
        <div><dt>Responsável</dt><dd>{{ $report['decision']['actor'] ?? '-' }}</dd></div>
        <div><dt>Observação</dt><dd>{{ $report['decision']['note'] ?? '-' }}</dd></div>
      </div>
    </section>
  @endif
@endsection
