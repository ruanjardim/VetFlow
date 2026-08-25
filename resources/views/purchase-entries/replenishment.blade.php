@extends('layouts.admin')

@section('title', 'Reposicao inteligente - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Reposicao inteligente</h1>
      <p>Prioridades explicaveis com saldo, estoque minimo, compras recebidas em {{ $historyWindowDays }} dias e demanda liquida em {{ $demandWindowDays }} dias.</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('purchase-entries.index') }}">Entradas</a>
      <a class="button" href="{{ route('purchase-entries.create') }}">Nova entrada</a>
    </div>
  </header>

  <section class="grid stats inventory-lot-stats">
    <div class="stat">
      <span>Produtos para repor</span>
      <strong>{{ $stats['products'] ?? 0 }}</strong>
    </div>
    <div class="stat">
      <span>Criticos</span>
      <strong>{{ $stats['critical'] ?? 0 }}</strong>
    </div>
    <div class="stat">
      <span>Abaixo do minimo</span>
      <strong>{{ $stats['below_minimum'] ?? 0 }}</strong>
    </div>
    <div class="stat">
      <span>Custo estimado</span>
      <strong>R$ {{ number_format((float) ($stats['estimated_cost'] ?? 0), 2, ',', '.') }}</strong>
    </div>
    <div class="stat">
      <span>Ajustados pelo historico</span>
      <strong>{{ $stats['history_based'] ?? 0 }}</strong>
    </div>
    <div class="stat">
      <span>Sem historico recente</span>
      <strong>{{ $stats['without_history'] ?? 0 }}</strong>
    </div>
    <div class="stat">
      <span>Com demanda recente</span>
      <strong>{{ $stats['with_recent_demand'] ?? 0 }}</strong>
    </div>
    <div class="stat">
      <span>Sem demanda recente</span>
      <strong>{{ $stats['without_recent_demand'] ?? 0 }}</strong>
    </div>
  </section>

  <section class="panel">
    <div class="panel-heading">
      <div>
        <h2>Lista priorizada</h2>
        <p>A sugestao nunca compra automaticamente. Revise quantidade, fornecedor e custo antes de salvar a entrada.</p>
      </div>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Prioridade</th>
            <th>Produto</th>
            <th>Saldo / minimo</th>
            <th>Sugestao</th>
            <th>Historico recente</th>
            <th>Demanda recente</th>
            <th>Fornecedor / custo</th>
            <th>Por que sugerimos</th>
            <th>Acao</th>
          </tr>
        </thead>
        <tbody>
          @forelse($items as $item)
            @php
              $priorityBadge = match ($item['priority']) {
                'critical' => 'danger',
                'high' => 'warning',
                default => 'muted-badge',
              };
              $confidenceLabel = match ($item['confidence']) {
                'high' => 'Alta',
                'medium' => 'Media',
                default => 'Baixa',
              };
            @endphp
            <tr>
              <td>
                <span class="badge {{ $priorityBadge }}">{{ $item['priority_label'] }}</span>
                <div class="muted">Confianca {{ $confidenceLabel }}</div>
              </td>
              <td>
                <strong>{{ $item['product']->name }}</strong>
                <div class="muted">{{ $item['product']->gtin ?: $item['product']->barcode ?: 'Sem EAN/GTIN' }}</div>
                @if($item['product']->globalProduct)
                  <div class="muted"><a href="{{ route('global-products.show', $item['product']->globalProduct->id) }}">Catalogo global #{{ $item['product']->globalProduct->id }}</a></div>
                @endif
              </td>
              <td>
                {{ number_format((float) $item['stock_quantity'], 3, ',', '.') }} / {{ number_format((float) $item['minimum_stock'], 3, ',', '.') }} {{ $item['unit'] }}
                <div class="muted">Alvo apos compra: {{ number_format((float) $item['target_stock'], 3, ',', '.') }} {{ $item['unit'] }}</div>
              </td>
              <td>
                <strong>{{ number_format((float) $item['suggested_quantity'], 3, ',', '.') }} {{ $item['unit'] }}</strong>
                <div class="muted">Estimado: R$ {{ number_format((float) $item['estimated_cost'], 2, ',', '.') }}</div>
              </td>
              <td>
                @if($item['history_count'] > 0)
                  {{ $item['history_count'] }} compra(s)
                  <div class="muted">Media: {{ number_format((float) $item['average_purchase_quantity'], 3, ',', '.') }} {{ $item['unit'] }}</div>
                  @if($item['average_purchase_interval_days'])
                    <div class="muted">Intervalo medio: {{ $item['average_purchase_interval_days'] }} dias</div>
                  @endif
                @else
                  <span class="muted">Sem compras recebidas no periodo</span>
                @endif
              </td>
              <td>
                @if($item['has_recent_demand'])
                  <strong>{{ number_format((float) $item['net_demand_quantity'], 3, ',', '.') }} {{ $item['unit'] }}</strong>
                  <div class="muted">em {{ $item['demand_sales_count'] }} venda(s)</div>
                  <div class="muted">Media mensal: {{ number_format((float) $item['average_monthly_demand'], 3, ',', '.') }} {{ $item['unit'] }}</div>
                  @if($item['demand_returned_quantity'] > 0)
                    <div class="muted">Devolucoes descontadas: {{ number_format((float) $item['demand_returned_quantity'], 3, ',', '.') }}</div>
                  @endif
                @else
                  <span class="muted">Sem demanda liquida no periodo</span>
                @endif
              </td>
              <td>
                {{ $item['last_supplier_name'] ?: 'Fornecedor nao identificado' }}
                <div class="muted">Custo de referencia: R$ {{ number_format((float) $item['unit_cost'], 2, ',', '.') }}</div>
                @if($item['last_purchase_at'])
                  <div class="muted">Ultima compra: {{ $item['last_purchase_at']->format('d/m/Y') }}</div>
                @endif
              </td>
              <td>{{ $item['reason'] }}</td>
              <td>
                <div class="row-actions">
                  <a class="button secondary" href="{{ $item['purchase_url'] }}">Revisar na entrada</a>
                  <a class="button secondary" href="{{ route('products.edit', $item['product']->id) }}">Editar produto</a>
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="9" class="muted">Nenhum produto abaixo do minimo agora.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>
@endsection
