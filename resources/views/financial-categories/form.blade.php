<div class="form-grid">
  @if($requiresClinic)
    <div class="field full">
      <label for="clinic_id">Estabelecimento</label>
      @if($category)
        <input type="hidden" name="clinic_id" value="{{ $selectedClinicId }}">
        <input value="{{ $clinics->firstWhere('id', $selectedClinicId)?->trade_name ?: $clinics->firstWhere('id', $selectedClinicId)?->corporate_name }}" disabled>
      @else
        <select id="clinic_id" name="clinic_id" required>
          @foreach($clinics as $clinic)
            <option value="{{ $clinic->id }}" @selected((int) old('clinic_id', $selectedClinicId) === $clinic->id)>{{ $clinic->trade_name ?: $clinic->corporate_name }}</option>
          @endforeach
        </select>
      @endif
    </div>
  @endif

  <div class="field">
    <label for="name">Nome</label>
    <input id="name" name="name" value="{{ old('name', $category?->name) }}" maxlength="100" placeholder="Ex.: Aluguel" required autofocus>
  </div>

  <div class="field">
    <label for="type">Tipo</label>
    @if($category)
      <input type="hidden" name="type" value="{{ $category->type }}">
      <input value="{{ $category->typeLabel() }}" disabled>
      <small class="muted">O tipo fica bloqueado para preservar o histórico.</small>
    @else
      <select id="type" name="type" required>
        @foreach($typeLabels as $value => $label)
          <option value="{{ $value }}" @selected(old('type', 'expense') === $value)>{{ $label }}</option>
        @endforeach
      </select>
    @endif
  </div>

  <div class="field">
    <label for="active">Status</label>
    <select id="active" name="active" required>
      <option value="1" @selected((bool) old('active', $category?->active ?? true))>Ativa</option>
      <option value="0" @selected(! (bool) old('active', $category?->active ?? true))>Inativa</option>
    </select>
    <small class="muted">Inativas saem da seleção manual, mas permanecem no histórico e nas classificações automáticas do sistema.</small>
  </div>

  <div class="field">
    <label for="sort_order">Ordem</label>
    <input id="sort_order" name="sort_order" type="number" min="0" max="9999" value="{{ old('sort_order', $category?->sort_order ?? 0) }}">
  </div>

  <div class="field full">
    <div class="actions">
      <button type="submit">Salvar categoria</button>
      <a class="button secondary" href="{{ route('financial-categories.index', $requiresClinic ? ['clinic_id' => $selectedClinicId] : []) }}">Cancelar</a>
    </div>
  </div>
</div>
