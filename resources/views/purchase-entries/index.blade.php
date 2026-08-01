@extends('layouts.admin')

@section('title', 'Entrada de mercadorias - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Entrada de mercadorias</h1>
      <p>Compras, recebimentos, lotes e validade dos produtos.</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('purchase-entries.replenishment') }}">Reposicao inteligente</a>
      <a class="button secondary" href="{{ route('suppliers.index') }}">Fornecedores</a>
      <a class="button" href="{{ route('purchase-entries.create') }}">Nova entrada</a>
    </div>
  </header>

  @php
    $purchaseStats = $purchaseInsights['stats'] ?? [];
    $replenishmentItems = $purchaseInsights['replenishment'] ?? collect();
    $recentCosts = $purchaseInsights['recentCosts'] ?? collect();
  @endphp
  <section class="grid stats inventory-lot-stats">
    <a class="stat stat-link" href="{{ route('purchase-entries.index') }}">
      <span>Entradas no mes</span>
      <strong>{{ $purchaseStats['entries_month'] ?? 0 }}</strong>
    </a>
    <a class="stat stat-link" href="{{ route('purchase-entries.replenishment') }}">
      <span>Itens para repor</span>
      <strong>{{ $purchaseStats['replenishment_items'] ?? 0 }}</strong>
    </a>
    <div class="stat">
      <span>Compras recebidas</span>
      <strong>R$ {{ number_format((float) ($purchaseStats['month_total'] ?? 0), 2, ',', '.') }}</strong>
    </div>
    <div class="stat">
      <span>A pagar</span>
      <strong>R$ {{ number_format((float) ($purchaseStats['pending_payables'] ?? 0), 2, ',', '.') }}</strong>
    </div>
    <div class="stat">
      <span>Vencido</span>
      <strong>R$ {{ number_format((float) ($purchaseStats['overdue_payables'] ?? 0), 2, ',', '.') }}</strong>
    </div>
    <div class="stat">
      <span>Previsao reposicao</span>
      <strong>R$ {{ number_format((float) ($purchaseStats['estimated_replenishment_cost'] ?? 0), 2, ',', '.') }}</strong>
    </div>
  </section>

  <section class="content-grid">
    <div class="panel">
      <div class="panel-heading">
        <div>
          <h2>Reposicao inteligente</h2>
          <p>Prioridades por saldo, minimo e historico recente de compras recebidas.</p>
        </div>
        <a class="button secondary" href="{{ route('purchase-entries.replenishment') }}">Ver todos</a>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Prioridade</th>
              <th>Produto</th>
              <th>Saldo</th>
              <th>Sugestao</th>
              <th>Acao</th>
            </tr>
          </thead>
          <tbody>
            @forelse($replenishmentItems as $item)
              @php
                $priorityBadge = match ($item['priority']) {
                  'critical' => 'danger',
                  'high' => 'warning',
                  default => 'muted-badge',
                };
              @endphp
              <tr>
                <td><span class="badge {{ $priorityBadge }}">{{ $item['priority_label'] }}</span></td>
                <td>
                  <strong>{{ $item['product']->name }}</strong>
                  <div class="muted">{{ $item['product']->gtin ?: $item['product']->barcode ?: 'Sem EAN' }}</div>
                </td>
                <td>{{ number_format((float) $item['stock_quantity'], 3, ',', '.') }} / {{ number_format((float) $item['minimum_stock'], 3, ',', '.') }} {{ $item['unit'] }}</td>
                <td>{{ number_format((float) $item['suggested_quantity'], 3, ',', '.') }} {{ $item['unit'] }}</td>
                <td><a class="button secondary" href="{{ $item['purchase_url'] }}">Revisar compra</a></td>
              </tr>
            @empty
              <tr>
                <td colspan="5" class="muted">Nenhum produto abaixo do minimo agora.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <div class="panel">
      <div class="panel-heading">
        <div>
          <h2>Custos recentes</h2>
          <p>Ultimos custos recebidos na entrada.</p>
        </div>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Produto</th>
              <th>Custo</th>
              <th>Margem</th>
            </tr>
          </thead>
          <tbody>
            @forelse($recentCosts as $cost)
              <tr>
                <td>{{ $cost['product'] }}</td>
                <td>R$ {{ number_format((float) $cost['unit_cost'], 2, ',', '.') }}</td>
                <td>{{ $cost['margin_percent'] !== null ? number_format((float) $cost['margin_percent'], 2, ',', '.').'%' : '-' }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="3" class="muted">Nenhum custo recente registrado.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </section>

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
                @if($financialCount > 0)
                  <a class="badge {{ $financialBadge }}" href="{{ route('financial-transactions.index', ['purchase_entry_id' => $entry->id]) }}">{{ $financialLabel }}</a>
                @else
                  <span class="badge {{ $financialBadge }}">{{ $financialLabel }}</span>
                @endif
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
