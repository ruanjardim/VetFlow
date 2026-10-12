<section class="thermal-receipt thermal-print-document thermal-paper-{{ $paperSize }}" aria-label="Cupom não fiscal da venda {{ $sale->code }}">
  <header class="thermal-receipt-header">
    <strong>{{ $sale->clinic?->trade_name ?? $sale->clinic?->corporate_name ?? 'VetFlow' }}</strong>
    @if($sale->clinic?->cnpj)
      <span>CNPJ {{ $sale->clinic->cnpj }}</span>
    @endif
    <span>CUPOM NÃO FISCAL</span>
  </header>

  <div class="thermal-divider"></div>
  <div class="thermal-receipt-meta">
    <div><span>Venda</span><strong>{{ $sale->code }}</strong></div>
    <div><span>Data</span><strong>{{ optional($sale->sold_at)->format('d/m/Y H:i') }}</strong></div>
    @if($sale->tutor)
      <div><span>Cliente</span><strong>{{ $sale->tutor->name }}</strong></div>
    @endif
    @if($sale->patient)
      <div><span>Pet</span><strong>{{ $sale->patient->name }}</strong></div>
    @endif
  </div>

  <div class="thermal-divider"></div>
  <div class="thermal-items">
    @foreach($sale->items as $item)
      <article class="thermal-item">
        <strong>{{ $item->description }}</strong>
        <div>
          <span>{{ $quantity($item->quantity) }} × {{ $money($item->unit_price) }}</span>
          <strong>{{ $money($item->total) }}</strong>
        </div>
      </article>
    @endforeach
  </div>

  <div class="thermal-divider"></div>
  <div class="thermal-totals">
    <div><span>Subtotal</span><strong>{{ $money($sale->subtotal) }}</strong></div>
    @if((float) $sale->discount_total > 0)
      <div><span>Desconto</span><strong>- {{ $money($sale->discount_total) }}</strong></div>
    @endif
    @if((float) $sale->additions_total > 0)
      <div><span>Acréscimo</span><strong>{{ $money($sale->additions_total) }}</strong></div>
    @endif
    @if($sale->hasDelivery() && (float) $sale->delivery_fee > 0)
      <div><span>Entrega</span><strong>{{ $money($sale->delivery_fee) }}</strong></div>
    @endif
    <div class="thermal-total"><span>Total</span><strong>{{ $money($sale->total) }}</strong></div>
    <div><span>Pago</span><strong>{{ $money($sale->paid_total) }}</strong></div>
    @if((float) $sale->change_total > 0)
      <div><span>Troco</span><strong>{{ $money($sale->change_total) }}</strong></div>
    @endif
    @if((float) $sale->return_total > 0)
      <div><span>Devolvido</span><strong>{{ $money($sale->return_total) }}</strong></div>
    @endif
    @if((float) $sale->refunded_total > 0)
      <div><span>Estornado</span><strong>{{ $money($sale->refunded_total) }}</strong></div>
    @endif
  </div>

  @if($sale->payments->isNotEmpty())
    <div class="thermal-divider"></div>
    <div class="thermal-payments">
      <strong>Pagamentos</strong>
      @foreach($sale->payments as $payment)
        @php
          $paymentStatus = match ($payment->status ?? 'pending') {
            'paid' => null,
            'cancelled' => 'Cancelado',
            'refunded' => 'Estornado',
            default => 'Pendente',
          };
        @endphp
        <div>
          <span>
            {{ $payment->methodLabel() }}{{ ($payment->installments ?? 1) > 1 ? ' · '.$payment->installments.'x' : '' }}
            {{ $paymentStatus ? ' · '.$paymentStatus : '' }}
          </span>
          <strong>{{ $money($payment->amount) }}</strong>
        </div>
      @endforeach
    </div>
  @endif

  @if($sale->notes)
    <div class="thermal-divider"></div>
    <div class="thermal-notes"><strong>Observações</strong><p>{{ $sale->notes }}</p></div>
  @endif

  <div class="thermal-divider"></div>
  <footer class="thermal-receipt-footer">
    <strong>DOCUMENTO SEM VALOR FISCAL</strong>
    <span>Comprovante interno de venda e pagamento.</span>
    <span>Não substitui NFC-e, NFS-e ou documento fiscal.</span>
    @if($printer)
      <span>Impressora: {{ $printer->name }}</span>
    @endif
  </footer>
</section>
