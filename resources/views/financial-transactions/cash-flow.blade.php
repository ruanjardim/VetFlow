@extends('layouts.admin')

@section('title', 'Fluxo de caixa - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Fluxo de caixa</h1>
      <p>Resumo de entradas, saidas, vencidos e proximos vencimentos.</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('financial-transactions.index') }}">Ver lancamentos</a>
      <a class="button" href="{{ route('financial-transactions.create') }}">Novo lancamento</a>
    </div>
  </header>

  <section class="grid stats">
    <div class="stat">
      <span>Receita paga no mes</span>
      <strong>R$ {{ number_format($stats['income_month'] ?? 0, 2, ',', '.') }}</strong>
    </div>
    <div class="stat">
      <span>Despesa paga no mes</span>
      <strong>R$ {{ number_format($stats['expense_month'] ?? 0, 2, ',', '.') }}</strong>
    </div>
    <div class="stat">
      <span>Saldo do mes</span>
      <strong>R$ {{ number_format($stats['balance_month'] ?? 0, 2, ',', '.') }}</strong>
    </div>
    <div class="stat">
      <span>A pagar</span>
      <strong>R$ {{ number_format($stats['expense_pending'] ?? 0, 2, ',', '.') }}</strong>
    </div>
    <div class="stat">
      <span>A receber</span>
      <strong>R$ {{ number_format($stats['income_pending'] ?? 0, 2, ',', '.') }}</strong>
    </div>
    <div class="stat">
      <span>Pagamentos vencidos</span>
      <strong>R$ {{ number_format($stats['expense_overdue'] ?? 0, 2, ',', '.') }}</strong>
    </div>
    <div class="stat">
      <span>Vencem em 7 dias</span>
      <strong>R$ {{ number_format($stats['expense_next_7_days'] ?? 0, 2, ',', '.') }}</strong>
    </div>
    <div class="stat">
      <span>Receber em 7 dias</span>
      <strong>R$ {{ number_format($stats['income_next_7_days'] ?? 0, 2, ',', '.') }}</strong>
    </div>
  </section>

  <section class="content-grid">
    <div class="panel">
      <div class="panel-heading">
        <div>
          <h2>Proximos vencimentos</h2>
          <p>Contas pendentes para os proximos 15 dias.</p>
        </div>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Descricao</th>
              <th>Tipo</th>
              <th>Fornecedor</th>
              <th>Vencimento</th>
              <th>Valor</th>
              <th>Acao</th>
            </tr>
          </thead>
          <tbody>
            @forelse($upcoming as $transaction)
              <tr>
                <td>
                  {{ $transaction->description }}
                  @if((int) $transaction->installment_total > 1)
                    <div class="muted">Parcela {{ $transaction->installment_number }}/{{ $transaction->installment_total }}</div>
                  @endif
                </td>
                <td>{{ $transaction->type === 'expense' ? 'Saida' : 'Entrada' }}</td>
                <td>{{ $transaction->supplier?->name ?: '-' }}</td>
                <td>{{ optional($transaction->due_date)->format('d/m/Y') }}</td>
                <td>R$ {{ number_format((float) $transaction->amount, 2, ',', '.') }}</td>
                <td>
                  @if($transaction->sale)
                    <a class="button secondary" href="{{ route('sales.edit', $transaction->sale->id) }}">Abrir venda</a>
                  @else
                    <form class="inline" action="{{ route('financial-transactions.pay', $transaction->id) }}" method="POST">
                      @csrf
                      @method('PATCH')
                      <button type="submit">Pagar</button>
                    </form>
                  @endif
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="6" class="muted">Nenhum vencimento nos proximos 15 dias.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <div class="panel">
      <div class="panel-heading">
        <div>
          <h2>Vencidos</h2>
          <p>Lancamentos pendentes com vencimento atrasado.</p>
        </div>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Descricao</th>
              <th>Tipo</th>
              <th>Vencimento</th>
              <th>Valor</th>
            </tr>
          </thead>
          <tbody>
            @forelse($overdue as $transaction)
              <tr>
                <td>{{ $transaction->description }}</td>
                <td>{{ $transaction->type === 'expense' ? 'Saida' : 'Entrada' }}</td>
                <td>{{ optional($transaction->due_date)->format('d/m/Y') }}</td>
                <td>R$ {{ number_format((float) $transaction->amount, 2, ',', '.') }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="4" class="muted">Nenhum lancamento vencido.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </section>
@endsection
