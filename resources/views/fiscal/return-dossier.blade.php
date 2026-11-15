@extends('layouts.admin')

@section('title', 'Dossiê fiscal da devolução - VetFlow')

@section('content')
  @php
    $money = fn ($cents) => 'R$ '.number_format(((int) $cents) / 100, 2, ',', '.');
    $quantity = fn ($value) => number_format((float) $value, 3, ',', '.');
    $evidence = $report['evidence'];
    $eventLabels = [
      'item_returned' => 'Item devolvido',
      'refund' => 'Reembolso',
      'partial_return' => 'Devolução parcial',
      'returned' => 'Devolução integral',
    ];
  @endphp

  <header class="topbar pilot-report-actions">
    <div>
      <h1>Dossiê fiscal da devolução</h1>
      <p>{{ $report['sale']['code'] }} · {{ $report['clinic']['name'] }} · gerado em {{ \Illuminate\Support\Carbon::parse($report['generated_at'])->format('d/m/Y H:i') }}</p>
    </div>
    <div class="row-actions">
      <button class="button" type="button" data-print-page>Imprimir</button>
      <a class="button secondary" href="{{ route('fiscal.returns.dossier.json', [$sale->id, $review->ulid]) }}">Baixar JSON</a>
      <a class="button secondary" href="{{ route('fiscal.returns.show', $sale->id) }}">Voltar</a>
    </div>
  </header>

  <article class="pilot-report">
    <div class="alert warning">
      <strong>Registro operacional:</strong> este dossiê reproduz uma revisão humana e sua evidência congelada. Ele não é documento fiscal, escrituração, parecer contábil ou autorização para o VetFlow emitir, transmitir, ajustar ou cancelar documentos.
    </div>

    @if($report['status']['operationally_resolved'])
      <div class="alert success"><strong>Revisão concluída e vigente.</strong> A evidência registrada ainda corresponde ao estado atual da venda.</div>
    @elseif(!$report['status']['current'])
      <div class="alert warning"><strong>Evidência superada.</strong> A venda ou seus documentos mudaram depois desta revisão. Consulte o fluxo atual antes de adotar qualquer providência.</div>
    @else
      <div class="alert warning"><strong>Acompanhamento pendente.</strong> A evidência está vigente, mas esta decisão não concluiu a revisão operacional.</div>
    @endif

    <section class="grid stats">
      <div class="stat"><span>Total original</span><strong>{{ $money(data_get($evidence, 'sale.total_cents', 0)) }}</strong></div>
      <div class="stat"><span>Valor devolvido</span><strong>{{ $money(data_get($evidence, 'sale.return_total_cents', 0)) }}</strong></div>
      <div class="stat"><span>Valor reembolsado</span><strong>{{ $money(data_get($evidence, 'sale.refunded_total_cents', 0)) }}</strong></div>
      <div class="stat"><span>Vigência</span><strong>{{ $report['status']['current'] ? 'Atual' : 'Superada' }}</strong></div>
    </section>

    <section class="panel">
      <div class="panel-heading"><div><h2>Decisão humana registrada</h2><p>Revisão imutável vinculada à evidência abaixo.</p></div></div>
      <div class="panel-body">
        <div class="definition-grid">
          <div><dt>Decisão</dt><dd>{{ $report['review']['decision_label'] }}</dd></div>
          <div><dt>Responsável informado</dt><dd>{{ $report['review']['reviewer_name'] }}</dd></div>
          <div><dt>Registrado por</dt><dd>{{ $report['review']['recorded_by'] ?: 'Usuário removido' }}</dd></div>
          <div><dt>Data</dt><dd>{{ \Illuminate\Support\Carbon::parse($report['review']['occurred_at'])->format('d/m/Y H:i') }}</dd></div>
          <div><dt>Fonte da orientação</dt><dd>{{ $report['review']['guidance_source'] }}</dd></div>
          <div><dt>Versão da evidência</dt><dd>{{ $report['review']['schema_version'] }}</dd></div>
        </div>
        <p><strong>Orientação e próximo passo registrados</strong></p>
        <p>{{ $report['review']['resolution_summary'] }}</p>
      </div>
    </section>

    <section class="panel">
      <div class="panel-heading"><div><h2>Itens devolvidos</h2><p>Valores preservados no momento da revisão.</p></div></div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Item</th><th>Tipo</th><th>Vendido</th><th>Devolvido</th><th>Reembolsado</th></tr></thead>
          <tbody>
            @forelse(data_get($evidence, 'returned_items', []) as $item)
              <tr>
                <td>{{ $item['description'] }}</td>
                <td>{{ $item['type'] === 'product' ? 'Produto' : ($item['type'] === 'service' ? 'Serviço' : 'Avulso') }}</td>
                <td>{{ $quantity($item['sold_quantity']) }}</td>
                <td>{{ $quantity($item['returned_quantity']) }}</td>
                <td>{{ $money($item['refunded_total_cents']) }}</td>
              </tr>
            @empty
              <tr><td colspan="5" class="muted">Nenhum item individual consta na evidência congelada.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </section>

    <section class="panel">
      <div class="panel-heading"><div><h2>Eventos da devolução</h2></div></div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Evento</th><th>Quantidade</th><th>Valor</th><th>Motivo</th><th>Data</th></tr></thead>
          <tbody>
            @forelse(data_get($evidence, 'return_events', []) as $event)
              <tr>
                <td>{{ $eventLabels[$event['type']] ?? $event['type'] }}</td>
                <td>{{ $event['quantity'] !== null ? $quantity($event['quantity']) : '—' }}</td>
                <td>{{ $event['amount_cents'] !== null ? $money($event['amount_cents']) : '—' }}</td>
                <td>{{ $event['reason'] ?: 'Não informado' }}</td>
                <td>{{ $event['occurred_at'] ? \Illuminate\Support\Carbon::parse($event['occurred_at'])->format('d/m/Y H:i') : 'Não informada' }}</td>
              </tr>
            @empty
              <tr><td colspan="5" class="muted">Nenhum evento consta na evidência congelada.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </section>

    <section class="panel">
      <div class="panel-heading"><div><h2>Documentos fiscais vinculados</h2><p>Somente estados e totais registrados; payloads e credenciais são omitidos.</p></div></div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Tipo</th><th>Status</th><th>Total</th><th>Autorização</th><th>Cancelamento</th></tr></thead>
          <tbody>
            @forelse(data_get($evidence, 'documents', []) as $document)
              <tr>
                <td>{{ strtoupper($document['type']) }}</td>
                <td>{{ $document['status'] }}</td>
                <td>{{ $money($document['total_cents']) }}</td>
                <td>{{ $document['authorized_at'] ? \Illuminate\Support\Carbon::parse($document['authorized_at'])->format('d/m/Y H:i') : '—' }}</td>
                <td>{{ $document['cancelled_at'] ? \Illuminate\Support\Carbon::parse($document['cancelled_at'])->format('d/m/Y H:i') : '—' }}</td>
              </tr>
            @empty
              <tr><td colspan="5" class="muted">Nenhum documento fiscal consta na evidência congelada.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </section>

    <section class="panel panel-body">
      <h2>Integridade do dossiê</h2>
      <p class="muted">Revisão <code>{{ $report['review']['id'] }}</code></p>
      <p class="muted">Evidência <code>{{ $report['fingerprints']['recorded_evidence'] }}</code></p>
      <p class="muted">Relatório <code>{{ $report['report_hash'] }}</code></p>
    </section>
  </article>
@endsection
