@extends('layouts.admin')

@section('title', $count->code.' - VetFlow')

@section('content')
  @php
    $quantity = fn ($value) => number_format((float) $value, 3, ',', '.');
    $money = fn ($value) => 'R$ '.number_format((float) $value, 2, ',', '.');
  @endphp

  <header class="topbar">
    <div>
      <h1>{{ $count->code }} · {{ $count->title }}</h1>
      <p>{{ $count->category ?: 'Todos os produtos ativos' }} · aberta em {{ optional($count->opened_at)->format('d/m/Y H:i') }}</p>
    </div>
    <div class="actions">
      @if($count->status === 'draft')
        <span class="badge warning">{{ $statusLabels[$count->status] }}</span>
      @elseif($count->status === 'finalized')
        <span class="badge success">{{ $statusLabels[$count->status] }}</span>
      @else
        <span class="badge muted-badge">{{ $statusLabels[$count->status] }}</span>
      @endif
      <a class="button secondary" href="{{ route('inventory-counts.index') }}">Voltar</a>
    </div>
  </header>

  <section class="grid stats inventory-lot-stats">
    <div class="stat"><span>Produtos</span><strong>{{ $summary['items'] }}</strong></div>
    <div class="stat"><span>Contados</span><strong>{{ $summary['counted'] }} / {{ $summary['items'] }}</strong></div>
    <div class="stat"><span>Divergências</span><strong>{{ $summary['divergences'] }}</strong></div>
    <div class="stat">
      <span>Valor esperado ao custo</span><strong>{{ $money($summary['expected_value']) }}</strong>
      @if($count->status === 'finalized')<div class="muted">Contado: {{ $money($summary['counted_value']) }}</div>@endif
    </div>
  </section>

  <section class="panel">
    <div class="panel-heading">
      <div>
        <h2>Rastreabilidade</h2>
        <p>A lista e o saldo esperado são fotografados na abertura e não podem ser reescritos.</p>
      </div>
    </div>
    <div class="panel-body">
      <div class="form-grid">
        @if(auth()->user()?->clinic_id === null)
          <div class="field"><label>Clínica</label><strong>{{ $count->clinic?->trade_name ?? 'Clínica removida' }}</strong></div>
        @endif
        <div class="field"><label>Aberta por</label><strong>{{ $count->createdBy?->name ?? 'Usuário removido' }}</strong></div>
        @if($count->finalized_at)
          <div class="field"><label>Finalizada por</label><strong>{{ $count->finalizedBy?->name ?? 'Usuário removido' }} em {{ $count->finalized_at->format('d/m/Y H:i') }}</strong></div>
        @endif
        @if($count->cancelled_at)
          <div class="field"><label>Cancelada por</label><strong>{{ $count->cancelledBy?->name ?? 'Usuário removido' }} em {{ $count->cancelled_at->format('d/m/Y H:i') }}</strong></div>
          <div class="field full"><label>Motivo</label><div>{{ $count->cancellation_reason }}</div></div>
        @endif
        @if($count->notes)
          <div class="field full"><label>Observações</label><div>{{ $count->notes }}</div></div>
        @endif
      </div>
    </div>
  </section>

  @if($count->isDraft())
    <div class="alert-soft">
      <strong>Conferência em andamento</strong>
      <span>Salve quantas vezes precisar. A finalização exige todos os campos e só aplica diferenças se nenhum saldo tiver mudado desde a abertura.</span>
    </div>

    <form method="POST" action="{{ route('inventory-counts.update', $count->id) }}">
      @csrf
      @method('PUT')
      <section class="panel">
        <div class="panel-heading">
          <div><h2>Quantidades físicas</h2><p>Informe o total encontrado no local, inclusive zero.</p></div>
          <button type="submit">Salvar contagem</button>
        </div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Produto</th><th>Saldo esperado</th><th>Quantidade física</th><th>Custo fotografado</th></tr></thead>
            <tbody>
              @foreach($count->items as $item)
                <tr>
                  <td>
                    <strong>{{ $item->product?->name ?? 'Produto removido' }}</strong>
                    <div class="muted">{{ $item->product?->sku ?: 'Sem SKU' }} · {{ $item->product?->unit ?: 'un' }}</div>
                  </td>
                  <td>{{ $quantity($item->expected_quantity) }} {{ $item->product?->unit ?: 'un' }}</td>
                  <td>
                    <input
                      aria-label="Quantidade física de {{ $item->product?->name ?? 'produto' }}"
                      name="counts[{{ $item->id }}]"
                      type="number"
                      min="0"
                      max="999999999.999"
                      step="0.001"
                      value="{{ old('counts.'.$item->id, $item->counted_quantity) }}"
                      placeholder="Não contado"
                    >
                  </td>
                  <td>{{ $money($item->unit_cost_snapshot) }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        <div class="panel-body">
          <div class="field full">
            <label for="inventory-count-notes">Observações</label>
            <textarea id="inventory-count-notes" name="notes" rows="3" maxlength="2000">{{ old('notes', $count->notes) }}</textarea>
          </div>
          <div class="actions"><button type="submit">Salvar contagem</button></div>
        </div>
      </section>
    </form>

    <section class="panel">
      <div class="panel-heading">
        <div><h2>Encerrar contagem</h2><p>Finalizar é irreversível e cria movimentações somente para os produtos divergentes.</p></div>
      </div>
      <div class="panel-body">
        <div class="actions">
          <form class="inline" method="POST" action="{{ route('inventory-counts.finalize', $count->id) }}">
            @csrf
            <button type="submit" data-confirm="Finalizar esta contagem e aplicar as divergências ao estoque?">Finalizar e ajustar estoque</button>
          </form>
        </div>
        <form method="POST" action="{{ route('inventory-counts.cancel', $count->id) }}">
          @csrf
          <div class="field full">
            <label for="inventory-count-cancellation-reason">Motivo do cancelamento</label>
            <input id="inventory-count-cancellation-reason" name="cancellation_reason" value="{{ old('cancellation_reason') }}" maxlength="500" required>
          </div>
          <div class="actions">
            <button class="danger" type="submit" data-confirm="Cancelar esta contagem sem alterar o estoque?">Cancelar contagem</button>
          </div>
        </form>
      </div>
    </section>
  @else
    <section class="panel">
      <div class="panel-heading">
        <div><h2>Resultado imutável</h2><p>O saldo fotografado, a quantidade contada e o ajuste gerado permanecem vinculados.</p></div>
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Produto</th><th>Esperado</th><th>Contado</th><th>Diferença</th><th>Ajuste</th></tr></thead>
          <tbody>
            @foreach($count->items as $item)
              @php($variance = (float) ($item->variance_quantity ?? 0))
              <tr>
                <td><strong>{{ $item->product?->name ?? 'Produto removido' }}</strong><div class="muted">{{ $item->product?->sku ?: 'Sem SKU' }}</div></td>
                <td>{{ $quantity($item->expected_quantity) }}</td>
                <td>{{ $item->counted_quantity === null ? '-' : $quantity($item->counted_quantity) }}</td>
                <td>
                  @if($item->variance_quantity === null)
                    <span class="muted">Não aplicada</span>
                  @elseif(abs($variance) < 0.0005)
                    <span class="badge success">Sem diferença</span>
                  @elseif($variance > 0)
                    <span class="badge warning">+{{ $quantity($variance) }}</span>
                  @else
                    <span class="badge danger">{{ $quantity($variance) }}</span>
                  @endif
                </td>
                <td>
                  @if($item->adjustmentMovement)
                    <span class="badge muted-badge">Movimento #{{ $item->adjustmentMovement->id }}</span>
                  @else
                    <span class="muted">Nenhum</span>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </section>
  @endif
@endsection
