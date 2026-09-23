@extends('layouts.admin')

@section('title', 'Comissões banho e tosa - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Comissões de banho e tosa</h1>
      <p>Gerada por serviço quando a comanda é finalizada. O fechamento cria a conta a pagar no Financeiro.</p>
    </div>
  </header>

  <form method="GET" action="{{ route('grooming-commissions.index') }}" class="panel grooming-agenda-toolbar">
    <label for="from">De</label>
    <input id="from" name="from" type="date" value="{{ $from->toDateString() }}">
    <label for="to">Até</label>
    <input id="to" name="to" type="date" value="{{ $to->toDateString() }}">
    <label for="user_id" class="sr-only">Profissional</label>
    <select id="user_id" name="user_id">
      <option value="">Todos os profissionais</option>
      @foreach($professionals as $professional)
        <option value="{{ $professional->id }}" @selected($userId === $professional->id)>{{ $professional->name }}</option>
      @endforeach
    </select>
    <button type="submit">Filtrar</button>
  </form>

  <section class="grid stats">
    <div class="stat"><span>Comissão no período</span><strong>R$ {{ number_format($totals['earned'], 2, ',', '.') }}</strong></div>
    <div class="stat"><span>A pagar (em aberto)</span><strong>R$ {{ number_format($totals['pending'], 2, ',', '.') }}</strong></div>
    <div class="stat"><span>Fechada no período</span><strong>R$ {{ number_format($totals['settled'], 2, ',', '.') }}</strong></div>
  </section>

  <div class="panel">
    <h2>Por profissional</h2>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Profissional</th><th>Serviços</th><th>Base</th><th>Comissão no período</th><th>A pagar</th><th>Fechar até {{ $to->format('d/m') }}</th></tr></thead>
        <tbody>
          @forelse($byProfessional as $row)
            <tr>
              <td><strong>{{ $row['user']?->name ?? '-' }}</strong></td>
              <td>{{ $row['services'] }}</td>
              <td>R$ {{ number_format($row['base'], 2, ',', '.') }}</td>
              <td>R$ {{ number_format($row['earned'], 2, ',', '.') }}</td>
              <td><strong>R$ {{ number_format($row['pending'], 2, ',', '.') }}</strong></td>
              <td>
                @if($row['pending'] > 0 && $row['user'])
                  <form method="POST" action="{{ route('grooming-commissions.settle') }}" class="grooming-settle-form" data-confirm="Fechar R$ {{ number_format($row['pending'], 2, ',', '.') }} de {{ $row['user']->name }} e gerar conta a pagar?">
                    @csrf
                    <input type="hidden" name="user_id" value="{{ $row['user']->id }}">
                    <input type="hidden" name="until" value="{{ $to->toDateString() }}">
                    <label class="sr-only" for="due_{{ $row['user']->id }}">Vencimento</label>
                    <input id="due_{{ $row['user']->id }}" name="due_date" type="date" value="{{ today()->addDays(5)->toDateString() }}" title="Vencimento da conta a pagar">
                    <button type="submit">Fechar e gerar conta</button>
                  </form>
                @else
                  <span class="muted">Nada a fechar</span>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="6">Nenhuma comissão no período. Defina o % no serviço (Serviços e preços) ou o padrão do profissional (Usuários e acessos).</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="panel">
    <h2>Extrato de lançamentos</h2>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Data</th><th>Profissional</th><th>Pet</th><th>Serviço / comanda</th><th>Base</th><th>%</th><th>Comissão</th><th>Situação</th></tr></thead>
        <tbody>
          @forelse($entries as $entry)
            <tr>
              <td>{{ $entry->earned_at->format('d/m/Y') }}</td>
              <td>{{ $entry->user?->name }}</td>
              <td>{{ $entry->serviceOrder?->patient?->name ?? '-' }}</td>
              <td>
                @if($entry->serviceOrder)<a href="{{ route('service-orders.edit', $entry->service_order_id) }}">{{ $entry->description }}</a>@else {{ $entry->description }} @endif
              </td>
              <td>R$ {{ number_format((float) $entry->base_amount, 2, ',', '.') }}</td>
              <td>{{ rtrim(rtrim(number_format((float) $entry->percentage, 2, ',', '.'), '0'), ',') }}%</td>
              <td>R$ {{ number_format((float) $entry->amount, 2, ',', '.') }}</td>
              <td>
                <span class="badge {{ ['pending' => 'warning', 'settled' => 'success'][$entry->status] ?? 'muted-badge' }}">{{ \App\Modules\Commissions\Models\GroomingCommission::STATUS_LABELS[$entry->status] ?? $entry->status }}</span>
                @if($entry->kind === 'reversal')<span class="badge danger">Estorno</span>@endif
              </td>
            </tr>
          @empty
            <tr><td colspan="8">Nenhum lançamento no período.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="panel">
    <h2>Fechamentos recentes</h2>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Profissional</th><th>Período</th><th>Lançamentos</th><th>Total</th><th>Vencimento</th><th>Conta a pagar</th></tr></thead>
        <tbody>
          @forelse($settlements as $settlement)
            <tr>
              <td>{{ $settlement->user?->name }}</td>
              <td>{{ $settlement->period_start->format('d/m/Y') }} a {{ $settlement->period_end->format('d/m/Y') }}</td>
              <td>{{ $settlement->entries_count }}</td>
              <td>R$ {{ number_format((float) $settlement->total, 2, ',', '.') }}</td>
              <td>{{ $settlement->due_date->format('d/m/Y') }}</td>
              <td>{{ $settlement->financialTransaction ? (['paid' => 'Paga', 'pending' => 'Em aberto', 'cancelled' => 'Cancelada'][$settlement->financialTransaction->status] ?? $settlement->financialTransaction->status) : '-' }}</td>
            </tr>
          @empty
            <tr><td colspan="6">Nenhum fechamento ainda.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection
