@extends('layouts.admin')

@section('title', 'Conciliação fiscal - VetFlow')

@section('content')
  @php
    $query = array_filter([
      'clinic_id' => $requiresClinic ? $selectedClinicId : null,
      'from' => $report['period']['from'],
      'to' => $report['period']['to'],
      'status' => $report['status_filter'],
    ]);
  @endphp

  <header class="topbar">
    <div>
      <h1>Conciliação fiscal diária</h1>
      <p>Vendas concluídas e devolvidas comparadas com suas preparações NFC-e/NFS-e, sem emissão automática nem conclusão tributária.</p>
    </div>
    <div class="row-actions">
      <a class="button secondary" href="{{ route('fiscal.reconciliation.closures.index', $requiresClinic ? ['clinic_id' => $selectedClinicId] : []) }}">Histórico de fechamentos</a>
      <a class="button secondary" href="{{ route('fiscal.reconciliation.export', $query) }}">Exportar CSV</a>
      <a class="button secondary" href="{{ route('fiscal.index', $requiresClinic ? ['clinic_id' => $selectedClinicId] : []) }}">Voltar ao Fiscal</a>
    </div>
  </header>

  <div class="alert info">
    <strong>Controle operacional:</strong> “Sem preparação” significa apenas que o VetFlow não encontrou um documento vinculado. A obrigação de emitir, substituir, cancelar ou registrar devolução deve ser definida pela clínica e sua contabilidade.
  </div>

  <form method="GET" action="{{ route('fiscal.reconciliation.index') }}" class="panel panel-body filter-grid">
    @if($requiresClinic)
      <div class="field">
        <label for="reconciliation_clinic">Estabelecimento</label>
        <select id="reconciliation_clinic" name="clinic_id">
          @foreach($clinics as $availableClinic)
            <option value="{{ $availableClinic->id }}" @selected($selectedClinicId === $availableClinic->id)>{{ $availableClinic->trade_name ?: $availableClinic->corporate_name }}</option>
          @endforeach
        </select>
      </div>
    @endif
    <div class="field">
      <label for="reconciliation_from">De</label>
      <input id="reconciliation_from" name="from" type="date" value="{{ $report['period']['from'] }}" required>
    </div>
    <div class="field">
      <label for="reconciliation_to">Até</label>
      <input id="reconciliation_to" name="to" type="date" value="{{ $report['period']['to'] }}" required>
      <small class="muted">Máximo de 31 dias por consulta.</small>
    </div>
    <div class="field">
      <label for="reconciliation_status">Situação</label>
      <select id="reconciliation_status" name="status">
        <option value="">Todas</option>
        @foreach($report['status_labels'] as $statusKey => $statusLabel)
          <option value="{{ $statusKey }}" @selected($report['status_filter'] === $statusKey)>{{ $statusLabel }}</option>
        @endforeach
      </select>
    </div>
    <div class="field"><button type="submit">Aplicar</button></div>
  </form>

  <section class="grid stats">
    <div class="stat">
      <span>Vendas concluídas e devolvidas</span>
      <strong>{{ $report['total_sales'] }}</strong>
      <span class="muted">R$ {{ number_format($report['total_cents'] / 100, 2, ',', '.') }}</span>
    </div>
    <div class="stat">
      <span>Ação necessária</span>
      <strong>{{ $report['summary']['action_required']['count'] }}</strong>
      <span class="muted">R$ {{ number_format($report['summary']['action_required']['total_cents'] / 100, 2, ',', '.') }}</span>
    </div>
    <div class="stat">
      <span>Sem preparação</span>
      <strong>{{ $report['summary']['no_preparation']['count'] }}</strong>
      <span class="muted">R$ {{ number_format($report['summary']['no_preparation']['total_cents'] / 100, 2, ',', '.') }}</span>
    </div>
    <div class="stat">
      <span>Autorizadas</span>
      <strong>{{ $report['summary']['authorized']['count'] }}</strong>
      <span class="muted">R$ {{ number_format($report['summary']['authorized']['total_cents'] / 100, 2, ',', '.') }}</span>
    </div>
    <div class="stat">
      <span>Devoluções revisadas</span>
      <strong>{{ $report['summary']['return_reviewed']['count'] }}</strong>
      <span class="muted">R$ {{ number_format($report['summary']['return_reviewed']['total_cents'] / 100, 2, ',', '.') }}</span>
    </div>
  </section>

  <section class="panel">
    <div class="panel-heading">
      <div>
        <h2>Fechamento operacional do período</h2>
        <p>Registra a revisão humana da conciliação atual. Não transmite documentos nem substitui a validação da contabilidade.</p>
      </div>
    </div>
    <div class="panel-body">
      @if($closing['latest_closure_current'])
        <div class="alert success">
          <strong>Período fechado com evidência vigente.</strong>
          Registrado por {{ $closing['latest_closure']->reviewer_name }} em {{ $closing['latest_closure']->occurred_at->format('d/m/Y H:i') }}.
          <a href="{{ route('fiscal.reconciliation.closures.dossier', $closing['latest_closure']->ulid) }}">Abrir dossiê</a>
        </div>
      @elseif($closing['latest_closure'])
        <div class="alert warning">
          <strong>Fechamento desatualizado.</strong>
          As vendas, documentos ou a configuração do período mudaram depois do último registro. Revise a conciliação atual antes de fechar novamente.
        </div>
      @endif

      @if(!$closing['reconciled'])
        <div class="alert warning">
          <strong>Fechamento bloqueado:</strong> resolva os estados abaixo antes da confirmação.
        </div>
        <div class="row-actions">
          @foreach($closing['blocking'] as $block)
            <span class="badge warning">{{ $block['label'] }}: {{ $block['count'] }}</span>
          @endforeach
        </div>
      @elseif($closing['can_close'])
        <div class="alert info">
          <strong>Conciliação pronta para confirmação:</strong>
          {{ $closing['total_sales'] }} venda(s), R$ {{ number_format($closing['total_cents'] / 100, 2, ',', '.') }} no período.
          Um período sem vendas registra apenas que o VetFlow não encontrou movimento interno nessa janela.
        </div>
        <form method="POST" action="{{ route('fiscal.reconciliation.closures.store') }}" class="stacked-form">
          @csrf
          @if($requiresClinic)
            <input type="hidden" name="clinic_id" value="{{ $selectedClinicId }}">
          @endif
          <input type="hidden" name="from" value="{{ $closing['period']['from'] }}">
          <input type="hidden" name="to" value="{{ $closing['period']['to'] }}">
          <div class="form-grid two-columns">
            <div class="field">
              <label for="closure_reviewer_name">Responsável pela conferência</label>
              <input id="closure_reviewer_name" name="reviewer_name" value="{{ old('reviewer_name', auth()->user()->name) }}" maxlength="120" required>
            </div>
            <div class="field">
              <label for="closure_password">Senha atual do VetFlow</label>
              <input id="closure_password" name="current_password" type="password" autocomplete="current-password" required>
            </div>
          </div>
          <div class="field">
            <label for="closure_notes">Observação interna (opcional)</label>
            <textarea id="closure_notes" name="notes" rows="3" maxlength="1000">{{ old('notes') }}</textarea>
          </div>
          <label class="checkbox-line">
            <input type="checkbox" name="confirm_close" value="1" required>
            Revisei a conciliação completa deste período e confirmo este fechamento operacional.
          </label>
          @error('period')<div class="field-error">{{ $message }}</div>@enderror
          @error('current_password')<div class="field-error">{{ $message }}</div>@enderror
          @error('confirm_close')<div class="field-error">{{ $message }}</div>@enderror
          <div class="row-actions"><button type="submit">Registrar fechamento</button></div>
        </form>
      @endif
    </div>

    @if($closing['history']->isNotEmpty())
      <div class="table-wrap">
        <table>
          <thead><tr><th>Período</th><th>Responsável</th><th>Registrado por</th><th>Data</th><th>Vendas</th><th>Total</th><th>Ação</th></tr></thead>
          <tbody>
            @foreach($closing['history'] as $closure)
              <tr>
                <td>{{ $closure->period_from->format('d/m/Y') }} a {{ $closure->period_to->format('d/m/Y') }}</td>
                <td>{{ $closure->reviewer_name }}</td>
                <td>{{ $closure->closedBy?->name ?: 'Usuário removido' }}</td>
                <td>{{ $closure->occurred_at->format('d/m/Y H:i') }}</td>
                <td>{{ data_get($closure->evidence_snapshot, 'totals.sales_count', 0) }}</td>
                <td>R$ {{ number_format(data_get($closure->evidence_snapshot, 'totals.total_cents', 0) / 100, 2, ',', '.') }}</td>
                <td><a class="button secondary" href="{{ route('fiscal.reconciliation.closures.dossier', $closure->ulid) }}">Dossiê</a></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </section>

  <section class="panel">
    <div class="panel-heading">
      <div>
        <h2>Vendas no período</h2>
        <p>{{ $report['filtered_count'] }} resultado(s) no filtro atual.</p>
      </div>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Venda</th><th>Tipos</th><th>Total</th><th>Situação</th><th>Diagnóstico</th><th>Próximo passo</th><th>Ações</th></tr></thead>
        <tbody>
          @forelse($report['rows'] as $row)
            <tr>
              <td>
                <strong>{{ $row['sale_code'] }}</strong>
                <div class="muted">{{ \Illuminate\Support\Carbon::parse($row['completed_at'])->format('d/m/Y H:i') }}</div>
              </td>
              <td>{{ implode(' + ', array_map('strtoupper', $row['expected_types'])) ?: 'Indefinido' }}</td>
              <td>R$ {{ number_format($row['sale_total_cents'] / 100, 2, ',', '.') }}</td>
              <td><span class="badge {{ $row['severity'] }}">{{ $row['status_label'] }}</span></td>
              <td>{{ $row['detail'] }}</td>
              <td>{{ $row['next_action'] }}</td>
              <td>
                <div class="row-actions">
                  <a class="button secondary" href="{{ route('sales.receipt', $row['sale_id']) }}">Comprovante</a>
                  @if($row['has_return'])
                    <a class="button secondary" href="{{ route('fiscal.returns.show', $row['sale_id']) }}">Revisar devolução</a>
                  @endif
                  @foreach($row['documents'] as $document)
                    <a class="button secondary" href="{{ route('fiscal.documents.show', $document['id']) }}">{{ strtoupper($document['type']) }}</a>
                  @endforeach
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="muted">Nenhuma venda concluída foi encontrada neste período e filtro.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>
@endsection
