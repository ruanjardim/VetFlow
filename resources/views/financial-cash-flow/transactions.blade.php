@extends('layouts.admin')

@section('title', 'Composição do fluxo de caixa - VetFlow')

@section('content')
  @php
    $routeFilters = [
      'from' => $filters['from'],
      'days' => $filters['days'],
      'include_overdue' => $filters['include_overdue'] ? 1 : 0,
      ...($requiresClinic ? ['clinic_id' => $selectedClinicId] : []),
    ];
    $kindLabels = [
      'actual' => 'Realizado',
      'scheduled' => 'Previsto no vencimento',
      'overdue' => 'Vencido assumido na data inicial',
    ];
  @endphp

  <header class="topbar">
    <div>
      <h1>Composição de {{ \Carbon\CarbonImmutable::parse($selectedDate)->format('d/m/Y') }}</h1>
      <p>Conferência dos lançamentos que formam o saldo projetado deste dia.</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('financial-transactions.cash-flow', $routeFilters) }}">Voltar à projeção</a>
      <a class="button" href="{{ route('financial-transactions.cash-flow.export', $routeFilters) }}">Exportar período</a>
    </div>
  </header>

  <div class="alert info">
    <strong>Nenhum lançamento é alterado nesta tela.</strong>
    <span>Realizados usam a data do pagamento; previstos usam o vencimento; vencidos só aparecem aqui quando o cenário os assume na data inicial.</span>
  </div>

  <div class="panel">
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Natureza</th><th>Descrição</th><th>Tipo</th><th>Categoria</th><th>Origem</th><th>Vencimento</th><th>Pagamento</th><th>Valor</th></tr>
        </thead>
        <tbody>
          @forelse($transactions as $transaction)
            @php
              $origin = $transaction->salesCommissionSettlement
                ? 'Comissão de vendas · '.($transaction->salesCommissionSettlement->seller?->name ?? '-')
                : ($transaction->groomingCommissionSettlement
                  ? 'Comissão Banho & Tosa · '.($transaction->groomingCommissionSettlement->user?->name ?? '-')
                  : ($transaction->purchaseEntry
                    ? 'Compra '.$transaction->purchaseEntry->code
                    : ($transaction->sale ? 'Venda '.$transaction->sale->code : ($transaction->supplier?->name ?: ($transaction->reference ?: 'Manual')))));
            @endphp
            <tr>
              <td><span class="badge {{ $transaction->cash_flow_kind === 'overdue' ? 'warning' : ($transaction->cash_flow_kind === 'actual' ? 'success' : '') }}">{{ $kindLabels[$transaction->cash_flow_kind] }}</span></td>
              <td>{{ $transaction->description }}</td>
              <td>{{ $transaction->type === 'income' ? 'Entrada' : 'Saída' }}</td>
              <td>{{ $transaction->category?->name ?? 'Sem categoria' }}</td>
              <td>{{ $origin }}</td>
              <td>{{ $transaction->due_date?->format('d/m/Y') ?: '-' }}</td>
              <td>{{ $transaction->paid_at?->format('d/m/Y H:i') ?: '-' }}</td>
              <td>R$ {{ number_format((float) $transaction->amount, 2, ',', '.') }}</td>
            </tr>
          @empty
            <tr><td colspan="8" class="muted">Nenhum lançamento compõe esta data.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection
