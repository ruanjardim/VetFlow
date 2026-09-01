@extends('layouts.admin')

@section('title', 'Inventário rotativo - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Inventário rotativo</h1>
      <p>Contagens físicas com fotografia do saldo, ajustes automáticos e trilha de auditoria.</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('inventory-movements.index') }}">Movimentações</a>
      <a class="button secondary" href="{{ route('inventory-movements.radar') }}">Radar de estoque</a>
      <a class="button" href="{{ route('inventory-counts.create') }}">Nova contagem</a>
    </div>
  </header>

  <section class="grid stats inventory-lot-stats">
    <a class="stat stat-link" href="{{ route('inventory-counts.index') }}">
      <span>Total</span><strong>{{ $stats['total'] }}</strong>
    </a>
    <a class="stat stat-link" href="{{ route('inventory-counts.index', ['status' => 'draft']) }}">
      <span>Em contagem</span><strong>{{ $stats['draft'] }}</strong>
    </a>
    <a class="stat stat-link" href="{{ route('inventory-counts.index', ['status' => 'finalized']) }}">
      <span>Finalizadas</span><strong>{{ $stats['finalized'] }}</strong>
    </a>
    <a class="stat stat-link" href="{{ route('inventory-counts.index', ['status' => 'cancelled']) }}">
      <span>Canceladas</span><strong>{{ $stats['cancelled'] }}</strong>
    </a>
  </section>

  <section class="panel nested-panel">
    <div class="panel-heading">
      <div>
        <h2>Filtros</h2>
        <p>Localize uma contagem pelo código, título, categoria ou situação.</p>
      </div>
      <span class="badge muted-badge">{{ $counts->total() }} resultado(s)</span>
    </div>
    <div class="panel-body">
      <form class="filter-grid" action="{{ route('inventory-counts.index') }}" method="GET">
        <div class="field">
          <label for="inventory-count-q">Busca</label>
          <input id="inventory-count-q" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="120" placeholder="Código, título ou categoria">
        </div>
        <div class="field">
          <label for="inventory-count-status">Situação</label>
          <select id="inventory-count-status" name="status">
            <option value="">Todas</option>
            @foreach($statusLabels as $value => $label)
              <option value="{{ $value }}" @selected(($filters['status'] ?? null) === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="filter-actions">
          <button type="submit">Filtrar</button>
          <a class="button secondary" href="{{ route('inventory-counts.index') }}">Limpar</a>
        </div>
      </form>
    </div>
  </section>

  <section class="panel">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Contagem</th>
            <th>Escopo</th>
            <th>Situação</th>
            <th>Progresso</th>
            <th>Responsável</th>
            <th>Abertura</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          @forelse($counts as $count)
            <tr>
              <td>
                <strong>{{ $count->code }}</strong>
                <div>{{ $count->title }}</div>
                @if(auth()->user()?->clinic_id === null)
                  <div class="muted">{{ $count->clinic?->trade_name ?? 'Clínica removida' }}</div>
                @endif
              </td>
              <td>{{ $count->category ?: 'Todos os produtos ativos' }}</td>
              <td>
                @if($count->status === 'draft')
                  <span class="badge warning">{{ $statusLabels[$count->status] }}</span>
                @elseif($count->status === 'finalized')
                  <span class="badge success">{{ $statusLabels[$count->status] }}</span>
                @else
                  <span class="badge muted-badge">{{ $statusLabels[$count->status] }}</span>
                @endif
              </td>
              <td>{{ $count->counted_items_count }} / {{ $count->items_count }} produto(s)</td>
              <td>
                {{ $count->createdBy?->name ?? 'Usuário removido' }}
                @if($count->finalizedBy)<div class="muted">Finalizada por {{ $count->finalizedBy->name }}</div>@endif
                @if($count->cancelledBy)<div class="muted">Cancelada por {{ $count->cancelledBy->name }}</div>@endif
              </td>
              <td>{{ optional($count->opened_at)->format('d/m/Y H:i') }}</td>
              <td><a class="button secondary" href="{{ route('inventory-counts.show', $count->id) }}">Abrir</a></td>
            </tr>
          @empty
            <tr><td colspan="7" class="muted">Nenhuma contagem encontrada.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>

  {{ $counts->links() }}
@endsection
