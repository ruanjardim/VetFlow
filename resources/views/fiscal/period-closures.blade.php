@extends('layouts.admin')

@section('title', 'Histórico de fechamentos fiscais - VetFlow')

@section('content')
  @php
    $money = fn ($cents) => 'R$ '.number_format(((int) $cents) / 100, 2, ',', '.');
  @endphp

  <header class="topbar">
    <div>
      <h1>Histórico de fechamentos fiscais</h1>
      <p>{{ $clinic->trade_name ?: $clinic->corporate_name }} · competência {{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $filters['competence'])->translatedFormat('F/Y') }}</p>
    </div>
    <div class="row-actions">
      <a class="button secondary" href="{{ route('fiscal.reconciliation.index', array_filter(['clinic_id' => $requiresClinic ? $selectedClinicId : null, 'from' => $filters['competence'].'-01', 'to' => \Illuminate\Support\Carbon::createFromFormat('Y-m', $filters['competence'])->endOfMonth()->toDateString()])) }}">Abrir conciliação</a>
      <a class="button secondary" href="{{ route('fiscal.index', $requiresClinic ? ['clinic_id' => $selectedClinicId] : []) }}">Voltar ao Fiscal</a>
    </div>
  </header>

  <div class="alert info">
    <strong>Arquivo operacional:</strong> a vigência é recalculada contra as vendas, documentos e configuração atuais. Os fechamentos continuam imutáveis e não representam escrituração, declaração ou certificação contábil.
  </div>

  <form method="GET" action="{{ route('fiscal.reconciliation.closures.index') }}" class="panel panel-body filter-grid">
    @if($requiresClinic)
      <div class="field">
        <label for="closure_history_clinic">Estabelecimento</label>
        <select id="closure_history_clinic" name="clinic_id">
          @foreach($clinics as $availableClinic)
            <option value="{{ $availableClinic->id }}" @selected($selectedClinicId === $availableClinic->id)>{{ $availableClinic->trade_name ?: $availableClinic->corporate_name }}</option>
          @endforeach
        </select>
      </div>
    @endif
    <div class="field">
      <label for="closure_history_competence">Competência</label>
      <input id="closure_history_competence" name="competence" type="month" value="{{ $filters['competence'] }}" required>
    </div>
    <div class="field">
      <label for="closure_history_reviewer">Responsável</label>
      <input id="closure_history_reviewer" name="reviewer" value="{{ $filters['reviewer'] }}" maxlength="120" placeholder="Nome informado ou usuário">
    </div>
    <div class="field">
      <label for="closure_history_state">Situação</label>
      <select id="closure_history_state" name="state">
        <option value="">Todas</option>
        @foreach($stateLabels as $key => $label)
          <option value="{{ $key }}" @selected($filters['state'] === $key)>{{ $label }}</option>
        @endforeach
      </select>
    </div>
    <div class="field"><button type="submit">Pesquisar</button></div>
  </form>

  <section class="grid stats">
    <div class="stat"><span>Fechamentos encontrados</span><strong>{{ $history['summary']['total'] }}</strong></div>
    <div class="stat"><span>Vigentes</span><strong>{{ $history['summary']['current'] }}</strong></div>
    <div class="stat"><span>Superados</span><strong>{{ $history['summary']['stale'] }}</strong></div>
    <div class="stat"><span>Total histórico</span><strong>{{ $money($history['summary']['total_cents']) }}</strong></div>
  </section>

  <section class="panel">
    <div class="panel-heading"><div><h2>Fechamentos da competência</h2><p>{{ $history['paginator']->total() }} resultado(s) no filtro atual.</p></div></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Período</th><th>Responsável</th><th>Registro</th><th>Vendas</th><th>Total</th><th>Situação</th><th>Ações</th></tr></thead>
        <tbody>
          @forelse($history['paginator'] as $row)
            @php($closure = $row['closure'])
            <tr>
              <td>{{ $closure->period_from->format('d/m/Y') }} a {{ $closure->period_to->format('d/m/Y') }}</td>
              <td>{{ $closure->reviewer_name }}<div class="muted">{{ $closure->closedBy?->name ?: 'Usuário removido' }}</div></td>
              <td>{{ $closure->occurred_at->format('d/m/Y H:i') }}</td>
              <td>{{ $row['sales_count'] }}</td>
              <td>{{ $money($row['total_cents']) }}</td>
              <td>
                <span class="badge {{ $row['state'] === 'current' ? 'success' : 'warning' }}">{{ $row['state_label'] }}</span>
                @if($row['state'] === 'stale' && !$row['current_reconciliation_complete'])
                  <div class="muted">Conciliação atual pendente</div>
                @endif
              </td>
              <td>
                <div class="row-actions">
                  <a class="button secondary" href="{{ route('fiscal.reconciliation.closures.dossier', $closure->ulid) }}">Dossiê</a>
                  <a class="button secondary" href="{{ route('fiscal.reconciliation.index', array_filter(['clinic_id' => $requiresClinic ? $selectedClinicId : null, 'from' => $closure->period_from->toDateString(), 'to' => $closure->period_to->toDateString()])) }}">Conciliação</a>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="muted">Nenhum fechamento foi encontrado para os filtros informados.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if($history['paginator']->hasPages())
      <div class="panel-body">{{ $history['paginator']->links() }}</div>
    @endif
  </section>
@endsection
