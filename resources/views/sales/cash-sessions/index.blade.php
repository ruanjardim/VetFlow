@extends('layouts.admin')

@section('title', 'Caixas - VetFlow')

@section('content')
  @php
    $money = fn ($value) => 'R$ '.number_format((float) $value, 2, ',', '.');
    $moneyInput = fn ($value) => number_format((float) $value, 2, ',', '');
    $clinicQuery = $requiresClinic ? ['clinic_id' => $selectedClinicId] : [];
    $statusBadge = ['open' => 'success', 'closed' => 'warning', 'reviewed' => 'muted-badge'];
  @endphp

  <header class="topbar">
    <div>
      <h1>{{ $canReview ? 'Caixas' : 'Meu caixa' }}</h1>
      <p>Abertura com fundo de troco, suprimentos, sangrias, despesas e fechamento conferido por forma de pagamento.</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('sales.cashier') }}">Movimentos de caixa</a>
      <a class="button" href="{{ route('sales.create') }}">Ponto de venda</a>
    </div>
  </header>

  @if($requiresClinic)
    <form method="GET" action="{{ route('sales.cash-sessions.index') }}" class="panel panel-body">
      <div class="field">
        <label for="cash-session-clinic">Estabelecimento</label>
        <select id="cash-session-clinic" name="clinic_id" data-auto-submit-select>
          @foreach($clinics as $clinic)
            <option value="{{ $clinic->id }}" @selected($selectedClinicId === $clinic->id)>{{ $clinic->trade_name ?: $clinic->corporate_name }}</option>
          @endforeach
        </select>
      </div>
    </form>
  @endif

  <div class="panel">
    <div class="panel-heading">
      <div>
        <h2>Meu caixa</h2>
        <p>Para receber no PDV, o seu caixa precisa estar aberto.</p>
      </div>
    </div>
    <div class="panel-body">
      @if($current)
        <div class="grid stats inventory-lot-stats">
          <div class="stat">
            <span>Caixa</span>
            <strong>{{ $current->code }}</strong>
          </div>
          <div class="stat">
            <span>Aberto em</span>
            <strong>{{ $current->opened_at->format('d/m H:i') }}</strong>
          </div>
          <div class="stat">
            <span>Fundo de troco</span>
            <strong>{{ $money($current->opening_amount) }}</strong>
          </div>
          <div class="stat">
            <span>Dinheiro esperado</span>
            <strong>{{ $money($currentSummary['cash']['expected']) }}</strong>
          </div>
        </div>
        <div class="actions">
          <a class="button" href="{{ route('sales.cash-sessions.show', $current->id) }}">Suprimento, sangria ou despesa</a>
          <a class="button secondary" href="{{ route('sales.cash-sessions.close', $current->id) }}">Fechar caixa</a>
        </div>
      @else
        <form method="POST" action="{{ route('sales.cash-sessions.store') }}" class="form-grid">
          @csrf
          @if($requiresClinic)
            <input type="hidden" name="clinic_id" value="{{ $selectedClinicId }}">
          @endif
          <div class="field">
            <label for="opening_amount">Fundo de troco</label>
            <input id="opening_amount" name="opening_amount" type="text" inputmode="decimal" value="{{ old('opening_amount', $moneyInput($suggestedOpening)) }}" data-money-input>
            <small class="muted">Dinheiro que já está na gaveta ao abrir. Sugerido: o que ficou no último fechamento.</small>
          </div>
          <div class="field">
            <label for="opening_notes">Observação (opcional)</label>
            <input id="opening_notes" name="notes" maxlength="500" value="{{ old('notes') }}">
          </div>
          <div class="field full">
            <div class="actions">
              <button type="submit">Abrir caixa</button>
            </div>
          </div>
        </form>
      @endif
    </div>
  </div>

  <div class="panel">
    <div class="panel-heading">
      <div>
        <h2>{{ $canReview ? 'Caixas da clínica' : 'Meus caixas anteriores' }}</h2>
        @if($canReview)
          <p>{{ $awaitingReview }} {{ $awaitingReview === 1 ? 'caixa fechado aguarda' : 'caixas fechados aguardam' }} conferência.</p>
        @endif
      </div>
    </div>
    @if($canReview)
      <div class="panel-body">
        <form method="GET" action="{{ route('sales.cash-sessions.index') }}" class="form-grid">
          @if($requiresClinic)
            <input type="hidden" name="clinic_id" value="{{ $selectedClinicId }}">
          @endif
          <div class="field">
            <label for="filter_status">Situação</label>
            <select id="filter_status" name="status">
              <option value="">Todas</option>
              @foreach(\App\Modules\Sales\Models\CashSession::SHORT_STATUS_LABELS as $value => $label)
                <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="field">
            <label for="filter_user">Operador</label>
            <select id="filter_user" name="user_id">
              <option value="">Todos</option>
              @foreach($operators as $operator)
                <option value="{{ $operator->id }}" @selected((int) ($filters['user_id'] ?? 0) === $operator->id)>{{ $operator->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="field">
            <label for="filter_from">Aberto de</label>
            <input id="filter_from" name="from" type="date" value="{{ $filters['from'] ?? '' }}">
          </div>
          <div class="field">
            <label for="filter_to">Até</label>
            <input id="filter_to" name="to" type="date" value="{{ $filters['to'] ?? '' }}">
          </div>
          <div class="field full">
            <div class="actions">
              <button type="submit">Filtrar</button>
              <a class="button secondary" href="{{ route('sales.cash-sessions.index', $clinicQuery) }}">Limpar</a>
            </div>
          </div>
        </form>
      </div>
    @endif
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Caixa</th>
            @if($canReview)<th>Operador</th>@endif
            <th>Aberto em</th>
            <th>Fechado em</th>
            <th>Situação</th>
            <th>Esperado</th>
            <th>Contado</th>
            <th>Diferença</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          @forelse($sessions as $session)
            <tr>
              <td><strong>{{ $session->code }}</strong></td>
              @if($canReview)<td>{{ $session->user?->name ?? '-' }}</td>@endif
              <td>{{ $session->opened_at->format('d/m/Y H:i') }}</td>
              <td>{{ $session->closed_at?->format('d/m/Y H:i') ?? '-' }}</td>
              <td>
                <span class="badge {{ $statusBadge[$session->status] ?? 'muted-badge' }}">{{ $session->shortStatusLabel() }}</span>
                @if($session->wasAutoClosed())
                  <div class="muted">Fechado automaticamente</div>
                @endif
              </td>
              <td>{{ $session->isOpen() ? '-' : $money($session->expected_total) }}</td>
              <td>{{ $session->isOpen() ? '-' : $money($session->counted_total) }}</td>
              <td>
                @if($session->isOpen())
                  -
                @elseif(abs((float) $session->difference_total) < 0.01)
                  <span class="badge success">Sem diferença</span>
                @else
                  <span class="badge warning">{{ ((float) $session->difference_total > 0 ? '+' : '−').$money(abs((float) $session->difference_total)) }}</span>
                @endif
              </td>
              <td><a class="button secondary" href="{{ route('sales.cash-sessions.show', $session->id) }}">Ver</a></td>
            </tr>
          @empty
            <tr>
              <td colspan="{{ $canReview ? 9 : 8 }}" class="muted">Nenhum caixa encontrado.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{ $sessions->links() }}
@endsection
