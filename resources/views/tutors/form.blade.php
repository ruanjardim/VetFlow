<div class="form-section">
  <div class="panel-heading"><div><h2>Identificação</h2><p>Dados da pessoa responsável pelo paciente.</p></div></div>
  <div class="form-grid">
    <div class="field full"><label for="name">Nome completo</label><input id="name" name="name" value="{{ old('name', $tutor->name ?? '') }}" autocomplete="name" required></div>
    <div class="field"><label for="cpf">CPF</label><input id="cpf" name="cpf" value="{{ old('cpf', $tutor->cpf ?? '') }}" inputmode="numeric" maxlength="14" autocomplete="off" data-tutor-cpf><span class="lookup-status" data-tutor-cpf-status></span></div>
    <div class="field"><label for="rg">RG</label><input id="rg" name="rg" value="{{ old('rg', $tutor->rg ?? '') }}"></div>
    <div class="field"><label for="birth_date">Data de nascimento</label><input id="birth_date" name="birth_date" type="date" value="{{ old('birth_date', optional($tutor?->birth_date)->format('Y-m-d')) }}"></div>
    <div class="field"><label for="gender">Gênero</label><input id="gender" name="gender" value="{{ old('gender', $tutor->gender ?? '') }}" maxlength="50"></div>
  </div>
</div>
<div class="form-section">
  <div class="panel-heading"><div><h2>Contatos</h2><p>Use o telefone principal para os avisos da clínica.</p></div></div>
  <div class="form-grid">
    <div class="field"><label for="phone">Telefone principal</label><input id="phone" name="phone" value="{{ old('phone', $tutor->phone ?? '') }}" inputmode="tel" autocomplete="tel" data-tutor-phone required></div>
    <div class="field"><label for="phone_secondary">Telefone secundário</label><input id="phone_secondary" name="phone_secondary" value="{{ old('phone_secondary', $tutor->phone_secondary ?? '') }}" inputmode="tel" data-tutor-phone></div>
    <div class="field full"><label for="email">E-mail</label><input id="email" name="email" type="email" value="{{ old('email', $tutor->email ?? '') }}" autocomplete="email"></div>
  </div>
</div>
<div class="form-section">
  <div class="panel-heading"><div><h2>Endereço</h2><p>Informe o CEP para buscar o endereço; revise número e complemento antes de salvar.</p></div></div>
  <div class="form-grid">
    <div class="field"><label for="zip_code">CEP</label><input id="zip_code" name="zip_code" value="{{ old('zip_code', $tutor->zip_code ?? '') }}" inputmode="numeric" maxlength="9" autocomplete="postal-code" data-tutor-cep><span class="lookup-status" data-tutor-cep-status></span></div>
    <div class="field"><label for="street">Endereço</label><input id="street" name="street" value="{{ old('street', $tutor->street ?? '') }}" autocomplete="street-address"></div>
    <div class="field"><label for="number">Número</label><input id="number" name="number" value="{{ old('number', $tutor->number ?? '') }}"></div>
    <div class="field"><label for="complement">Complemento</label><input id="complement" name="complement" value="{{ old('complement', $tutor->complement ?? '') }}"></div>
    <div class="field"><label for="district">Bairro</label><input id="district" name="district" value="{{ old('district', $tutor->district ?? '') }}"></div>
    <div class="field"><label for="city">Cidade</label><input id="city" name="city" value="{{ old('city', $tutor->city ?? '') }}"></div>
    <div class="field"><label for="state">Estado</label><input id="state" name="state" value="{{ old('state', $tutor->state ?? '') }}" maxlength="2" placeholder="UF"></div>
  </div>
</div>
<div class="form-section"><div class="form-grid">
  <div class="field"><label for="active">Status</label><select id="active" name="active"><option value="1" @selected(old('active', $tutor->active ?? true))>Ativo</option><option value="0" @selected(! old('active', $tutor->active ?? true))>Inativo</option></select></div>
  <div class="field full"><label for="notes">Observações</label><textarea id="notes" name="notes">{{ old('notes', $tutor->notes ?? '') }}</textarea></div>
  <div class="field full"><div class="actions"><button type="submit">Salvar responsável</button><a class="button secondary" href="{{ route('tutores.index') }}">Cancelar</a></div></div>
</div></div>
