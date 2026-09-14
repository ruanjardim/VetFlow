@extends('layouts.admin')
@section('title', 'Implantar novo cliente - VetFlow')
@section('content')
  <header class="topbar"><div><h1>Implantar novo cliente</h1><p>O estabelecimento, a assinatura e o administrador são criados em uma única transação.</p></div></header>

  @if($plans->isEmpty())<div class="alert warning">Cadastre e ative ao menos um plano comercial antes de iniciar a implantação.</div>@endif

  <form method="POST" action="{{ route('saas.onboarding.store') }}" data-document-form>@csrf
    <section class="panel"><div class="panel-heading"><div><h2>1. Estabelecimento</h2><p>Identificação principal do novo tenant.</p></div></div><div class="panel-body"><div class="form-grid">
      <label class="field"><span>Nome ou razão social</span><input name="clinic[corporate_name]" value="{{ old('clinic.corporate_name') }}" required></label>
      <label class="field"><span>Nome de exibição ou fantasia</span><input name="clinic[trade_name]" value="{{ old('clinic.trade_name') }}" required></label>
      <label class="field"><span>Tipo de negócio</span><select name="clinic[business_type]" required><option value="">Selecione</option>@foreach($businessTypes as $key => $label)<option value="{{ $key }}" @selected(old('clinic.business_type') === $key)>{{ $label }}</option>@endforeach</select></label>
      <label class="field"><span>Tipo de documento</span><select name="clinic[document_type]" data-document-type required><option value="cnpj" @selected(old('clinic.document_type', 'cnpj') === 'cnpj')>CNPJ — pessoa jurídica</option><option value="cpf" @selected(old('clinic.document_type') === 'cpf')>CPF — pessoa física</option></select></label>
      <label class="field"><span data-document-label>{{ old('clinic.document_type', 'cnpj') === 'cpf' ? 'CPF' : 'CNPJ' }}</span><input name="clinic[cnpj]" value="{{ old('clinic.cnpj') }}" inputmode="numeric" autocomplete="off" data-document-number required><small class="field-hint" data-document-hint></small></label>
      <label class="field"><span>E-mail comercial</span><input type="email" name="clinic[email]" value="{{ old('clinic.email') }}"></label>
      <label class="field"><span>Telefone</span><input name="clinic[phone]" value="{{ old('clinic.phone') }}" inputmode="tel" autocomplete="tel" maxlength="15" data-phone-mask></label>
      <label class="field"><span>WhatsApp</span><input name="clinic[whatsapp]" value="{{ old('clinic.whatsapp') }}" inputmode="tel" maxlength="15" data-phone-mask></label>
      <label class="field"><span>Fuso horário</span><input name="clinic[timezone]" value="{{ old('clinic.timezone', 'America/Sao_Paulo') }}"></label>
    </div></div></section>

    <section class="panel"><div class="panel-heading"><div><h2>2. Plano inicial</h2><p>Selecione a oferta comercial de partida.</p></div></div><div class="panel-body"><div class="form-grid">
      @foreach($plans as $plan)
        <label class="field"><span>{{ $plan->name }}</span><label><input type="radio" name="plan_id" value="{{ $plan->id }}" @checked((int)old('plan_id', $plans->first()?->id) === $plan->id)> {{ $plan->max_users ? $plan->max_users.' usuários' : 'Usuários ilimitados' }}</label><small class="field-hint">{{ $plan->description }}</small></label>
      @endforeach
    </div></div></section>

    <section class="panel"><div class="panel-heading"><div><h2>3. Exceções opcionais</h2><p>Use apenas quando o contrato deste cliente diferir do plano.</p></div></div><div class="panel-body"><div class="form-grid">
      @foreach($features as $feature)<label class="field"><span>{{ $feature->name }}</span>
        @if($feature->type === 'boolean')<select name="overrides[{{ $feature->key }}]"><option value="">Herdar do plano</option><option value="1" @selected(old('overrides.'.$feature->key) === '1')>Habilitado</option><option value="0" @selected(old('overrides.'.$feature->key) === '0')>Desabilitado</option></select>
        @else<input type="number" min="0" name="overrides[{{ $feature->key }}]" value="{{ old('overrides.'.$feature->key) }}" placeholder="Herdar do plano">@endif
      </label>@endforeach
    </div></div></section>

    <section class="panel"><div class="panel-heading"><div><h2>4. Administrador inicial</h2><p>Primeiro acesso do cliente, vinculado ao perfil Administrador.</p></div></div><div class="panel-body"><div class="form-grid">
      <label class="field"><span>Nome</span><input name="admin[name]" value="{{ old('admin.name') }}" required></label>
      <label class="field"><span>E-mail de acesso</span><input type="email" name="admin[email]" value="{{ old('admin.email') }}" required></label>
      <label class="field"><span>Telefone</span><input name="admin[phone]" value="{{ old('admin.phone') }}" inputmode="tel" autocomplete="tel" maxlength="15" data-phone-mask></label>
      <label class="field"><span>Senha</span><input type="password" name="admin[password]" required></label>
      <label class="field"><span>Confirmar senha</span><input type="password" name="admin[password_confirmation]" required></label>
    </div></div></section>
    <div class="form-actions"><button class="button" type="submit" @disabled($plans->isEmpty())>Implantar cliente</button><a class="button secondary" href="{{ route('saas.dashboard') }}">Cancelar</a></div>
  </form>
@endsection
