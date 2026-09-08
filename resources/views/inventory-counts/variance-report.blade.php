@extends('layouts.admin')

@section('title', 'Divergências de inventário - VetFlow')

@section('content')
  @php
    $money = fn ($value) => 'R$ '.number_format((float) $value, 2, ',', '.');
    $quantity = fn ($value) => number_format((float) $value, 3, ',', '.');
    $percent = fn ($value) => $value === null ? '-' : number_format((float) $value, 2, ',', '.').'%';
    $exportQuery = collect(request()->query())->except('page')->all();
  @endphp

  <header class="topbar">
    <div>
      <h1>Divergências de inventário</h1>
      <p>Precisão das contagens finalizadas e impacto das sobras e faltas pelo custo fotografado.</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('inventory-counts.index') }}">Contagens</a>
      <a class="button secondary" href="{{ route('inventory-movements.index') }}">Movimentações</a>
      <a class="button" href="{{ route('inventory-counts.variance-report.export', $exportQuery) }}">Exportar CSV</a>
    </div>
  </header>

  <div class="alert-soft">
    <strong>Leitura histórica, sem alteração de estoque</strong>
    <span>Os valores usam o custo unitário preservado em cada contagem. Precisão representa linhas conferidas sem diferença; não é uma avaliação automática de pessoas, processos ou perdas.</span>
  </div>

  <section class="grid stats inventory-lot-stats">
    <div class="stat">
      <span>Precisão das linhas</span>
      <strong>{{ $percent($stats['accuracy_percent']) }}</strong>
      <div class="muted">{{ $stats['total_items'] - $stats['divergent_items'] }} de {{ $stats['total_items'] }} sem diferença</div>
    </div>
    <div class="stat">
      <span>Contagens finalizadas</span>
      <strong>{{ $stats['total_counts'] }}</strong>
      <div class="muted">{{ $stats['affected_products'] }} produto(s) divergente(s)</div>
    </div>
    <div class="stat">
      <span>Sobras ao custo</span>
      <strong>{{ $money($stats['surplus_value']) }}</strong>
    </div>
    <div class="stat">
      <span>Faltas ao custo</span>
      <strong>{{ $money($stats['shortage_value']) }}</strong>
    </div>
    <div class="stat">
      <span>Movimento absoluto</span>
      <strong>{{ $money($stats['absolute_adjustment_value']) }}</strong>
      <div class="muted">Soma de sobras e faltas</div>
    </div>
    <div class="stat">
      <span>Impacto líquido</span>
      <strong>{{ $money($stats['net_adjustment_value']) }}</strong>
      <div class="muted">Sobras menos faltas</div>
    </div>
  </section>

  <section class="panel nested-panel">
    <div class="panel-heading">
      <div>
        <h2>Filtros</h2>
        <p>Os cartões consideram período, busca e categoria; a direção refina somente o ranking abaixo.</p>
      </div>
      <span class="badge muted-badge">{{ $rankings->total() }} produto(s)</span>
    </div>
    <div class="panel-body">
      <form class="filter-grid" action="{{ route('inventory-counts.variance-report') }}" method="GET">
        <div class="field">
          <label for="variance-period">Período</label>
          <select id="variance-period" name="period">
            @foreach($periods as $value => $label)
              <option value="{{ $value }}" @selected($filters['period'] === (string) $value)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="field">
          <label for="variance-direction">Resultado</label>
          <select id="variance-direction" name="direction">
            @foreach($directions as $value => $label)
              <option value="{{ $value }}" @selected($filters['direction'] === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="field">
          <label for="variance-category">Categoria</label>
          <select id="variance-category" name="category">
            <option value="">Todas</option>
            @foreach($categories as $category)
              <option value="{{ $category }}" @selected($filters['category'] === $category)>{{ $category }}</option>
            @endforeach
          </select>
        </div>
        <div class="field">
          <label for="variance-q">Busca</label>
          <input id="variance-q" name="q" value="{{ $filters['q'] }}" maxlength="120" placeholder="Produto, SKU ou EAN">
        </div>
        <div class="filter-actions">
          <button type="submit">Filtrar</button>
          <a class="button secondary" href="{{ route('inventory-counts.variance-report') }}">Limpar</a>
        </div>
      </form>
    </div>
  </section>

  <section class="panel">
    <div class="panel-heading">
      <div>
        <h2>Ranking por produto</h2>
        <p>Ordenado pelo maior valor absoluto movimentado no escopo selecionado.</p>
      </div>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Produto</th>
            <th>Contagens</th>
            <th>Divergências</th>
            <th>Variação física</th>
            <th>Sobras</th>
            <th>Faltas</th>
            <th>Impacto líquido</th>
            <th>Última contagem</th>
            @can('products.manage')<th>Ações</th>@endcan
          </tr>
        </thead>
        <tbody>
          @forelse($rankings as $row)
            @php($product = $row->product)
            <tr>
              <td>
                <strong>{{ $product?->name ?? 'Produto removido' }}</strong>
                <div class="muted">{{ $product?->sku ?: 'Sem SKU' }} · {{ $product?->category ?: 'Sem categoria' }}</div>
                @if(auth()->user()?->clinic_id === null)<div class="muted">{{ $product?->clinic?->trade_name ?? 'Clínica removida' }}</div>@endif
              </td>
              <td>{{ (int) $row->count_events }}</td>
              <td>{{ (int) $row->divergence_events }}</td>
              <td>
                <strong>{{ $quantity($row->net_quantity) }} {{ $product?->unit ?: 'un' }}</strong>
                <div class="muted">{{ $quantity($row->absolute_quantity) }} em movimentos</div>
              </td>
              <td>{{ $money($row->surplus_value) }}</td>
              <td>{{ $money($row->shortage_value) }}</td>
              <td>
                @if((float) $row->net_value > 0)
                  <span class="badge warning">+{{ $money($row->net_value) }}</span>
                @elseif((float) $row->net_value < 0)
                  <span class="badge danger">{{ $money($row->net_value) }}</span>
                @else
                  <span class="badge success">{{ $money(0) }}</span>
                @endif
              </td>
              <td>{{ \Illuminate\Support\Carbon::parse($row->last_counted_at)->format('d/m/Y H:i') }}</td>
              @can('products.manage')
                <td>@if($product)<a class="button secondary" href="{{ route('products.edit', $product->id) }}">Revisar produto</a>@endif</td>
              @endcan
            </tr>
          @empty
            <tr><td colspan="9" class="muted">Nenhum produto encontrado para o escopo selecionado.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>

  {{ $rankings->links() }}
@endsection
