@extends('layouts.admin')

@section('title', 'Orçamentos - VetFlow')

@section('content')
  @php
    $money = fn ($value) => 'R$ '.number_format((float) $value, 2, ',', '.');
    $statusBadges = [
      'open' => 'success',
      'expired' => 'warning',
      'converted' => 'muted-badge',
      'cancelled' => 'danger',
    ];
    $statusFilters = [
      '' => 'Todos',
      'open' => 'Abertos',
      'expired' => 'Vencidos',
      'converted' => 'Convertidos em venda',
      'cancelled' => 'Cancelados',
    ];
  @endphp

  <header class="topbar">
    <div>
      <h1>Orçamentos</h1>
      <p>Orçamentos feitos no PDV. Não baixam estoque nem geram financeiro até virarem venda.</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('sales.index') }}">Histórico de vendas</a>
      <a class="button" href="{{ route('sales.create', ['mode' => 'quote']) }}">Novo orçamento</a>
    </div>
  </header>

  <section class="panel nested-panel">
    <div class="panel-body">
      <form class="filter-grid" action="{{ route('sales.quotes.index') }}" method="GET">
        <div class="field">
          <label for="quote-status">Situação</label>
          <select id="quote-status" name="status">
            @foreach($statusFilters as $value => $label)
              <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="field">
          <label for="quote-q">Busca</label>
          <input id="quote-q" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100" placeholder="Código, cliente ou pet">
        </div>
        <div class="filter-actions">
          <button type="submit">Filtrar</button>
          <a class="button secondary" href="{{ route('sales.quotes.index') }}">Limpar</a>
        </div>
      </form>
    </div>
  </section>

  <div class="panel">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Código</th>
            <th>Data</th>
            <th>Cliente</th>
            <th>Pet</th>
            <th>Tipo</th>
            <th>Validade</th>
            <th>Total</th>
            <th>Situação</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          @forelse($quotes as $quote)
            @php($status = $quote->displayStatus())
            <tr>
              <td><strong>{{ $quote->code }}</strong></td>
              <td>{{ $quote->created_at?->format('d/m/Y H:i') }}</td>
              <td>{{ $quote->tutor?->name ?? 'Consumidor não identificado' }}</td>
              <td>{{ $quote->patient?->name ?? '-' }}</td>
              <td>{{ \App\Modules\Sales\Support\SaleType::shortLabel($quote->sale_type) }}</td>
              <td>{{ $quote->valid_until?->format('d/m/Y') }}</td>
              <td>{{ $money($quote->total) }}</td>
              <td>
                <span class="badge {{ $statusBadges[$status] ?? 'muted-badge' }}">{{ $quote->statusLabel() }}</span>
                @if($status === 'converted' && $quote->convertedSale)
                  <div class="muted">{{ $quote->convertedSale->code }}</div>
                @endif
              </td>
              <td>
                <a class="button secondary" href="{{ route('sales.quotes.show', $quote->id) }}">Abrir</a>
                @if($quote->isOpen())
                  <a class="button secondary" href="{{ route('sales.create', ['quote_id' => $quote->id]) }}">Converter em venda</a>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="9" class="muted">Nenhum orçamento encontrado.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{ $quotes->links() }}
@endsection
