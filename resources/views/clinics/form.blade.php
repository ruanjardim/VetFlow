<div class="form-grid">
  <div class="field">
    <label for="corporate_name">Razao social</label>
    <input id="corporate_name" name="corporate_name" value="{{ old('corporate_name', $clinic->corporate_name ?? '') }}">
  </div>
  <div class="field">
    <label for="trade_name">Nome fantasia</label>
    <input id="trade_name" name="trade_name" value="{{ old('trade_name', $clinic->trade_name ?? '') }}">
  </div>
  <div class="field">
    <label for="document_type">Tipo de documento</label>
    <select id="document_type" name="document_type" data-document-type>
      <option value="cnpj" @selected(old('document_type', $clinic->document_type ?? 'cnpj') === 'cnpj')>CNPJ — pessoa jurídica</option>
      <option value="cpf" @selected(old('document_type', $clinic->document_type ?? 'cnpj') === 'cpf')>CPF — pessoa física</option>
    </select>
  </div>
  <div class="field">
    <label for="cnpj" data-document-label>{{ old('document_type', $clinic->document_type ?? 'cnpj') === 'cpf' ? 'CPF' : 'CNPJ' }}</label>
    <input id="cnpj" name="cnpj" value="{{ old('cnpj', $clinic->cnpj ?? '') }}" inputmode="numeric" autocomplete="off" data-document-number required>
    <small class="field-hint" data-document-hint></small>
  </div>
  <div class="field">
    <label for="email">E-mail</label>
    <input id="email" name="email" type="email" value="{{ old('email', $clinic->email ?? '') }}">
  </div>
  <div class="field">
    <label for="phone">Telefone</label>
    <input id="phone" name="phone" value="{{ old('phone', $clinic->phone ?? '') }}" inputmode="tel" maxlength="15" data-phone-mask>
  </div>
  <div class="field">
    <label for="city">Cidade</label>
    <input id="city" name="city" value="{{ old('city', $clinic->city ?? '') }}">
  </div>
  <div class="field">
    <label for="state">Estado</label>
    <input id="state" name="state" value="{{ old('state', $clinic->state ?? '') }}">
  </div>
  <div class="field">
    <label for="active">Status</label>
    <select id="active" name="active">
      <option value="1" @selected(old('active', $clinic->active ?? true))>Ativa</option>
      <option value="0" @selected(! old('active', $clinic->active ?? true))>Inativa</option>
    </select>
  </div>
  @include('clinics.branding-fields')
  <div class="field full">
    <div class="actions">
      <button type="submit">Salvar</button>
      <a class="button secondary" href="{{ route('clinics.index') }}">Cancelar</a>
    </div>
  </div>
</div>
