<div class="form-grid">
  <div class="field">
    <label for="name">Nome</label>
    <input id="name" name="name" value="{{ old('name', $tutor->name ?? '') }}" required>
  </div>
  <div class="field">
    <label for="cpf">CPF</label>
    <input id="cpf" name="cpf" value="{{ old('cpf', $tutor->cpf ?? '') }}">
  </div>
  <div class="field">
    <label for="phone">Telefone principal</label>
    <input id="phone" name="phone" value="{{ old('phone', $tutor->phone ?? '') }}" required>
  </div>
  <div class="field">
    <label for="phone_secondary">Telefone secundario</label>
    <input id="phone_secondary" name="phone_secondary" value="{{ old('phone_secondary', $tutor->phone_secondary ?? '') }}">
  </div>
  <div class="field">
    <label for="email">E-mail</label>
    <input id="email" name="email" type="email" value="{{ old('email', $tutor->email ?? '') }}">
  </div>
  <div class="field">
    <label for="city">Cidade</label>
    <input id="city" name="city" value="{{ old('city', $tutor->city ?? '') }}">
  </div>
  <div class="field">
    <label for="state">Estado</label>
    <input id="state" name="state" value="{{ old('state', $tutor->state ?? '') }}">
  </div>
  <div class="field">
    <label for="active">Status</label>
    <select id="active" name="active">
      <option value="1" @selected(old('active', $tutor->active ?? true))>Ativo</option>
      <option value="0" @selected(! old('active', $tutor->active ?? true))>Inativo</option>
    </select>
  </div>
  <div class="field full">
    <label for="notes">Observacoes</label>
    <textarea id="notes" name="notes">{{ old('notes', $tutor->notes ?? '') }}</textarea>
  </div>
  <div class="field full">
    <div class="actions">
      <button type="submit">Salvar</button>
      <a class="button secondary" href="{{ route('tutores.index') }}">Cancelar</a>
    </div>
  </div>
</div>
