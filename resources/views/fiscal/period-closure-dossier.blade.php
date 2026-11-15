@extends('layouts.admin')

@section('title', 'Dossiê do fechamento fiscal - VetFlow')

@section('content')
  @php
    $money = fn ($cents) => 'R$ '.number_format(((int) $cents) / 100, 2, ',', '.');
    $evidence = $report['evidence'];
    $backQuery = array_filter([
      'clinic_id' => $requiresClinic ? $report['clinic']['id'] : null,
      'from' => $report['period']['from'],
      'to' => $report['period']['to'],
    ]);
  @endphp

  <header class="topbar pilot-report-actions">
    <div>
      <h1>Dossiê do fechamento fiscal</h1>
      <p>{{ $report['clinic']['name'] }} · {{ \Illuminate\Support\Carbon::parse($report['period']['from'])->format('d/m/Y') }} a {{ \Illuminate\Support\Carbon::parse($report['period']['to'])->format('d/m/Y') }}</p>
    </div>
    <div class="row-actions">
      <button class="button" type="button" data-print-page>Imprimir</button>
      <a class="button secondary" href="{{ route('fiscal.reconciliation.closures.dossier.json', $closure->ulid) }}">Baixar JSON</a>
      <a class="button secondary" href="{{ route('fiscal.reconciliation.index', $backQuery) }}">Voltar à conciliação</a>
    </div>
  </header>

  <article class="pilot-report">
    <div class="alert warning">
      <strong>Controle operacional interno:</strong> este dossiê reproduz a evidência congelada de um fechamento do VetFlow. Não é escrituração, declaração fiscal, apuração de tributos, parecer contábil ou comprovante de transmissão a qualquer autoridade.
    </div>

    @if($report['status']['current'])
      <div class="alert success"><strong>Fechamento vigente.</strong> A evidência registrada ainda corresponde à conciliação atual deste período.</div>
    @else
      <div class="alert warning">
        <strong>Fechamento superado.</strong>
        O período mudou depois deste registro. A fotografia histórica abaixo permanece intacta, mas a conciliação atual {{ $report['status']['current_reconciliation_complete'] ? 'precisa de um novo fechamento' : 'possui pendências que devem ser revisadas' }}.
      </div>
    @endif

    <section class="grid stats">
      <div class="stat"><span>Vendas congeladas</span><strong>{{ data_get($evidence, 'totals.sales_count', 0) }}</strong></div>
      <div class="stat"><span>Total congelado</span><strong>{{ $money(data_get($evidence, 'totals.total_cents', 0)) }}</strong></div>
      <div class="stat"><span>Autorizadas</span><strong>{{ data_get($evidence, 'totals.statuses.authorized.count', 0) }}</strong></div>
      <div class="stat"><span>Devoluções revisadas</span><strong>{{ data_get($evidence, 'totals.statuses.return_reviewed.count', 0) }}</strong></div>
    </section>

    <section class="panel">
      <div class="panel-heading"><div><h2>Confirmação humana</h2><p>Registro imutável vinculado à evidência do período.</p></div></div>
      <div class="panel-body">
        <div class="definition-grid">
          <div><dt>Responsável informado</dt><dd>{{ $report['closure']['reviewer_name'] }}</dd></div>
          <div><dt>Registrado por</dt><dd>{{ $report['closure']['recorded_by'] ?: 'Usuário removido' }}</dd></div>
          <div><dt>Data</dt><dd>{{ \Illuminate\Support\Carbon::parse($report['closure']['occurred_at'])->format('d/m/Y H:i') }}</dd></div>
          <div><dt>Vigência</dt><dd>{{ $report['status']['current'] ? 'Atual' : 'Superada' }}</dd></div>
          <div><dt>Versão da evidência</dt><dd>{{ $report['closure']['schema_version'] }}</dd></div>
          <div><dt>Ambiente registrado</dt><dd>{{ data_get($evidence, 'profile.environment', 'Não informado') }}</dd></div>
        </div>
        <p><strong>Observação interna</strong></p>
        <p>{{ $report['closure']['notes'] ?: 'Nenhuma observação registrada.' }}</p>
      </div>
    </section>

    <section class="panel">
      <div class="panel-heading"><div><h2>Resumo por situação</h2><p>Contagens e valores preservados no fechamento.</p></div></div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Situação</th><th>Vendas</th><th>Total</th></tr></thead>
          <tbody>
            @forelse(collect(data_get($evidence, 'totals.statuses', []))->filter(fn ($summary) => $summary['count'] > 0) as $status => $summary)
              <tr>
                <td>{{ $report['status_labels'][$status] ?? $status }}</td>
                <td>{{ $summary['count'] }}</td>
                <td>{{ $money($summary['total_cents']) }}</td>
              </tr>
            @empty
              <tr><td colspan="3" class="muted">O fechamento registrou um período sem movimento interno.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </section>

    <section class="panel">
      <div class="panel-heading"><div><h2>Vendas do fechamento</h2><p>Lista congelada; alterações posteriores não reescrevem estas linhas.</p></div></div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Venda</th><th>Conclusão</th><th>Tipos esperados</th><th>Situação</th><th>Total</th><th>Documentos</th></tr></thead>
          <tbody>
            @forelse(data_get($evidence, 'sales', []) as $sale)
              <tr>
                <td>{{ $sale['sale_code'] }}</td>
                <td>{{ $sale['completed_at'] ? \Illuminate\Support\Carbon::parse($sale['completed_at'])->format('d/m/Y H:i') : 'Não informada' }}</td>
                <td>{{ implode(' + ', array_map('strtoupper', $sale['expected_types'])) ?: 'Indefinido' }}</td>
                <td>{{ $report['status_labels'][$sale['status']] ?? $sale['status'] }}</td>
                <td>{{ $money($sale['sale_total_cents']) }}</td>
                <td>
                  @forelse($sale['documents'] as $document)
                    <span>{{ strtoupper($document['type']) }} · {{ $document['status'] }} · {{ $money($document['total_cents']) }}</span>@if(!$loop->last)<br>@endif
                  @empty
                    Nenhum documento congelado
                  @endforelse
                </td>
              </tr>
            @empty
              <tr><td colspan="6" class="muted">O fechamento registrou um período sem movimento interno.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </section>

    <section class="panel panel-body">
      <h2>Integridade do dossiê</h2>
      <p class="muted">Fechamento <code>{{ $report['closure']['id'] }}</code></p>
      <p class="muted">Evidência <code>{{ $report['fingerprints']['recorded_evidence'] }}</code></p>
      <p class="muted">Relatório <code>{{ $report['report_hash'] }}</code></p>
      <p class="muted">Gerado em {{ \Illuminate\Support\Carbon::parse($report['generated_at'])->format('d/m/Y H:i:s') }}</p>
    </section>
  </article>
@endsection
