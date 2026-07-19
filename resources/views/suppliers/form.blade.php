<div class="form-grid">
  <div class="field">
    <label for="name">Nome</label>
    <input id="name" name="name" value="{{ old('name', $supplier->name ?? '') }}" required>
  </div>
  <div class="field">
    <label for="document">CNPJ / CPF</label>
    <input id="document" name="document" value="{{ old('document', $supplier->document ?? '') }}">
  </div>
  <div class="field">
    <label for="contact_name">Contato</label>
    <input id="contact_name" name="contact_name" value="{{ old('contact_name', $supplier->contact_name ?? '') }}">
  </div>
  <div class="field">
    <label for="email">E-mail</label>
    <input id="email" name="email" type="email" value="{{ old('email', $supplier->email ?? '') }}">
  </div>
  <div class="field">
    <label for="phone">Telefone</label>
    <input id="phone" name="phone" value="{{ old('phone', $supplier->phone ?? '') }}">
  </div>
  <div class="field">
    <label for="whatsapp">WhatsApp</label>
    <input id="whatsapp" name="whatsapp" value="{{ old('whatsapp', $supplier->whatsapp ?? '') }}">
  </div>
  <div class="field">
    <label for="city">Cidade</label>
    <input id="city" name="city" value="{{ old('city', $supplier->city ?? '') }}">
  </div>
  <div class="field">
    <label for="state">UF</label>
    <input id="state" name="state" maxlength="2" value="{{ old('state', $supplier->state ?? '') }}">
  </div>
  <div class="field">
    <label for="active">Status</label>
    <select id="active" name="active">
      <option value="1" @selected(old('active', $supplier->active ?? true))>Ativo</option>
      <option value="0" @selected(! old('active', $supplier->active ?? true))>Inativo</option>
    </select>
  </div>
  <div class="field full">
    <label for="notes">Observacoes</label>
    <textarea id="notes" name="notes">{{ old('notes', $supplier->notes ?? '') }}</textarea>
  </div>
  <div class="field full">
    <div class="actions">
      <button type="submit">Salvar</button>
      <a class="button secondary" href="{{ route('suppliers.index') }}">Cancelar</a>
    </div>
  </div>
</div>
