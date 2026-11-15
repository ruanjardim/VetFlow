@extends('layouts.admin')

@section('title', 'Saldo de '.$customer->name.' - VetFlow')

@section('content')
  @php
    $money = fn ($value) => 'R$ '.number_format((float) $value, 2, ',', '.');
    $moneyInput = fn ($value) => number_format((float) $value, 2, ',', '');
    $signedMoney = fn ($value) => ((float) $value < 0 ? '−' : '+').'R$ '.number_format(abs((float) $value), 2, ',', '.');
    $credit = (float) $summary['credit'];
    $debt = (float) $summary['debt'];
  @endphp

  <header class="topbar">
    <div>
      <h1>Saldo de {{ $customer->name }}</h1>
      <p>{{ collect([$customer->phone_secondary ?: $customer->phone, $customer->email])->filter()->implode(' · ') ?: 'Sem contato cadastrado' }}</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('sales.customer-balances.index') }}">Saldo de clientes</a>
      @can('tutors.manage')
        <a class="button secondary" href="{{ route('tutores.edit', $customer->id) }}">Ficha do responsável</a>
      @endcan
    </div>
  </header>

  <div class="grid stats inventory-lot-stats">
    <div class="stat">
      <span>Deve (fiado)</span>
      <strong>{{ $money($debt) }}</strong>
    </div>
    <div class="stat">
      <span>Vendas em aberto</span>
      <strong>{{ $summary['open_sales'] }}</strong>
    </div>
    <div class="stat">
      <span>Crédito disponível</span>
      <strong>{{ $money($credit) }}</strong>
    </div>
  </div>

  @unless($cashSession)
    <div class="alert warning">
      Para receber, guardar adiantamento ou devolver crédito em dinheiro, abra o seu caixa em <a href="{{ route('sales.cash-sessions.index', ['clinic_id' => $customer->clinic_id]) }}">Caixa</a>.
      Quitar vendas usando o crédito do cliente não precisa de caixa.
    </div>
  @endunless

  <div class="content-grid cash-session-grid">
    <div class="panel">
      <div class="panel-heading">
        <div>
          <h2>Receber vendas em aberto</h2>
          <p>O valor quita as vendas da mais antiga para a mais nova.</p>
        </div>
      </div>
      <div class="panel-body">
        @if($debt > 0)
          <form method="POST" action="{{ route('sales.customer-balances.settle', $customer->id) }}" class="form-grid" data-receipt-payment-form>
            @csrf
            <div class="field">
              <label for="settle_amount">Valor</label>
              <input id="settle_amount" name="amount" type="text" inputmode="decimal" value="{{ old('amount', $moneyInput($debt)) }}" data-money-input required>
            </div>
            <div class="field">
              <label for="settle_method">Forma</label>
              <select id="settle_method" name="payment_method_id" data-receipt-payment-method required>
                <option value="">Selecione</option>
                @if($credit > 0)
                  <option value="customer_credit" data-kind="customer_credit" data-max-installments="1" data-requires-reference="0">Crédito do cliente ({{ $money($credit) }})</option>
                @endif
                @foreach($paymentMethods as $option)
                  <option value="{{ $option->id }}" data-kind="{{ $option->kind }}" data-max-installments="{{ $option->maxInstallments() }}" data-requires-reference="{{ $option->requires_reference ? '1' : '0' }}">{{ $option->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="field" data-receipt-installments-field hidden>
              <label for="settle_installments">Parcelas</label>
              <input id="settle_installments" name="installments" type="number" min="1" max="1" value="1" data-receipt-installments>
            </div>
            <div class="field">
              <label for="payment_reference" data-receipt-reference-label>NSU / referência</label>
              <input id="payment_reference" name="reference" maxlength="255">
            </div>
            <div class="field full">
              <div class="actions">
                <button type="submit">Receber</button>
              </div>
            </div>
          </form>
        @else
          <p class="muted">Nenhuma venda em aberto.</p>
        @endif
      </div>
      @if($openSales->isNotEmpty())
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Venda</th>
                <th>Data</th>
                <th>Total</th>
                <th>Pago</th>
                <th>Em aberto</th>
              </tr>
            </thead>
            <tbody>
              @foreach($openSales as $sale)
                <tr>
                  <td><a href="{{ route('sales.edit', $sale->id) }}">{{ $sale->code }}</a></td>
                  <td>{{ $sale->sold_at?->format('d/m/Y') }}</td>
                  <td>{{ $money($sale->total) }}</td>
                  <td>{{ $money($sale->paid_total) }}</td>
                  <td><strong>{{ $money(max(0, (float) $sale->total - (float) $sale->paid_total)) }}</strong></td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </div>

    <div class="panel">
      <div class="panel-heading">
        <div>
          <h2>Crédito do cliente</h2>
          <p>Adiantamento vira receita só quando for usado numa venda.</p>
        </div>
      </div>
      <div class="panel-body">
        <form method="POST" action="{{ route('sales.customer-balances.deposit', $customer->id) }}" class="form-grid">
          @csrf
          <div class="field">
            <label for="deposit_amount">Adiantamento</label>
            <input id="deposit_amount" name="amount" type="text" inputmode="decimal" placeholder="0,00" data-money-input required>
          </div>
          <div class="field">
            <label for="deposit_method">Recebido em</label>
            <select id="deposit_method" name="payment_method_id" required>
              <option value="">Selecione</option>
              @foreach($paymentMethods as $option)
                <option value="{{ $option->id }}">{{ $option->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="field full">
            <label for="deposit_description">Descrição (opcional)</label>
            <input id="deposit_description" name="description" maxlength="255" placeholder="Ex.: Adiantamento para a ração do mês">
          </div>
          <div class="field full">
            <div class="actions">
              <button type="submit">Guardar como crédito</button>
            </div>
          </div>
        </form>
        @if($credit > 0)
          <form method="POST" action="{{ route('sales.customer-balances.refund', $customer->id) }}" class="form-grid customer-credit-refund">
            @csrf
            <div class="field">
              <label for="refund_amount">Devolver crédito</label>
              <input id="refund_amount" name="amount" type="text" inputmode="decimal" value="{{ $moneyInput($credit) }}" data-money-input required>
            </div>
            <div class="field">
              <label for="refund_method">Devolvido em</label>
              <select id="refund_method" name="payment_method_id" required>
                <option value="">Selecione</option>
                @foreach($paymentMethods as $option)
                  <option value="{{ $option->id }}">{{ $option->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="field full">
              <div class="actions">
                <button type="submit" class="secondary" data-confirm="Devolver este valor do crédito ao cliente?">Devolver ao cliente</button>
              </div>
            </div>
          </form>
        @endif
      </div>
    </div>
  </div>

  <div class="panel">
    <div class="panel-heading">
      <div>
        <h2>Extrato do crédito</h2>
        <p>Entradas e usos do crédito, do mais recente para o mais antigo.</p>
      </div>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Quando</th>
            <th>Movimento</th>
            <th>Descrição</th>
            <th>Valor</th>
            <th>Saldo</th>
            <th>Por</th>
          </tr>
        </thead>
        <tbody>
          @forelse($ledger as $entry)
            <tr>
              <td>{{ $entry->occurred_at->format('d/m/Y H:i') }}</td>
              <td>{{ $entry->typeLabel() }}</td>
              <td>
                @if($entry->sale)
                  <a href="{{ route('sales.receipt', $entry->sale_id) }}">{{ $entry->description ?: $entry->sale->code }}</a>
                @else
                  {{ $entry->description ?: '-' }}
                @endif
              </td>
              <td>{{ $signedMoney($entry->amount) }}</td>
              <td>{{ $money($entry->running_balance) }}</td>
              <td>{{ $entry->creator?->name ?? '-' }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="muted">Nenhum crédito registrado.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection
