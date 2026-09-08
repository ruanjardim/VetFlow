@php
  $selectedClinicId = (int) old('clinic_id', $rule->clinic_id ?? 0);
  $selectedSellerId = (int) old('seller_user_id', $rule->seller_user_id ?? 0);
  $isGlobalActor = auth()->user()?->clinic_id === null;
@endphp

<div class="form-grid">
  @if($isGlobalActor)
    <div class="field">
      <label for="clinic_id">Clinica</label>
      @if($rule)
        <input type="hidden" name="clinic_id" value="{{ $rule->clinic_id }}">
        <div class="readonly-total">{{ $rule->seller?->clinic?->trade_name ?? $rule->seller?->clinic?->corporate_name ?? 'Clinica da regra' }}</div>
        <div class="field-hint">A clinica nao muda depois da criacao para preservar o historico da regra.</div>
      @else
        <select id="clinic_id" name="clinic_id" required>
          <option value="">Selecione</option>
          @foreach($clinics as $clinic)
            <option value="{{ $clinic->id }}" @selected($selectedClinicId === $clinic->id)>
              {{ $clinic->trade_name ?? $clinic->corporate_name }}
            </option>
          @endforeach
        </select>
        <div class="field-hint">A regra sempre pertence a uma clinica especifica.</div>
      @endif
    </div>
  @endif

  <div class="field">
    <label for="seller_user_id">Vendedor</label>
    <select id="seller_user_id" name="seller_user_id" required>
      <option value="">Selecione</option>
      @foreach($sellers as $seller)
        <option value="{{ $seller->id }}" @selected($selectedSellerId === $seller->id)>
          {{ $seller->name }}@if($isGlobalActor) — {{ $seller->clinic?->trade_name ?? $seller->clinic?->corporate_name ?? 'Sem clinica' }}@endif
        </option>
      @endforeach
    </select>
    <div class="field-hint">Vendedores sao colaboradores cadastrados em Usuarios e acessos.</div>
  </div>

  <div class="field">
    <label for="name">Nome da regra</label>
    <input id="name" name="name" value="{{ old('name', $rule->name ?? '') }}" placeholder="Ex.: Comissao de vendas" required>
  </div>

  <div class="field">
    <label for="percentage">Percentual (%)</label>
    <input id="percentage" name="percentage" type="text" inputmode="decimal" value="{{ old('percentage', $rule->percentage ?? '') }}" placeholder="Ex.: 5,00" required>
  </div>

  <div class="field">
    <label for="basis">Base de calculo</label>
    <select id="basis" name="basis" required>
      <option value="sold_total" @selected(old('basis', $rule->basis ?? 'sold_total') === 'sold_total')>Valor liquido vendido</option>
      <option value="gross_profit" @selected(old('basis', $rule->basis ?? 'sold_total') === 'gross_profit')>Margem bruta</option>
    </select>
  </div>

  <div class="field">
    <label for="recognition">Quando reconhecer</label>
    <select id="recognition" name="recognition" required>
      <option value="sale_date" @selected(old('recognition', $rule->recognition ?? 'sale_date') === 'sale_date')>Na data da venda</option>
      <option value="receipt_date" @selected(old('recognition', $rule->recognition ?? 'sale_date') === 'receipt_date')>Na data do recebimento</option>
    </select>
  </div>

  <div class="field">
    <label for="starts_on">Inicio da vigencia</label>
    <input id="starts_on" name="starts_on" type="date" value="{{ old('starts_on', isset($rule) && $rule?->starts_on ? $rule->starts_on->format('Y-m-d') : today()->format('Y-m-d')) }}" required>
  </div>

  <div class="field">
    <label for="ends_on">Fim da vigencia</label>
    <input id="ends_on" name="ends_on" type="date" value="{{ old('ends_on', isset($rule) && $rule?->ends_on ? $rule->ends_on->format('Y-m-d') : '') }}">
  </div>

  <div class="field full">
    <label class="access-role-option">
      <input type="hidden" name="requires_paid" value="0">
      <input type="checkbox" name="requires_paid" value="1" @checked(old('requires_paid', $rule->requires_paid ?? true))>
      <span class="access-role-summary">
        <strong>Exigir venda quitada</strong>
        <small>Quando marcado, a regra so considera uma venda apos sua quitacao total.</small>
      </span>
    </label>
  </div>

  <div class="field full">
    <label class="access-role-option">
      <input type="hidden" name="active" value="0">
      <input type="checkbox" name="active" value="1" @checked(old('active', $rule->active ?? true))>
      <span class="access-role-summary">
        <strong>Regra ativa</strong>
        <small>Uma regra ativa nao pode sobrepor outra do mesmo vendedor no mesmo periodo.</small>
      </span>
    </label>
  </div>

  <div class="field full">
    <label for="notes">Observacoes</label>
    <textarea id="notes" name="notes">{{ old('notes', $rule->notes ?? '') }}</textarea>
  </div>

  <div class="field full">
    <div class="alert-soft">
      <strong>Previa, nao pagamento</strong>
      <span>Esta configuracao apenas estima comissoes. Nenhum lancamento financeiro ou pagamento sera criado automaticamente.</span>
    </div>
  </div>

  <div class="field full">
    <div class="actions">
      <button type="submit">Salvar regra</button>
      <a class="button secondary" href="{{ route('commissions.index') }}">Cancelar</a>
    </div>
  </div>
</div>
