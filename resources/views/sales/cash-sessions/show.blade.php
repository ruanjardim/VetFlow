@extends('layouts.admin')

@section('title', 'Caixa '.$session->code.' - VetFlow')

@section('content')
  @php
    $money = fn ($value) => 'R$ '.number_format((float) $value, 2, ',', '.');
    $signedMoney = fn ($value) => ((float) $value < 0 ? '−' : ((float) $value > 0 ? '+' : '')).'R$ '.number_format(abs((float) $value), 2, ',', '.');
    $cash = $figures['cash'];
    $methods = collect($figures['methods'] ?? []);
    $fees = collect($figures['fees_by_machine'] ?? []);
    $closed = ! $session->isOpen();
    $statusBadge = ['open' => 'success', 'closed' => 'warning', 'reviewed' => 'muted-badge'][$session->status] ?? 'muted-badge';
  @endphp

  <header class="topbar">
    <div>
      <h1>Caixa {{ $session->code }} <span class="badge {{ $statusBadge }}">{{ $session->shortStatusLabel() }}</span></h1>
      <p>
        {{ $session->user?->name ?? 'Operador' }} · aberto em {{ $session->opened_at->format('d/m/Y H:i') }}
        @if($session->closed_at)
          · fechado em {{ $session->closed_at->format('d/m/Y H:i') }}{{ $session->closedBy ? ' por '.$session->closedBy->name : '' }}
        @endif
      </p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('sales.cash-sessions.index', ['clinic_id' => $session->clinic_id]) }}">Caixas</a>
      @if($canOperate)
        <a class="button secondary" href="{{ route('sales.create') }}">Ponto de venda</a>
        <a class="button" href="{{ route('sales.cash-sessions.close', $session->id) }}">Fechar caixa</a>
      @endif
    </div>
  </header>

  @if($session->wasAutoClosed())
    <div class="alert warning">
      Este caixa ficou aberto de um dia para o outro e foi fechado automaticamente às {{ $session->closed_at?->format('H:i') }},
      com os valores esperados como contados. Confira a gaveta e as maquininhas antes de encerrar.
    </div>
  @endif

  @if($session->isReviewed())
    <div class="alert success">
      Conferido e encerrado por {{ $session->reviewedBy?->name ?? 'gestor' }} em {{ $session->reviewed_at?->format('d/m/Y H:i') }}.
      @if($session->review_notes)
        {{ $session->review_notes }}
      @endif
    </div>
  @endif

  <div class="grid stats inventory-lot-stats">
    <div class="stat">
      <span>Fundo de troco</span>
      <strong>{{ $money($cash['opening']) }}</strong>
    </div>
    <div class="stat">
      <span>Recebido no caixa</span>
      <strong>{{ $money($figures['totals']['received'] ?? 0) }}</strong>
    </div>
    <div class="stat">
      <span>Taxas de cartão</span>
      <strong>{{ $money($figures['totals']['fees'] ?? 0) }}</strong>
    </div>
    <div class="stat">
      <span>{{ $closed ? 'Diferença no fechamento' : 'Dinheiro esperado na gaveta' }}</span>
      <strong>{{ $closed ? $signedMoney($session->difference_total) : $money($cash['expected']) }}</strong>
    </div>
  </div>

  @if($canOperate)
    <div class="panel">
      <div class="panel-heading">
        <div>
          <h2>Movimentar dinheiro</h2>
          <p>Suprimento coloca dinheiro na gaveta; sangria retira (cofre, banco, pagamento de comissão); despesa paga uma conta com o dinheiro do caixa e entra no financeiro.</p>
        </div>
      </div>
      <div class="panel-body">
        <form method="POST" action="{{ route('sales.cash-sessions.movements.store', $session->id) }}" class="form-grid" data-cash-movement-form>
          @csrf
          <div class="field">
            <label for="movement_type">Tipo</label>
            <select id="movement_type" name="type" data-cash-movement-type required>
              @foreach($movementTypes as $value => $label)
                <option value="{{ $value }}" @selected(old('type', 'withdrawal') === $value)>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="field">
            <label for="movement_amount">Valor</label>
            <input id="movement_amount" name="amount" type="text" inputmode="decimal" placeholder="0,00" value="{{ old('amount') }}" data-money-input required>
          </div>
          <div class="field">
            <label for="movement_description">Descrição</label>
            <input id="movement_description" name="description" maxlength="255" value="{{ old('description') }}" placeholder="Ex.: Pagamento de comissão, troco do cofre" required>
          </div>
          <div class="field" data-cash-movement-category @if(old('type', 'withdrawal') !== 'expense') hidden @endif>
            <label for="movement_category">Categoria da despesa (opcional)</label>
            <input id="movement_category" name="category" maxlength="100" value="{{ old('category') }}" placeholder="Ex.: Limpeza, lanche, correio">
          </div>
          <div class="field full">
            <div class="actions">
              <button type="submit">Registrar</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  @endif

  <div class="content-grid cash-session-grid">
    <div class="panel">
      <div class="panel-heading">
        <div>
          <h2>Dinheiro na gaveta</h2>
          <p>{{ $closed ? 'Valores do fechamento.' : 'Atualizado a cada venda e movimentação.' }}</p>
        </div>
      </div>
      <div class="table-wrap">
        <table class="cash-drawer-table">
          <tbody>
            <tr><td>Fundo de troco</td><td>{{ $money($cash['opening']) }}</td></tr>
            <tr><td>Recebido em dinheiro ({{ $cash['count'] ?? 0 }})</td><td>{{ $signedMoney($cash['received']) }}</td></tr>
            <tr><td>Troco dado</td><td>{{ $signedMoney(-1 * $cash['change']) }}</td></tr>
            <tr><td>Suprimentos</td><td>{{ $signedMoney($cash['supplies']) }}</td></tr>
            <tr><td>Sangrias</td><td>{{ $signedMoney(-1 * $cash['withdrawals']) }}</td></tr>
            <tr><td>Despesas</td><td>{{ $signedMoney(-1 * $cash['expenses']) }}</td></tr>
            <tr><td>Estornos em dinheiro</td><td>{{ $signedMoney(-1 * $cash['refunds']) }}</td></tr>
            <tr><td><strong>Dinheiro esperado</strong></td><td><strong>{{ $money($cash['expected']) }}</strong></td></tr>
            @if($closed)
              <tr><td>Dinheiro contado</td><td>{{ $money($cash['counted'] ?? $session->counted_cash) }}</td></tr>
              <tr><td>Diferença</td><td>{{ $signedMoney($cash['difference'] ?? 0) }}</td></tr>
              <tr><td>Deixado na gaveta para o próximo caixa</td><td>{{ $money($cash['left'] ?? $session->cash_left) }}</td></tr>
              <tr><td>Recolhido no fechamento</td><td>{{ $money($cash['withdrawn'] ?? 0) }}</td></tr>
            @endif
          </tbody>
        </table>
      </div>
    </div>

    <div class="panel">
      <div class="panel-heading">
        <div>
          <h2>Outras formas</h2>
          <p>Cartões, Pix e transferências recebidos neste caixa.</p>
        </div>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Forma</th>
              <th>Qtd</th>
              <th>Esperado</th>
              @if($closed)
                <th>Contado</th>
                <th>Diferença</th>
              @endif
              <th>Taxas</th>
            </tr>
          </thead>
          <tbody>
            @forelse($methods as $row)
              <tr>
                <td>
                  {{ $row['label'] }}
                  @if(($row['refunds'] ?? 0) > 0)
                    <div class="muted">Estornos: {{ $money($row['refunds']) }}</div>
                  @endif
                </td>
                <td>{{ $row['count'] }}</td>
                <td>{{ $money($row['expected']) }}</td>
                @if($closed)
                  <td>{{ $money($row['counted'] ?? $row['expected']) }}</td>
                  <td>
                    @if(abs((float) ($row['difference'] ?? 0)) < 0.01)
                      -
                    @else
                      <span class="badge warning">{{ $signedMoney($row['difference']) }}</span>
                    @endif
                  </td>
                @endif
                <td>{{ $money($row['fees']) }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="{{ $closed ? 6 : 4 }}" class="muted">Nenhum recebimento fora do dinheiro.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
      @if($fees->isNotEmpty())
        <div class="panel-body">
          <p class="muted">
            Taxas por maquininha{{ $closed ? ', lançadas como despesa paga no fechamento' : ', lançadas como despesa quando o caixa for fechado' }}:
            @foreach($fees as $fee)
              <strong>{{ $fee['label'] }}</strong> {{ $money($fee['amount']) }}@if(! $loop->last) · @endif
            @endforeach
          </p>
        </div>
      @endif
    </div>
  </div>

  @if($closed && ($session->closing_notes || ($canReview && $session->isClosed())))
    <div class="panel">
      <div class="panel-heading">
        <div>
          <h2>Conferência</h2>
          @if($session->closing_notes)
            <p>Observação do fechamento: {{ $session->closing_notes }}</p>
          @endif
        </div>
      </div>
      @if($canReview && $session->isClosed())
        <div class="panel-body">
          <form method="POST" action="{{ route('sales.cash-sessions.review', $session->id) }}" class="form-grid">
            @csrf
            <div class="field full">
              <label for="review_notes">Observação da conferência (opcional)</label>
              <textarea id="review_notes" name="review_notes" maxlength="2000" rows="2">{{ old('review_notes') }}</textarea>
            </div>
            <div class="field full">
              <div class="actions">
                <button type="submit">Conferir e encerrar</button>
              </div>
            </div>
          </form>
          <form method="POST" action="{{ route('sales.cash-sessions.reopen', $session->id) }}" class="inline">
            @csrf
            <button type="submit" class="secondary" data-confirm="Reabrir o caixa {{ $session->code }}? As taxas lançadas no fechamento serão canceladas e o operador poderá fechar de novo.">Reabrir caixa</button>
          </form>
        </div>
      @endif
    </div>
  @endif

  <div class="panel">
    <div class="panel-heading">
      <div>
        <h2>Movimentações</h2>
        <p>Suprimentos, sangrias, despesas e estornos deste caixa.</p>
      </div>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Quando</th>
            <th>Tipo</th>
            <th>Descrição</th>
            <th>Forma</th>
            <th>Valor</th>
            <th>Por</th>
          </tr>
        </thead>
        <tbody>
          @forelse($summary['movements'] as $movement)
            <tr>
              <td>{{ $movement->occurred_at->format('d/m H:i') }}</td>
              <td>{{ $movement->typeLabel() }}</td>
              <td>
                {{ $movement->description }}
                @if($movement->category)
                  <div class="muted">{{ $movement->category }}</div>
                @endif
              </td>
              <td>{{ $movement->paymentMethod?->name ?? (\App\Modules\Sales\Models\PaymentMethod::KIND_LABELS[$movement->method] ?? $movement->method) }}</td>
              <td>{{ $signedMoney($movement->signedAmount()) }}</td>
              <td>{{ $movement->creator?->name ?? '-' }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="muted">Nenhuma movimentação.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="panel">
    <div class="panel-heading">
      <div>
        <h2>Recebimentos</h2>
        <p>Pagamentos de vendas recebidos neste caixa.</p>
      </div>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Quando</th>
            <th>Venda</th>
            <th>Forma</th>
            <th>Parcelas</th>
            <th>Valor</th>
            <th>Taxa</th>
          </tr>
        </thead>
        <tbody>
          @forelse($summary['payments'] as $payment)
            <tr>
              <td>{{ $payment->paid_at?->format('d/m H:i') ?? '-' }}</td>
              <td>
                <a href="{{ route('sales.receipt', $payment->sale_id) }}">{{ $payment->sale?->code ?? '#'.$payment->sale_id }}</a>
                @if($payment->status === 'cancelled')
                  <div class="muted">venda cancelada, valor devolvido como estorno</div>
                @endif
              </td>
              <td>{{ $payment->methodLabel() }}</td>
              <td>{{ $payment->installments ?? 1 }}x</td>
              <td>{{ $money($payment->amount) }}</td>
              <td>{{ $money($payment->fee_amount) }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="muted">Nenhum recebimento ainda.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection
