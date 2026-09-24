@extends('layouts.admin')

@section('title', 'Orçamento '.$quote->code.' - VetFlow')

@section('content')
  @php
    $money = fn ($value) => 'R$ '.number_format((float) $value, 2, ',', '.');
    $quantity = fn ($value) => rtrim(rtrim(number_format((float) $value, 3, ',', '.'), '0'), ',');
    $status = $quote->displayStatus();
    $editable = $quote->isOpen() && ! $activeSale;
    $clinicName = $quote->clinic?->trade_name ?: $quote->clinic?->corporate_name;
    $statusBadges = ['open' => 'success', 'expired' => 'warning', 'converted' => 'muted-badge', 'cancelled' => 'danger'];
  @endphp

  <header class="topbar receipt-actions">
    <div>
      <h1>Orçamento {{ $quote->code }}</h1>
      <p>
        <span class="badge {{ $statusBadges[$status] ?? 'muted-badge' }}">{{ $quote->statusLabel() }}</span>
        Válido até {{ $quote->valid_until?->format('d/m/Y') }}
      </p>
    </div>
    <div class="actions">
      <button type="button" class="secondary" onclick="window.print()">Imprimir</button>
      <a class="button secondary" href="{{ $whatsappUrl }}" target="_blank" rel="noopener">Enviar pelo WhatsApp</a>
      @if($editable)
        <a class="button" href="{{ route('sales.create', ['quote_id' => $quote->id]) }}">Converter em venda</a>
        <a class="button secondary" href="{{ route('sales.create', ['quote_id' => $quote->id, 'mode' => 'quote']) }}">Editar</a>
      @endif
      <a class="button secondary" href="{{ route('sales.quotes.index') }}">Orçamentos</a>
    </div>
  </header>

  @if($errors->has('quote'))
    <div class="alert error receipt-actions" role="alert">{{ $errors->first('quote') }}</div>
  @endif

  @if($status === 'expired')
    <div class="alert warning receipt-actions" role="status">
      Este orçamento venceu em {{ $quote->valid_until->format('d/m/Y') }}. Ele ainda pode virar venda pelos preços orçados; confira os valores com o cliente.
    </div>
  @elseif($status === 'converted')
    <div class="alert success receipt-actions" role="status">
      Convertido em venda
      @if($quote->convertedSale)
        <a href="{{ route('sales.receipt', $quote->convertedSale->id) }}">{{ $quote->convertedSale->code }}</a>
      @endif
      em {{ $quote->converted_at?->format('d/m/Y H:i') }}.
    </div>
  @elseif($status === 'cancelled')
    <div class="alert error receipt-actions" role="status">
      Cancelado em {{ $quote->cancelled_at?->format('d/m/Y H:i') }}.
      @if($quote->cancellation_reason)
        Motivo: {{ $quote->cancellation_reason }}
      @endif
    </div>
  @endif

  @if($activeSale && $quote->isOpen())
    <div class="alert warning receipt-actions" role="status">
      Existe uma venda em andamento para este orçamento:
      <a href="{{ route('sales.edit', $activeSale->id) }}">{{ $activeSale->code }}</a>.
      Conclua ou cancele essa venda antes de editar ou cancelar o orçamento.
    </div>
  @endif

  <div class="panel receipt-card quote-document">
    <div class="panel-body">
      <div class="receipt-header">
        <div>
          <strong>{{ $clinicName ?: 'VetFlow' }}</strong>
          <span>{{ collect([$quote->clinic?->phone, $quote->clinic?->whatsapp, $quote->clinic?->email])->filter()->unique()->implode(' · ') }}</span>
        </div>
        <div>
          <strong>ORÇAMENTO {{ $quote->code }}</strong>
          <span>Emitido em {{ $quote->created_at?->format('d/m/Y H:i') }}</span>
        </div>
      </div>

      <div class="receipt-info-grid">
        <div>
          <span>Cliente</span>
          <strong>{{ $quote->tutor?->name ?? 'Consumidor não identificado' }}</strong>
        </div>
        <div>
          <span>Pet</span>
          <strong>{{ $quote->patient?->name ?? '-' }}</strong>
        </div>
        <div>
          <span>Tipo de venda</span>
          <strong>{{ $quote->saleTypeLabel() }}</strong>
        </div>
        <div>
          <span>Válido até</span>
          <strong>{{ $quote->valid_until?->format('d/m/Y') }}</strong>
        </div>
        <div>
          <span>Atendido por</span>
          <strong>{{ $quote->seller?->name ?? '-' }}</strong>
        </div>
      </div>

      @if($quote->hasDelivery() && $quote->delivery_address)
        <div class="receipt-section">
          <h2>Entrega</h2>
          <p>{{ $quote->delivery_address }}</p>
        </div>
      @endif

      <div class="table-wrap receipt-table">
        <table>
          <thead>
            <tr>
              <th>Item</th>
              <th>Qtd</th>
              <th>Valor unit.</th>
              <th>Desconto</th>
              <th>Total</th>
            </tr>
          </thead>
          <tbody>
            @forelse($quote->items as $item)
              <tr>
                <td>
                  <strong>{{ $item->description }}</strong>
                  <div class="muted">{{ $item->typeLabel() }}</div>
                </td>
                <td>{{ $quantity($item->quantity) }}</td>
                <td>{{ $money($item->unit_price) }}</td>
                <td>{{ (float) $item->discount_total > 0 ? $money($item->discount_total) : '-' }}</td>
                <td>{{ $money($item->total) }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="5" class="muted">Nenhum item informado.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div class="receipt-summary">
        <div>
          <span>Subtotal</span>
          <strong>{{ $money($quote->subtotal) }}</strong>
        </div>
        <div>
          <span>Desconto</span>
          <strong>{{ $money($quote->discount_total) }}</strong>
        </div>
        <div>
          <span>Acréscimos</span>
          <strong>{{ $money($quote->additions_total) }}</strong>
        </div>
        @if($quote->hasDelivery())
          <div>
            <span>Taxa de entrega</span>
            <strong>{{ $money($quote->delivery_fee) }}</strong>
          </div>
        @endif
        <div>
          <span>Total</span>
          <strong>{{ $money($quote->total) }}</strong>
        </div>
      </div>

      @if($quote->notes)
        <div class="receipt-section">
          <h2>Observações</h2>
          <p>{{ $quote->notes }}</p>
        </div>
      @endif

      <p class="muted quote-footnote">Documento sem valor fiscal. Valores válidos até {{ $quote->valid_until?->format('d/m/Y') }}.</p>
    </div>
  </div>

  @if($editable)
    <div class="panel nested-panel receipt-actions">
      <div class="panel-heading">
        <div>
          <h2>Cancelar orçamento</h2>
          <p>O orçamento continua no histórico, mas não pode mais virar venda.</p>
        </div>
      </div>
      <div class="panel-body">
        <form class="form-grid" method="POST" action="{{ route('sales.quotes.cancel', $quote->id) }}">
          @csrf
          @method('PATCH')
          <div class="field full">
            <label for="quote-cancel-reason">Motivo (opcional)</label>
            <input id="quote-cancel-reason" name="reason" maxlength="1000">
          </div>
          <div class="field">
            <button class="danger" type="submit" data-confirm="Cancelar o orçamento {{ $quote->code }}?">Cancelar orçamento</button>
          </div>
        </form>
      </div>
    </div>
  @endif
@endsection
