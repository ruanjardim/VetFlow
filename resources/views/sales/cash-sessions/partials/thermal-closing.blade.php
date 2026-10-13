<section class="thermal-receipt thermal-print-document thermal-paper-{{ $paperSize }}" aria-label="Relatório gerencial do caixa {{ $session->code }}">
  <header class="thermal-receipt-header">
    <strong>{{ $session->clinic?->trade_name ?? $session->clinic?->corporate_name ?? 'VetFlow' }}</strong>
    @if($session->clinic?->cnpj)
      <span>CNPJ {{ $session->clinic->cnpj }}</span>
    @endif
    <span>FECHAMENTO DE CAIXA</span>
  </header>

  <div class="thermal-divider"></div>
  <div class="thermal-receipt-meta">
    <div><span>Caixa</span><strong>{{ $session->code }}</strong></div>
    <div><span>Operador</span><strong>{{ $session->user?->name ?? '-' }}</strong></div>
    <div><span>Abertura</span><strong>{{ $session->opened_at?->format('d/m/Y H:i') }}</strong></div>
    <div><span>Fechamento</span><strong>{{ $session->closed_at?->format('d/m/Y H:i') }}</strong></div>
    <div><span>Fechado por</span><strong>{{ $session->closedBy?->name ?? ($session->wasAutoClosed() ? 'Automático' : '-') }}</strong></div>
    <div><span>Status</span><strong>{{ $session->shortStatusLabel() }}</strong></div>
  </div>

  <div class="thermal-divider"></div>
  <div class="thermal-payments">
    <strong>Dinheiro</strong>
    <div><span>Fundo de troco</span><strong>{{ $money($cash['opening']) }}</strong></div>
    <div><span>Recebido</span><strong>{{ $money($cash['received']) }}</strong></div>
    @if((float) ($cash['change'] ?? 0) > 0)
      <div><span>Troco dado</span><strong>{{ $money($cash['change']) }}</strong></div>
    @endif
    @if((float) ($cash['supplies'] ?? 0) > 0)
      <div><span>Suprimentos</span><strong>{{ $money($cash['supplies']) }}</strong></div>
    @endif
    @if((float) ($cash['withdrawals'] ?? 0) > 0)
      <div><span>Sangrias</span><strong>{{ $money($cash['withdrawals']) }}</strong></div>
    @endif
    @if((float) ($cash['expenses'] ?? 0) > 0)
      <div><span>Despesas</span><strong>{{ $money($cash['expenses']) }}</strong></div>
    @endif
    @if((float) ($cash['refunds'] ?? 0) > 0)
      <div><span>Estornos</span><strong>{{ $money($cash['refunds']) }}</strong></div>
    @endif
    <div><span>Esperado</span><strong>{{ $money($cash['expected']) }}</strong></div>
    <div><span>Contado</span><strong>{{ $money($cash['counted'] ?? $session->counted_cash) }}</strong></div>
    <div><span>Diferença</span><strong>{{ $signedMoney($cash['difference'] ?? 0) }}</strong></div>
    <div><span>Deixado na gaveta</span><strong>{{ $money($cash['left'] ?? $session->cash_left) }}</strong></div>
    <div><span>Recolhido</span><strong>{{ $money($cash['withdrawn'] ?? 0) }}</strong></div>
  </div>

  @if($methods->isNotEmpty())
    <div class="thermal-divider"></div>
    <div class="thermal-payments">
      <strong>Outras formas</strong>
      @foreach($methods as $method)
        <strong>{{ $method['label'] }} ({{ $method['count'] ?? 0 }})</strong>
        <div><span>Esperado</span><strong>{{ $money($method['expected']) }}</strong></div>
        <div><span>Contado</span><strong>{{ $money($method['counted'] ?? $method['expected']) }}</strong></div>
        @if(abs((float) ($method['difference'] ?? 0)) >= 0.01)
          <div><span>Diferença</span><strong>{{ $signedMoney($method['difference']) }}</strong></div>
        @endif
        @if((float) ($method['refunds'] ?? 0) > 0)
          <div><span>Estornos {{ $method['label'] }}</span><strong>{{ $money($method['refunds']) }}</strong></div>
        @endif
        @if((float) ($method['fees'] ?? 0) > 0)
          <div><span>Taxas {{ $method['label'] }}</span><strong>{{ $money($method['fees']) }}</strong></div>
        @endif
      @endforeach
    </div>
  @endif

  <div class="thermal-divider"></div>
  <div class="thermal-totals">
    <div><span>Total esperado</span><strong>{{ $money($session->expected_total) }}</strong></div>
    <div><span>Total contado</span><strong>{{ $money($session->counted_total) }}</strong></div>
    <div class="thermal-total"><span>Diferença</span><strong>{{ $signedMoney($session->difference_total) }}</strong></div>
  </div>

  @if($session->closing_notes)
    <div class="thermal-divider"></div>
    <div class="thermal-notes"><strong>Justificativa do fechamento</strong><p>{{ $session->closing_notes }}</p></div>
  @endif

  @if($session->review_notes)
    <div class="thermal-divider"></div>
    <div class="thermal-notes">
      <strong>Conferência gerencial{{ $session->reviewedBy ? ' · '.$session->reviewedBy->name : '' }}</strong>
      <p>{{ $session->review_notes }}</p>
    </div>
  @endif

  <div class="thermal-divider"></div>
  <footer class="thermal-receipt-footer">
    <strong>RELATÓRIO GERENCIAL</strong>
    <span>DOCUMENTO SEM VALOR FISCAL</span>
    <span>Gerado em {{ now()->format('d/m/Y H:i:s') }}</span>
    @if($printer)
      <span>Impressora: {{ $printer->name }}</span>
    @endif
  </footer>
</section>
