@extends('layouts.admin')

@section('title', 'Entrada de mercadorias - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Entrada de mercadorias</h1>
      <p>Compras, recebimentos, lotes e validade dos produtos.</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('suppliers.index') }}">Fornecedores</a>
      <a class="button" href="{{ route('purchase-entries.create') }}">Nova entrada</a>
    </div>
  </header>

  <div class="panel">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Codigo</th>
            <th>Fornecedor</th>
            <th>Status</th>
            <th>NF</th>
            <th>Itens</th>
            <th>Total</th>
            <th>Financeiro</th>
            <th>Recebimento</th>
            <th>Acoes</th>
          </tr>
        </thead>
        <tbody>
          @forelse($purchaseEntries as $entry)
            @php
              $statusLabel = match ($entry->status) {
                'received' => 'Recebida',
                'cancelled' => 'Cancelada',
                default => 'Rascunho',
              };
              $badgeClass = match ($entry->status) {
                'received' => 'success',
                'cancelled' => 'danger',
                default => 'warning',
              };
              $financials = $entry->financialTransactions;
              $financialCount = $financials->count();
              $pendingFinancials = $financials->where('status', 'pending');
              $nextFinancial = $pendingFinancials->sortBy('due_date')->first() ?: $financials->sortBy('due_date')->first();
              $hasOverdue = $financials->contains(fn ($financial) => $financial->status === 'overdue' || ($financial->status === 'pending' && $financial->due_date && $financial->due_date->lt(today())));
              $allPaid = $financialCount > 0 && $financials->every(fn ($financial) => $financial->status === 'paid');
              $allCancelled = $financialCount > 0 && $financials->every(fn ($financial) => $financial->status === 'cancelled');
              $financialLabel = match (true) {
                $allPaid => 'Pago',
                $allCancelled => 'Cancelado',
                $hasOverdue => 'Vencido',
                $pendingFinancials->count() > 0 => 'Pendente',
                $financialCount > 0 => 'Gerado',
                default => '-',
              };
              $financialBadge = match (true) {
                $allPaid => 'success',
                $allCancelled || $hasOverdue => 'danger',
                $pendingFinancials->count() > 0 => 'warning',
                $financialCount > 0 => 'success',
                default => 'muted-badge',
              };
            @endphp
            <tr>
              <td><strong>{{ $entry->code }}</strong></td>
              <td>{{ $entry->supplier?->name ?: '-' }}</td>
              <td><span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span></td>
              <td>{{ $entry->invoice_number ?: '-' }}</td>
              <td>{{ $entry->items_count }}</td>
              <td>R$ {{ number_format((float) $entry->total, 2, ',', '.') }}</td>
              <td>
                <span class="badge {{ $financialBadge }}">{{ $financialLabel }}</span>
                @if($financialCount > 1)
                  <div class="muted">{{ $financialCount }} parcelas</div>
                @endif
                @if($nextFinancial?->due_date)
                  <div class="muted">Vence {{ $nextFinancial->due_date->format('d/m/Y') }}</div>
                @endif
              </td>
              <td>{{ optional($entry->received_at ?? $entry->purchased_at)->format('d/m/Y H:i') ?: '-' }}</td>
              <td>
                <a class="button secondary" href="{{ route('purchase-entries.edit', $entry->id) }}">Editar</a>
                <form class="inline" action="{{ route('purchase-entries.destroy', $entry->id) }}" method="POST">
                  @csrf
                  @method('DELETE')
                  <button class="danger" type="submit" data-confirm="Remover esta entrada? O estoque lancado por ela sera revertido.">Excluir</button>
                </form>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="9" class="muted">Nenhuma entrada de mercadorias cadastrada.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{ $purchaseEntries->links() }}
@endsection
