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
    <label for="cnpj">CNPJ</label>
    <input id="cnpj" name="cnpj" value="{{ old('cnpj', $clinic->cnpj ?? '') }}">
  </div>
  <div class="field">
    <label for="email">E-mail</label>
    <input id="email" name="email" type="email" value="{{ old('email', $clinic->email ?? '') }}">
  </div>
  <div class="field">
    <label for="phone">Telefone</label>
    <input id="phone" name="phone" value="{{ old('phone', $clinic->phone ?? '') }}">
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
