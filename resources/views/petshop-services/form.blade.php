<div class="form-grid">
  <div class="field">
    <label for="name">Nome</label>
    <input id="name" name="name" value="{{ old('name', $service->name ?? '') }}" required>
  </div>
  <div class="field">
    <label for="category">Categoria</label>
    <input id="category" name="category" value="{{ old('category', $service->category ?? '') }}" placeholder="Banho, Tosa, Pacote">
  </div>
  <div class="field">
    <label for="base_price">Preco base</label>
    <input id="base_price" name="base_price" type="number" step="0.01" min="0" value="{{ old('base_price', $service->base_price ?? 0) }}">
  </div>
  <div class="field">
    <label for="duration_minutes">Duracao estimada</label>
    <input id="duration_minutes" name="duration_minutes" type="number" min="1" value="{{ old('duration_minutes', $service->duration_minutes ?? '') }}">
  </div>
  <div class="field">
    <label for="small_price">Porte pequeno</label>
    <input id="small_price" name="small_price" type="number" step="0.01" min="0" value="{{ old('small_price', $service->small_price ?? '') }}">
  </div>
  <div class="field">
    <label for="medium_price">Porte medio</label>
    <input id="medium_price" name="medium_price" type="number" step="0.01" min="0" value="{{ old('medium_price', $service->medium_price ?? '') }}">
  </div>
  <div class="field">
    <label for="large_price">Porte grande</label>
    <input id="large_price" name="large_price" type="number" step="0.01" min="0" value="{{ old('large_price', $service->large_price ?? '') }}">
  </div>
  <div class="field">
    <label for="giant_price">Porte gigante</label>
    <input id="giant_price" name="giant_price" type="number" step="0.01" min="0" value="{{ old('giant_price', $service->giant_price ?? '') }}">
  </div>
  <div class="field">
    <label for="requires_appointment">Agenda</label>
    <select id="requires_appointment" name="requires_appointment">
      <option value="1" @selected(old('requires_appointment', $service->requires_appointment ?? true))>Precisa agendar</option>
      <option value="0" @selected(! old('requires_appointment', $service->requires_appointment ?? true))>Atendimento de balcao</option>
    </select>
  </div>
  <div class="field">
    <label for="active">Status</label>
    <select id="active" name="active">
      <option value="1" @selected(old('active', $service->active ?? true))>Ativo</option>
      <option value="0" @selected(! old('active', $service->active ?? true))>Inativo</option>
    </select>
  </div>
  <div class="field full">
    <label for="description">Descricao</label>
    <textarea id="description" name="description">{{ old('description', $service->description ?? '') }}</textarea>
  </div>
  <div class="field full">
    <div class="actions">
      <button type="submit">Salvar</button>
      <a class="button secondary" href="{{ route('petshop-services.index') }}">Cancelar</a>
    </div>
  </div>
</div>
