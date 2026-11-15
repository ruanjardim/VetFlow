@php
  $percentInput = fn ($value) => $value === null || $value === '' ? '' : number_format((float) $value, 2, ',', '');
  $selectedKind = old('kind', $method?->kind ?? $initialKind);
  $isCard = in_array($selectedKind, \App\Modules\Sales\Models\PaymentMethod::CARD_KINDS, true);
  $isCredit = $selectedKind === 'credit_card';
  $defaultSettlementDays = ['debit_card' => 1, 'credit_card' => 30][$selectedKind] ?? 0;
@endphp

<div class="form-grid" data-payment-method-form>
  @if($requiresClinic)
    <div class="field full">
      <label for="clinic_id">Estabelecimento</label>
      @if($method)
        <input type="hidden" name="clinic_id" value="{{ $selectedClinicId }}">
        <input value="{{ $clinics->firstWhere('id', $selectedClinicId)?->trade_name ?: $clinics->firstWhere('id', $selectedClinicId)?->corporate_name }}" disabled>
      @else
        <select id="clinic_id" name="clinic_id" required>
          @foreach($clinics as $clinic)
            <option value="{{ $clinic->id }}" @selected((int) old('clinic_id', $selectedClinicId) === $clinic->id)>{{ $clinic->trade_name ?: $clinic->corporate_name }}</option>
          @endforeach
        </select>
      @endif
    </div>
  @endif
  <div class="field">
    <label for="name">Nome no PDV</label>
    <input id="name" name="name" value="{{ old('name', $method?->name) }}" placeholder="Ex.: Rede Visa Crédito" maxlength="100" required autofocus>
  </div>
  <div class="field">
    <label for="kind">Tipo</label>
    <select id="kind" name="kind" data-payment-method-kind required>
      @foreach($kinds as $value => $label)
        <option value="{{ $value }}" @selected($selectedKind === $value)>{{ $label }}</option>
      @endforeach
    </select>
  </div>
  <div class="field">
    <label for="acquirer">Maquininha / operadora</label>
    <input id="acquirer" name="acquirer" value="{{ old('acquirer', $method?->acquirer) }}" placeholder="Ex.: Rede, PagSeguro, Stone" maxlength="100">
  </div>
  <div class="field" data-payment-method-card @unless($isCard) hidden @endunless>
    <label for="card_brand">Bandeira</label>
    <input id="card_brand" name="card_brand" value="{{ old('card_brand', $method?->card_brand) }}" placeholder="Ex.: Visa (vazio = todas)" maxlength="50" @disabled(! $isCard)>
  </div>
  <div class="field">
    <label for="fee_percent" data-payment-method-fee-label>{{ $isCredit ? 'Taxa à vista (%)' : 'Taxa (%)' }}</label>
    <input id="fee_percent" name="fee_percent" type="text" inputmode="decimal" value="{{ old('fee_percent', $percentInput($method?->fee_percent ?? 0)) }}" placeholder="0,00">
  </div>
  <div class="field">
    <label for="settlement_days">Prazo de repasse (dias)</label>
    <input id="settlement_days" name="settlement_days" type="number" min="0" max="365" value="{{ old('settlement_days', $method?->settlement_days ?? $defaultSettlementDays) }}" required>
    <small class="muted">Em quantos dias o valor cai na conta: 0 para na hora, 1 para o dia seguinte. No crédito parcelado, é o prazo da 1ª parcela.</small>
  </div>
  <div class="field" data-payment-method-credit @unless($isCredit) hidden @endunless>
    <label for="max_installments">Parcelas até</label>
    <input id="max_installments" name="max_installments" type="number" min="1" max="{{ \App\Modules\Sales\Models\PaymentMethod::MAX_INSTALLMENTS }}" value="{{ old('max_installments', $method?->max_installments ?? ($isCredit ? 12 : 1)) }}" @disabled(! $isCredit)>
  </div>
  <div class="field" data-payment-method-credit @unless($isCredit) hidden @endunless>
    <label for="installment_fee_percent">Taxa parcelada (%)</label>
    <input id="installment_fee_percent" name="installment_fee_percent" type="text" inputmode="decimal" value="{{ old('installment_fee_percent', $percentInput($method?->installment_fee_percent)) }}" placeholder="Vazio = mesma taxa" @disabled(! $isCredit)>
    <small class="muted">Usada de 2 parcelas em diante.</small>
  </div>
  <div class="field" data-payment-method-credit @unless($isCredit) hidden @endunless>
    <label for="installment_settlement">Repasse das parcelas</label>
    <select id="installment_settlement" name="installment_settlement" @disabled(! $isCredit)>
      @foreach($installmentSettlements as $value => $label)
        <option value="{{ $value }}" @selected(old('installment_settlement', $method?->installmentSettlement() ?? 'monthly') === $value)>{{ $label }}</option>
      @endforeach
    </select>
    <small class="muted">A 1ª parcela cai no prazo de repasse. Sem antecipação, as demais caem a cada 30 dias.</small>
  </div>
  <div class="field">
    <label for="requires_reference">NSU / referência</label>
    <select id="requires_reference" name="requires_reference" required>
      <option value="0" @selected(! old('requires_reference', $method?->requires_reference ?? false))>Opcional no recebimento</option>
      <option value="1" @selected(old('requires_reference', $method?->requires_reference ?? false))>Obrigatório no recebimento</option>
    </select>
    <small class="muted">Para cartão, é o NSU ou código de autorização do comprovante da maquininha.</small>
  </div>
  <div class="field">
    <label for="active">Status</label>
    <select id="active" name="active" required>
      <option value="1" @selected(old('active', $method?->active ?? true))>Ativa no PDV</option>
      <option value="0" @selected(! old('active', $method?->active ?? true))>Inativa</option>
    </select>
  </div>
  <div class="field">
    <label for="sort_order">Ordem no PDV</label>
    <input id="sort_order" name="sort_order" type="number" min="0" max="9999" value="{{ old('sort_order', $method?->sort_order) }}" placeholder="Automática">
  </div>
  <div class="field full">
    <div class="actions">
      <button type="submit">Salvar forma de pagamento</button>
      <a class="button secondary" href="{{ route('sales.payment-methods.index', $requiresClinic ? ['clinic_id' => $selectedClinicId] : []) }}">Cancelar</a>
    </div>
  </div>
</div>
