@extends('layouts.admin')

@section('title', 'Composição do orçamento - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Composição do realizado</h1>
      <p>{{ $category_name }} em {{ $reference['label'] }} pela visão {{ strtolower($basisLabels[$filters['basis']]) }}.</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('financial-budgets.index', ['competence' => $filters['competence'], 'basis' => $filters['basis'], ...($requiresClinic ? ['clinic_id' => $selectedClinicId] : [])]) }}">Voltar ao orçamento</a>
    </div>
  </header>

  <section class="grid stats budget-detail-stats">
    <div class="stat">
      <span>Categoria</span>
      <strong>{{ $category_name }}</strong>
    </div>
    <div class="stat">
      <span>Lançamentos</span>
      <strong>{{ $stats['transactions_count'] }}</strong>
    </div>
    <div class="stat">
      <span>Total realizado</span>
      <strong>R$ {{ number_format($stats['amount_cents'] / 100, 2, ',', '.') }}</strong>
    </div>
  </section>

  <div class="alert warning">
    @if($filters['basis'] === 'cash')
      Esta composição inclui somente lançamentos pagos no mês pela data de pagamento.
    @else
      Esta composição inclui lançamentos não cancelados no mês pela data de vencimento.
    @endif
  </div>

  <section class="panel">
    <div class="table-wrap">
      <table class="financial-budget-detail-table">
        <thead>
          <tr>
            <th>Descrição</th>
            <th>Valor</th>
            <th>Vencimento</th>
            <th>Pagamento</th>
            <th>Status</th>
            <th>Origem</th>
          </tr>
        </thead>
        <tbody>
          @forelse($transactions as $transaction)
            @php
              $statusLabel = match ($transaction->status) {
                'paid' => 'Pago',
                'cancelled' => 'Cancelado',
                'overdue' => 'Vencido',
                default => 'Pendente',
              };
              $statusBadge = match ($transaction->status) {
                'paid' => 'success',
                'cancelled', 'overdue' => 'danger',
                default => 'warning',
              };
            @endphp
            <tr>
              <td>{{ $transaction->description }}</td>
              <td><strong>R$ {{ number_format((float) $transaction->amount, 2, ',', '.') }}</strong></td>
              <td>{{ optional($transaction->due_date)->format('d/m/Y') ?: '—' }}</td>
              <td>{{ optional($transaction->paid_at)->format('d/m/Y H:i') ?: '—' }}</td>
              <td><span class="badge {{ $statusBadge }}">{{ $statusLabel }}</span></td>
              <td>
                @if($transaction->sale)
                  <a href="{{ route('sales.edit', $transaction->sale->id) }}">{{ $transaction->sale->code }}</a>
                @elseif($transaction->purchaseEntry)
                  <a href="{{ route('purchase-entries.edit', $transaction->purchaseEntry->id) }}">{{ $transaction->purchaseEntry->code }}</a>
                @else
                  {{ $transaction->reference ?: 'Manual' }}
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="muted">Nenhum lançamento compõe este realizado.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>

  {{ $transactions->links() }}
@endsection
