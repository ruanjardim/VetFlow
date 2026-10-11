@extends('layouts.admin')

@section('title', 'Revisão fiscal da devolução - VetFlow')

@section('content')
  @php
    $money = fn ($value) => 'R$ '.number_format((float) $value, 2, ',', '.');
    $quantity = fn ($value) => number_format((float) $value, 3, ',', '.');
    $latest = $review['latest_review'];
  @endphp

  <header class="topbar">
    <div>
      <h1>Revisão fiscal da devolução</h1>
      <p>{{ $sale->code }} · {{ $sale->clinic?->trade_name ?: $sale->clinic?->corporate_name }}</p>
    </div>
    <div class="row-actions">
      @if($latest)
        <a class="button" href="{{ route('fiscal.returns.dossier', [$sale->id, $latest->ulid]) }}">Abrir último dossiê</a>
      @endif
      <a class="button secondary" href="{{ route('sales.receipt', $sale->id) }}">Ver comprovante</a>
      <a class="button secondary" href="{{ route('fiscal.reconciliation.index', ['from' => $sale->completed_at?->toDateString(), 'to' => $sale->completed_at?->toDateString()]) }}">Voltar à conciliação</a>
    </div>
  </header>

  <div class="alert info">
    <strong>Fluxo assistido:</strong> esta página documenta a conferência humana e a orientação recebida. O VetFlow não escolhe, gera, transmite ou cancela automaticamente qualquer documento fiscal de devolução.
  </div>

  @if($review['resolved'])
    <div class="alert success">
      <strong>Revisão concluída e vigente.</strong>
      A conciliação reconhecerá esta devolução como revisada enquanto a venda e os documentos permanecerem iguais.
    </div>
  @elseif($latest && !$review['latest_review_current'])
    <div class="alert warning">
      <strong>Revisão desatualizada.</strong>
      Houve mudança nos itens devolvidos, valores, eventos ou documentos fiscais. Registre uma nova decisão após conferir a evidência atual.
    </div>
  @elseif($latest)
    <div class="alert warning">
      <strong>Acompanhamento pendente.</strong>
      A última decisão ainda não concluiu a revisão fiscal desta devolução.
    </div>
  @endif

  <section class="grid stats">
    <div class="stat"><span>Total original</span><strong>{{ $money($sale->total) }}</strong></div>
    <div class="stat"><span>Valor devolvido</span><strong>{{ $money($sale->return_total) }}</strong></div>
    <div class="stat"><span>Valor reembolsado</span><strong>{{ $money($sale->refunded_total) }}</strong></div>
    <div class="stat"><span>Itens afetados</span><strong>{{ count(data_get($review, 'evidence_snapshot.returned_items', [])) }}</strong></div>
  </section>

  <section class="panel">
    <div class="panel-heading"><div><h2>Itens devolvidos</h2><p>Evidência operacional atual da venda.</p></div></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Item</th><th>Tipo</th><th>Vendido</th><th>Devolvido</th><th>Reembolsado</th></tr></thead>
        <tbody>
          @forelse($sale->items->filter(fn ($item) => (float) $item->returned_quantity > 0 || (float) $item->refunded_total > 0) as $item)
            <tr>
              <td>{{ $item->description }}</td>
              <td>{{ $item->type === 'product' ? 'Produto' : ($item->type === 'service' ? 'Serviço' : 'Avulso') }}</td>
              <td>{{ $quantity($item->quantity) }}</td>
              <td>{{ $quantity($item->returned_quantity) }}</td>
              <td>{{ $money($item->refunded_total) }}</td>
            </tr>
          @empty
            <tr><td colspan="5" class="muted">Nenhum item individual foi marcado; confira os eventos e totais da venda.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>

  <section class="panel">
    <div class="panel-heading"><div><h2>Documentos vinculados</h2><p>Estados atuais, sem interpretação tributária automática.</p></div></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Tipo</th><th>Status</th><th>Total congelado</th><th>Ação</th></tr></thead>
        <tbody>
          @forelse($sale->fiscalDocuments->sortBy('id') as $document)
            <tr>
              <td>{{ strtoupper($document->type) }}</td>
              <td><span class="badge">{{ $document->status }}</span></td>
              <td>{{ $money(data_get($document->totals_snapshot, 'total', 0)) }}</td>
              <td><a class="button secondary" href="{{ route('fiscal.documents.show', $document->id) }}">Abrir</a></td>
            </tr>
          @empty
            <tr><td colspan="4" class="muted">Nenhum documento fiscal foi preparado no VetFlow para esta venda.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>

  @if(!$review['resolved'])
    <section class="panel panel-body">
      <h2>Registrar decisão humana</h2>
      <p class="muted">Descreva a orientação recebida; o texto não executará nenhuma ação fiscal.</p>
      <form method="POST" action="{{ route('fiscal.returns.reviews.store', $sale->id) }}" class="stacked-form">
        @csrf
        <div class="form-grid two-columns">
          <div class="field">
            <label for="return_decision">Decisão</label>
            <select id="return_decision" name="decision" required>
              @foreach($decisionLabels as $key => $label)
                <option value="{{ $key }}" @selected(old('decision') === $key)>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="field">
            <label for="return_reviewer">Responsável pela revisão</label>
            <input id="return_reviewer" name="reviewer_name" value="{{ old('reviewer_name', auth()->user()->name) }}" maxlength="120" required>
          </div>
          <div class="field">
            <label for="return_guidance_source">Fonte da orientação</label>
            <input id="return_guidance_source" name="guidance_source" value="{{ old('guidance_source') }}" maxlength="160" placeholder="Contador, escritório ou referência interna" required>
          </div>
          <div class="field">
            <label for="return_password">Senha atual do VetFlow</label>
            <input id="return_password" name="current_password" type="password" autocomplete="current-password" required>
          </div>
        </div>
        <div class="field">
          <label for="return_resolution">Orientação e próximo passo</label>
          <textarea id="return_resolution" name="resolution_summary" rows="5" minlength="20" maxlength="2000" required>{{ old('resolution_summary') }}</textarea>
        </div>
        <label class="checkbox-line">
          <input type="checkbox" name="confirm_review" value="1" required>
          Conferi a devolução, os valores e os documentos vinculados antes de registrar esta decisão.
        </label>
        <div class="row-actions"><button type="submit">Registrar revisão</button></div>
      </form>
    </section>
  @endif

  @if($review['history']->isNotEmpty())
    <section class="panel">
      <div class="panel-heading"><div><h2>Histórico de revisões</h2><p>Registros imutáveis em ordem reversa.</p></div></div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Decisão</th><th>Responsável</th><th>Fonte</th><th>Orientação registrada</th><th>Data</th><th>Evidência</th><th>Ação</th></tr></thead>
          <tbody>
            @foreach($review['history'] as $entry)
              <tr>
                <td>{{ $decisionLabels[$entry->decision] ?? $entry->decision }}</td>
                <td>{{ $entry->reviewer_name }}<div class="muted">{{ $entry->reviewer?->name ?: 'Usuário removido' }}</div></td>
                <td>{{ $entry->guidance_source }}</td>
                <td>{{ $entry->resolution_summary }}</td>
                <td>{{ $entry->occurred_at->format('d/m/Y H:i') }}</td>
                <td>{{ hash_equals($review['evidence_fingerprint'], $entry->evidence_fingerprint) ? 'Atual' : 'Superada' }}</td>
                <td><a class="button secondary" href="{{ route('fiscal.returns.dossier', [$sale->id, $entry->ulid]) }}">Dossiê</a></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </section>
  @endif
@endsection
