@php($editing = isset($coupon))
<div class="form-grid">
  <label class="field"><span>Código</span><input name="code" maxlength="64" value="{{ old('code', $coupon->code ?? '') }}" placeholder="LANCAMENTO20" required @readonly($editing && ($coupon->promotions_count ?? 0) > 0)><small class="field-hint">Letras, números, hífen e sublinhado. O sistema salva em maiúsculas.</small></label>
  <label class="field"><span>Desconto</span><select name="percentage" required>@foreach(\App\Modules\Saas\Models\Coupon::PERCENTAGES as $percentage)<option value="{{ $percentage }}" @selected((int)old('percentage', $coupon->percentage ?? 10) === $percentage)>{{ $percentage }}%</option>@endforeach</select></label>
  <label class="field"><span>Início da validade</span><input type="datetime-local" name="valid_from" value="{{ old('valid_from', isset($coupon) ? $coupon->valid_from?->format('Y-m-d\TH:i') : now()->format('Y-m-d\TH:i')) }}" required></label>
  <label class="field"><span>Fim da validade</span><input type="datetime-local" name="valid_until" value="{{ old('valid_until', isset($coupon) ? $coupon->valid_until?->format('Y-m-d\TH:i') : now()->addMonths(2)->format('Y-m-d\TH:i')) }}" required></label>
  <label class="field"><span>Duração para cada cliente</span><input type="number" min="1" max="24" name="duration_months" value="{{ old('duration_months', $coupon->duration_months ?? 1) }}" required><small class="field-hint">Meses consecutivos com desconto após a aplicação.</small></label>
  <label class="field"><span>Limite de utilizações</span><input type="number" min="1" name="max_redemptions" value="{{ old('max_redemptions', $coupon->max_redemptions ?? '') }}" placeholder="Sem limite"></label>
</div>
<fieldset class="panel" style="margin-top: 18px;"><div class="panel-heading"><div><h2>Planos participantes</h2><p>Sem seleção significa válido para todos os planos comerciais.</p></div></div><div class="panel-body form-grid">
  @foreach($plans as $plan)<label class="field"><span><input type="checkbox" name="plan_ids[]" value="{{ $plan->id }}" @checked(in_array($plan->id, array_map('intval', old('plan_ids', isset($coupon) ? $coupon->plans->pluck('id')->all() : [])), true))> {{ $plan->name }} — R$ {{ number_format((float)$plan->monthly_price, 2, ',', '.') }}/mês</span></label>@endforeach
</div></fieldset>
<div class="form-grid" style="margin-top: 18px;">
  <label class="field"><span>Situação</span><select name="active"><option value="1" @selected((string)old('active', isset($coupon) ? (int)$coupon->active : 1) === '1')>Ativo</option><option value="0" @selected((string)old('active', isset($coupon) ? (int)$coupon->active : 1) === '0')>Inativo</option></select></label>
  <label class="field"><span>Observações internas</span><textarea name="notes" rows="4">{{ old('notes', $coupon->notes ?? '') }}</textarea></label>
</div>
