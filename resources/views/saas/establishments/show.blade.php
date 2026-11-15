@extends('layouts.admin')
@section('title', 'Assinatura do estabelecimento - VetFlow')
@section('content')
  <header class="topbar">
    <div>
      <h1>{{ $clinic->trade_name ?: $clinic->corporate_name }}</h1>
      <p>{{ $clinic->corporate_name }} · {{ $clinic->documentLabel() }}: {{ $clinic->formattedDocument() }}</p>
    </div>

    <div class="row-actions">
      @can('implementation.manage')
        <form method="POST" action="{{ route('implementation.clinic') }}">
          @csrf
          <input type="hidden" name="clinic_id" value="{{ $clinic->id }}">
          <button class="button" type="submit">Continuar implantação</button>
        </form>
      @endcan

      <a class="button secondary" href="{{ route('saas.establishments.index') }}">Voltar</a>
    </div>
  </header>

  <section class="grid stats">
    <div class="stat"><span>Plano atual</span><strong>{{ $clinic->subscription?->plan?->name ?? 'Sem plano' }}</strong></div>
    <div class="stat"><span>Status</span><strong>{{ ucfirst($clinic->subscription?->status ?? 'pendente') }}</strong></div>
    <div class="stat"><span>Usuários ativos</span><strong>{{ $activeUsers }}</strong></div>
    <div class="stat"><span>Unidades cadastradas</span><strong>{{ 1 + $clinic->children->count() }}</strong></div>
    <div class="stat"><span>Preço atual</span><strong>{{ \App\Modules\Saas\Services\SubscriptionPriceService::format($priceQuote['final_price_cents'] ?? null) }}</strong></div>
  </section>

  @if($clinic->subscription)
    <section class="panel">
      <div class="panel-heading"><div><h2>Condição comercial</h2><p>Preço-base, promoção ativa e histórico sem apagar evidências.</p></div></div>
      <div class="panel-body">
        <div class="grid stats">
          <div class="stat"><span>Preço-base mensal</span><strong>{{ \App\Modules\Saas\Services\SubscriptionPriceService::format($priceQuote['base_price_cents']) }}</strong></div>
          <div class="stat"><span>Desconto vigente</span><strong>{{ $priceQuote['discount_percentage'] ? $priceQuote['discount_percentage'].'%' : 'Sem desconto' }}</strong></div>
          <div class="stat"><span>Valor durante a promoção</span><strong>{{ \App\Modules\Saas\Services\SubscriptionPriceService::format($priceQuote['final_price_cents']) }}</strong></div>
          <div class="stat"><span>Renovação após a promoção</span><strong>{{ \App\Modules\Saas\Services\SubscriptionPriceService::format($priceQuote['base_price_cents']) }}</strong></div>
        </div>
        <form method="POST" action="{{ route('saas.subscriptions.coupon.apply', $clinic) }}" class="form-actions" style="margin-top: 18px;">@csrf
          <label class="field" style="min-width: 280px;"><span>Aplicar cupom</span><input name="coupon_code" maxlength="64" placeholder="CÓDIGO" required></label>
          <button class="button" type="submit">Aplicar e congelar preço</button>
        </form>
        <div class="table-wrap" style="margin-top: 18px;"><table><thead><tr><th>Cupom</th><th>Desconto</th><th>Preço</th><th>Período</th><th>Situação</th><th>Ação</th></tr></thead><tbody>
          @forelse($promotions as $promotion)<tr><td><strong>{{ $promotion->coupon_code }}</strong></td><td>{{ $promotion->percentage }}%</td><td>{{ \App\Modules\Saas\Services\SubscriptionPriceService::format($promotion->base_price_cents) }} → {{ \App\Modules\Saas\Services\SubscriptionPriceService::format($promotion->final_price_cents) }}</td><td>{{ $promotion->starts_on?->format('d/m/Y') }} a {{ $promotion->ends_on?->format('d/m/Y') }}</td><td><span class="badge {{ $promotion->cancelled_at ? 'warning' : ($promotion->ends_on?->isPast() ? 'muted-badge' : 'success') }}">{{ $promotion->cancelled_at ? 'Cancelada' : ($promotion->ends_on?->isPast() ? 'Encerrada' : 'Registrada') }}</span></td><td>
            @if(!$promotion->cancelled_at && !$promotion->ends_on?->isPast())<form method="POST" action="{{ route('saas.subscriptions.promotion.cancel', [$clinic, $promotion]) }}">@csrf @method('DELETE')<input name="reason" maxlength="500" placeholder="Motivo obrigatório" required><button class="button secondary" type="submit">Cancelar</button></form>@else<span class="muted">{{ $promotion->cancellation_reason }}</span>@endif
          </td></tr>@empty<tr><td colspan="6" class="muted">Nenhuma promoção registrada.</td></tr>@endforelse
        </tbody></table></div>
        <p class="muted">Esta tela documenta a condição comercial; a cobrança financeira permanece externa/administrativa até a futura integração de um gateway.</p>
      </div>
    </section>
  @endif

  <form method="POST" action="{{ route('saas.subscriptions.update', $clinic) }}">
    @csrf @method('PUT')
    <section class="panel"><div class="panel-heading"><div><h2>Assinatura</h2><p>Plano, vigência e situação de acesso do estabelecimento.</p></div></div><div class="panel-body"><div class="form-grid">
      <label class="field"><span>Plano</span><select name="plan_id" required>@foreach($plans as $plan)<option value="{{ $plan->id }}" @selected((int)old('plan_id', $clinic->subscription?->plan_id) === $plan->id)>{{ $plan->name }}@if($plan->internal) (interno)@endif</option>@endforeach</select></label>
      <label class="field"><span>Status</span><select name="status" required>@foreach(\App\Modules\Saas\Models\Subscription::STATUSES as $status)<option value="{{ $status }}" @selected(old('status', $clinic->subscription?->status ?? 'active') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></label>
      <label class="field"><span>Início</span><input type="date" name="starts_at" value="{{ old('starts_at', $clinic->subscription?->starts_at?->toDateString() ?? now()->toDateString()) }}" required></label>
      <label class="field"><span>Fim</span><input type="date" name="ends_at" value="{{ old('ends_at', $clinic->subscription?->ends_at?->toDateString()) }}"></label>
      <label class="field"><span>Fim do trial</span><input type="date" name="trial_ends_at" value="{{ old('trial_ends_at', $clinic->subscription?->trial_ends_at?->toDateString()) }}"></label>
      <label class="field"><span>Próxima renovação</span><input type="date" name="renews_at" value="{{ old('renews_at', $clinic->subscription?->renews_at?->toDateString()) }}"></label>
    </div></div></section>

    <section class="panel"><div class="panel-heading"><div><h2>Exceções desta assinatura</h2><p>Deixe em “Herdar do plano” para acompanhar a composição comercial.</p></div></div><div class="panel-body"><div class="form-grid">
      @foreach($features as $feature)
        @php($override = $clinic->subscription?->overrides->firstWhere('feature_id', $feature->id))
        @php($value = old('overrides.'.$feature->key, $override?->value))
        <label class="field"><span>{{ $feature->name }}</span>
          @if($feature->type === 'boolean')
            <select name="overrides[{{ $feature->key }}]"><option value="">Herdar do plano</option><option value="1" @selected((string)$value === '1')>Habilitado</option><option value="0" @selected($override && !$value)>Desabilitado</option></select>
          @else
            <input type="number" min="0" name="overrides[{{ $feature->key }}]" value="{{ $value }}" placeholder="Herdar do plano">
          @endif
          <small class="field-hint">{{ $feature->description }}</small>
        </label>
      @endforeach
    </div></div></section>
    <div class="form-actions"><button class="button" type="submit">Salvar assinatura</button></div>
  </form>
@endsection
