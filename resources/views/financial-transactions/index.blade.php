@extends('layouts.admin')

@section('title', 'Financeiro - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Financeiro</h1>
      <p>Entradas, saidas e recebimentos.</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('financial-transactions.cash-flow') }}">Fluxo de caixa</a>
      <a class="button" href="{{ route('financial-transactions.create') }}">Novo lancamento</a>
    </div>
  </header>

  @if(! empty($filters))
    <div class="alert-soft">
      <strong>Financeiro filtrado</strong>
      <span>
        @if(! empty($filters['purchase_entry_id']))
          Mostrando lancamentos da entrada #{{ $filters['purchase_entry_id'] }}.
        @elseif(! empty($filters['status']))
          Mostrando status {{ $filters['status'] }}.
        @else
          Mostrando lancamentos filtrados.
        @endif
      </span>
      <a class="button secondary" href="{{ route('financial-transactions.index') }}">Limpar filtro</a>
    </div>
  @endif

  <div class="panel">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Descricao</th>
            <th>Tipo</th>
            <th>Fornecedor</th>
            <th>Valor</th>
            <th>Vencimento</th>
            <th>Origem</th>
            <th>Pagamento</th>
            <th>Status</th>
            <th>Acoes</th>
          </tr>
        </thead>
        <tbody>
          @forelse($financialTransactions as $transaction)
            @php
              $typeLabel = $transaction->type === 'expense' ? 'Saida' : 'Entrada';
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
              $methodLabel = match ($transaction->payment_method) {
                'cash' => 'Dinheiro',
                'pix' => 'PIX',
                'debit_card' => 'Cartao de debito',
                'credit_card' => 'Cartao de credito',
                'transfer' => 'Transferencia',
                'bank_slip' => 'Boleto',
                'other' => 'Outro',
                default => '-',
              };
            @endphp
            <tr>
              <td>
                {{ $transaction->description }}
                @if((int) $transaction->installment_total > 1)
                  <div class="muted">Parcela {{ $transaction->installment_number }}/{{ $transaction->installment_total }}</div>
                @endif
              </td>
              <td>{{ $typeLabel }}</td>
              <td>{{ $transaction->supplier?->name ?: '-' }}</td>
              <td>R$ {{ number_format((float) $transaction->amount, 2, ',', '.') }}</td>
              <td>{{ optional($transaction->due_date)->format('d/m/Y') }}</td>
              <td>
                @if($transaction->purchaseEntry)
                  <a href="{{ route('purchase-entries.edit', $transaction->purchaseEntry->id) }}">{{ $transaction->purchaseEntry->code }}</a>
                  <div class="muted">{{ $transaction->reference ?: $transaction->purchaseEntry->invoice_number }}</div>
                @else
                  {{ $transaction->reference ?: '-' }}
                @endif
              </td>
              <td>
                {{ $methodLabel }}
                @if($transaction->paid_at)
                  <div class="muted">{{ $transaction->paid_at->format('d/m/Y H:i') }}</div>
                @endif
              </td>
              <td><span class="badge {{ $statusBadge }}">{{ $statusLabel }}</span></td>
              <td>
                <a class="button secondary" href="{{ route('financial-transactions.edit', $transaction->id) }}">Editar</a>
                @if($transaction->status !== 'paid' && $transaction->status !== 'cancelled')
                  <form class="inline" action="{{ route('financial-transactions.pay', $transaction->id) }}" method="POST">
                    @csrf
                    @method('PATCH')
                    <button type="submit">Pagar</button>
                  </form>
                  <form class="inline" action="{{ route('financial-transactions.cancel', $transaction->id) }}" method="POST">
                    @csrf
                    @method('PATCH')
                    <button class="secondary" type="submit" data-confirm="Cancelar este lancamento?">Cancelar</button>
                  </form>
                @endif
                <form class="inline" action="{{ route('financial-transactions.destroy', $transaction->id) }}" method="POST">
                  @csrf
                  @method('DELETE')
                  <button class="danger" type="submit" data-confirm="Remover este lancamento?">Excluir</button>
                </form>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="9" class="muted">Nenhum lancamento cadastrado.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{ $financialTransactions->withQueryString()->links() }}
@endsection
