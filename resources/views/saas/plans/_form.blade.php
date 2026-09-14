@php
  $featureValues = isset($plan)
    ? $plan->featureValues->mapWithKeys(fn($item) => [$item->feature->key => (bool)$item->value])->all()
    : [];
  $featureValues = old('features', $featureValues);
@endphp

<div class="panel"><div class="panel-heading"><div><h2>Dados comerciais</h2><p>Valores são informativos nesta fase; não há cobrança automática.</p></div></div><div class="panel-body">
  <div class="form-grid">
    <label class="field"><span>Nome</span><input name="name" value="{{ old('name', $plan->name ?? '') }}" required></label>
    <label class="field"><span>Identificador</span><input name="slug" value="{{ old('slug', $plan->slug ?? '') }}" required placeholder="profissional"></label>
    <label class="field"><span>Preço mensal</span><input name="monthly_price" type="number" min="0" step="0.01" value="{{ old('monthly_price', $plan->monthly_price ?? '') }}"></label>
    <label class="field"><span>Preço anual</span><input name="annual_price" type="number" min="0" step="0.01" value="{{ old('annual_price', $plan->annual_price ?? '') }}"></label>
    <label class="field"><span>Usuários ativos</span><input name="max_users" type="number" min="1" value="{{ old('max_users', $plan->max_users ?? '') }}" placeholder="Em branco = ilimitado"></label>
    <label class="field"><span>Unidades</span><input name="max_units" type="number" min="1" value="{{ old('max_units', $plan->max_units ?? '') }}" placeholder="Em branco = ilimitado"></label>
    <label class="field"><span>Ordem de exibição</span><input name="display_order" type="number" min="0" value="{{ old('display_order', $plan->display_order ?? 0) }}"></label>
    <label class="field"><span>Status</span><select name="active"><option value="1" @selected(old('active', $plan->active ?? true))>Ativo</option><option value="0" @selected(!old('active', $plan->active ?? true))>Inativo</option></select></label>
    <label class="field full"><span>Descrição</span><textarea name="description" rows="3">{{ old('description', $plan->description ?? '') }}</textarea></label>
  </div>
</div></div>

<div class="panel"><div class="panel-heading"><div><h2>Recursos incluídos</h2><p>As permissões do usuário continuam sendo exigidas dentro dos módulos contratados.</p></div></div><div class="panel-body">
  <div class="form-grid">
    @foreach($features as $feature)
      <label class="field"><span>{{ $feature->name }}</span><input type="hidden" name="features[{{ $feature->key }}]" value="0"><label><input type="checkbox" name="features[{{ $feature->key }}]" value="1" @checked((bool)($featureValues[$feature->key] ?? false))> Incluído no plano</label><small class="field-hint">{{ $feature->description }}</small></label>
    @endforeach
  </div>
</div></div>

<div class="form-actions"><button class="button" type="submit">Salvar plano</button><a class="button secondary" href="{{ route('saas.plans.index') }}">Cancelar</a></div>
