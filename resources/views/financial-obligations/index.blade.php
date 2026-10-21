@extends('layouts.admin')

@section('title', 'Contas a pagar e receber - VetFlow')

@section('content')
  @php
    $routeFilters = array_filter([
      'clinic_id' => $requiresClinic ? $selectedClinicId : null,
      'from' => $filters['from'],
      'to' => $filters['to'],
      'type' => $filters['type'],
      'status' => $filters['status'],
      'aging' => $filters['aging'],
    ], fn ($value) => $value !== null && $value !== '');
  @endphp
  <header class="topbar">
    <div>
      <h1>Contas a pagar e receber</h1>
      <p>Vencimentos financeiros por período, tipo e situação, sem alterar os lançamentos de origem.</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('financial-obligations.export', $routeFilters) }}">Exportar CSV</a>
      <a class="button secondary" href="{{ route('financial-transactions.cash-flow') }}">Fluxo de caixa</a>
      <a class="button secondary" href="{{ route('financial-bank-reconciliation.index', $requiresClinic ? ['clinic_id' => $selectedClinicId] : []) }}">Conciliação bancária</a>
      <a class="button secondary" href="{{ route('financial-transactions.index') }}">Ver lançamentos</a>
      <a class="button" href="{{ route('financial-transactions.create') }}">Novo lançamento</a>
    </div>
  </header>

  <form method="GET" action="{{ route('financial-obligations.index') }}" class="panel panel-body">
    <div class="filter-grid compact-filter-grid">
      @if($requiresClinic)
        <div>
          <label for="obligation_clinic">Estabelecimento</label>
          <select id="obligation_clinic" name="clinic_id" required>
            @foreach($clinics as $clinic)
              <option value="{{ $clinic->id }}" @selected($selectedClinicId === $clinic->id)>{{ $clinic->trade_name ?: $clinic->corporate_name }}</option>
            @endforeach
          </select>
        </div>
      @endif
      <div>
        <label for="obligation_from">De</label>
        <input id="obligation_from" name="from" type="date" value="{{ $filters['from'] }}" required>
      </div>
      <div>
        <label for="obligation_to">Até</label>
        <input id="obligation_to" name="to" type="date" value="{{ $filters['to'] }}" required>
      </div>
      <div>
        <label for="obligation_type">Tipo</label>
        <select id="obligation_type" name="type">
          @foreach($typeLabels as $value => $label)
            <option value="{{ $value }}" @selected($filters['type'] === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label for="obligation_status">Situação</label>
        <select id="obligation_status" name="status">
          @foreach($statusLabels as $value => $label)
            <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label for="obligation_aging">Faixa de atraso</label>
        <select id="obligation_aging" name="aging">
          @foreach($agingLabels as $value => $label)
            <option value="{{ $value }}" @selected($filters['aging'] === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="filter-actions">
        <button type="submit">Atualizar visão</button>
        <a class="button secondary" href="{{ route('financial-obligations.index') }}">Período padrão</a>
      </div>
    </div>
  </form>

  @if($excluded_without_due_date > 0)
    <div class="alert warning">
      Há {{ $excluded_without_due_date }} lançamento(s) ativo(s) sem data de vencimento. Eles não entram nesta central até serem classificados.
    </div>
  @endif

  <section class="grid stats">
    <div class="stat">
      <span>Em aberto</span>
      <strong>R$ {{ number_format($stats['open_cents'] / 100, 2, ',', '.') }}</strong>
      <small>{{ $stats['open_count'] }} lançamento(s)</small>
    </div>
    <div class="stat{{ $stats['overdue_cents'] > 0 ? ' stat--alert' : '' }}">
      <span>Vencidas</span>
      <strong>R$ {{ number_format($stats['overdue_cents'] / 100, 2, ',', '.') }}</strong>
      <small>{{ $stats['overdue_count'] }} lançamento(s)</small>
    </div>
    <div class="stat">
      <span>Vencem hoje</span>
      <strong>R$ {{ number_format($stats['due_today_cents'] / 100, 2, ',', '.') }}</strong>
      <small>{{ $stats['due_today_count'] }} lançamento(s)</small>
    </div>
    <div class="stat">
      <span>A vencer</span>
      <strong>R$ {{ number_format($stats['upcoming_cents'] / 100, 2, ',', '.') }}</strong>
      <small>{{ $stats['upcoming_count'] }} lançamento(s)</small>
    </div>
    <div class="stat">
      <span>Pagas ou recebidas</span>
      <strong>R$ {{ number_format($stats['paid_cents'] / 100, 2, ',', '.') }}</strong>
      <small>{{ $stats['paid_count'] }} lançamento(s)</small>
    </div>
  </section>

  <section class="panel">
    <div class="panel-heading">
      <div>
        <h2>Faixas de atraso</h2>
        <p>Priorize cobranças e pagamentos pela idade da pendência dentro do período e tipo selecionados.</p>
      </div>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Faixa</th>
            <th>Lançamentos</th>
            <th>Valor em aberto</th>
            <th>Composição</th>
          </tr>
        </thead>
        <tbody>
          @foreach($aging_buckets as $bucket)
            <tr>
              <td><strong>{{ $bucket['label'] }}</strong></td>
              <td>{{ $bucket['count'] }}</td>
              <td>R$ {{ number_format($bucket['amount_cents'] / 100, 2, ',', '.') }}</td>
              <td>
                <a class="button secondary" href="{{ route('financial-obligations.index', [...$routeFilters, 'status' => 'overdue', 'aging' => $bucket['key']]) }}">
                  Ver composição
                </a>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>

  @if($filters['aging'] !== 'all')
    <div class="alert warning">
      A composição abaixo está limitada à faixa <strong>{{ $agingLabels[$filters['aging']] }}</strong>.
      <a href="{{ route('financial-obligations.index', [...$routeFilters, 'aging' => 'all']) }}">Remover esta faixa</a>
    </div>
  @endif

  <section class="panel">
    <div class="panel-heading">
      <div>
        <h2>Composição detalhada de {{ $reference['from']->format('d/m/Y') }} a {{ $reference['to']->format('d/m/Y') }}</h2>
        <p>{{ $typeLabels[$filters['type']] }} · {{ $statusLabels[$filters['status']] }} · {{ $agingLabels[$filters['aging']] }}</p>
      </div>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Vencimento</th>
            <th>Atraso</th>
            <th>Descrição</th>
            <th>Tipo</th>
            <th>Categoria</th>
            <th>Origem</th>
            <th>Situação</th>
            <th>Valor</th>
            <th>Ação</th>
          </tr>
        </thead>
        <tbody>
          @forelse($transactions as $transaction)
            @php
              $isOpen = in_array($transaction->status, ['pending', 'overdue'], true);
              $isOverdue = $isOpen && $transaction->due_date?->isBefore(today());
              $isToday = $isOpen && $transaction->due_date?->isToday();
              $statusLabel = match (true) {
                $transaction->status === 'paid' => $transaction->type === 'income' ? 'Recebida' : 'Paga',
                $transaction->status === 'cancelled' => 'Cancelada',
                $isOverdue => 'Vencida',
                $isToday => 'Vence hoje',
                default => 'A vencer',
              };
            @endphp
            <tr>
              <td>{{ $transaction->due_date?->format('d/m/Y') ?: '-' }}</td>
              <td>{{ $isOverdue ? (int) $transaction->due_date->diffInDays(today()).' dia(s)' : '-' }}</td>
              <td>
                <strong>{{ $transaction->description }}</strong>
                @if((int) $transaction->installment_total > 1)
                  <div class="muted">Parcela {{ $transaction->installment_number }}/{{ $transaction->installment_total }}</div>
                @endif
              </td>
              <td>{{ $transaction->type === 'income' ? 'A receber' : 'A pagar' }}</td>
              <td>{{ $transaction->category?->name ?: 'Sem categoria' }}</td>
              <td>
                @if($transaction->salesCommissionSettlement)
                  Comissão de vendas · {{ $transaction->salesCommissionSettlement->seller?->name ?? '-' }}
                @elseif($transaction->groomingCommissionSettlement)
                  Comissão Banho &amp; Tosa · {{ $transaction->groomingCommissionSettlement->user?->name ?? '-' }}
                @else
                  {{ $transaction->supplier?->name ?: ($transaction->sale ? 'Venda '.$transaction->sale->code : '-') }}
                @endif
              </td>
              <td><span class="badge {{ $isOverdue ? 'danger' : ($transaction->status === 'paid' ? 'success' : '') }}">{{ $statusLabel }}</span></td>
              <td><strong>R$ {{ number_format((float) $transaction->amount, 2, ',', '.') }}</strong></td>
              <td>
                @if($transaction->sale)
                  <a class="button secondary" href="{{ route('sales.edit', $transaction->sale->id) }}">Abrir venda</a>
                @elseif($transaction->salesCommissionSettlement)
                  <a class="button secondary" href="{{ route('commissions.index', ['from' => $transaction->salesCommissionSettlement->period_start->toDateString(), 'to' => $transaction->salesCommissionSettlement->period_end->toDateString()]) }}">Abrir comissão</a>
                @elseif($transaction->groomingCommissionSettlement)
                  <a class="button secondary" href="{{ route('grooming-commissions.index', ['from' => $transaction->groomingCommissionSettlement->period_start->toDateString(), 'to' => $transaction->groomingCommissionSettlement->period_end->toDateString()]) }}">Abrir comissão</a>
                @else
                  <a class="button secondary" href="{{ route('financial-transactions.edit', $transaction->id) }}">Abrir lançamento</a>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="9" class="muted">Nenhum vencimento encontrado para os filtros selecionados.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="panel-body">{{ $transactions->links() }}</div>
  </section>

  <section class="content-grid">
    <div class="panel panel-body">
      <h2>Registrar revisão</h2>
      <p class="muted">A revisão é interna e não paga, recebe, cancela nem altera nenhum lançamento.</p>
      <form method="POST" action="{{ route('financial-obligations.reviews.store') }}" class="stacked-form">
        @csrf
        @if($requiresClinic)<input type="hidden" name="clinic_id" value="{{ $selectedClinicId }}">@endif
        <input type="hidden" name="from" value="{{ $filters['from'] }}">
        <input type="hidden" name="to" value="{{ $filters['to'] }}">
        <input type="hidden" name="type" value="{{ $filters['type'] }}">
        <input type="hidden" name="status" value="{{ $filters['status'] }}">
        <input type="hidden" name="aging" value="{{ $filters['aging'] }}">
        <div>
          <label for="obligation_review_decision">Resultado da revisão</label>
          <select id="obligation_review_decision" name="decision" required>
            @foreach($reviewLabels as $value => $label)
              <option value="{{ $value }}" @selected(old('decision') === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <label for="obligation_review_note">Observação</label>
          <textarea id="obligation_review_note" name="note" rows="4" maxlength="1000" placeholder="Obrigatória quando houver acompanhamento pendente.">{{ old('note') }}</textarea>
        </div>
        <label class="checkbox-row">
          <input type="checkbox" name="confirm_review" value="1" required>
          <span>Revisei a composição financeira apresentada para estes filtros.</span>
        </label>
        <button type="submit">Registrar revisão</button>
      </form>
    </div>

    <div class="panel">
      <div class="panel-heading">
        <div>
          <h2>Histórico de revisões</h2>
          <p>Registros imutáveis mais recentes deste estabelecimento.</p>
        </div>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Data</th>
              <th>Responsável</th>
              <th>Decisão</th>
              <th>Período revisado</th>
              <th>Em aberto</th>
              <th>Observação</th>
            </tr>
          </thead>
          <tbody>
            @forelse($review_history as $review)
              <tr>
                <td>{{ $review['occurred_at']?->format('d/m/Y H:i') }}</td>
                <td>{{ $review['actor_name'] }}</td>
                <td><span class="badge {{ $review['decision'] === 'follow_up' ? 'warning' : 'success' }}">{{ $review['decision_label'] }}</span></td>
                <td>{{ data_get($review, 'filters.from') }} a {{ data_get($review, 'filters.to') }}</td>
                <td>R$ {{ number_format(data_get($review, 'summary.open_cents', 0) / 100, 2, ',', '.') }}</td>
                <td>{{ $review['note'] ?: '-' }}</td>
              </tr>
            @empty
              <tr><td colspan="6" class="muted">Nenhuma revisão registrada para este estabelecimento.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </section>
@endsection
