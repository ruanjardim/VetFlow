@php
  $rows = old('items');
  if ($rows === null && $package) {
    $rows = $package->items->map(fn ($item) => ['petshop_service_id' => $item->petshop_service_id, 'quantity' => $item->quantity])->all();
  }
  $rows = array_pad($rows ?: [], 5, []);
@endphp

<div class="form-grid">
  @if(auth()->user()?->clinic_id === null)
    <div class="field">
      <label for="clinic_id">Clínica</label>
      <select id="clinic_id" name="clinic_id" required>
        <option value="">Selecione</option>
        @foreach($clinics as $clinic)
          <option value="{{ $clinic->id }}" @selected((int) old('clinic_id', $package->clinic_id ?? 0) === $clinic->id)>{{ $clinic->trade_name ?? $clinic->corporate_name }}</option>
        @endforeach
      </select>
    </div>
  @endif
  <div class="field">
    <label for="name">Nome do pacote</label>
    <input id="name" name="name" value="{{ old('name', $package->name ?? '') }}" placeholder="Ex.: Clubinho M" required>
  </div>
  <div class="field">
    <label for="price">Preço (R$)</label>
    <input id="price" name="price" type="number" step="0.01" min="0" value="{{ old('price', $package->price ?? '') }}" required>
  </div>
  <div class="field">
    <label for="validity_days">Validade (dias)</label>
    <input id="validity_days" name="validity_days" type="number" min="1" max="730" value="{{ old('validity_days', $package->validity_days ?? 30) }}" placeholder="Sem validade">
  </div>
  <div class="field">
    <label for="active">Status</label>
    <select id="active" name="active">
      <option value="1" @selected((string) old('active', $package ? (int) $package->active : 1) === '1')>Ativo</option>
      <option value="0" @selected((string) old('active', $package ? (int) $package->active : 1) === '0')>Inativo</option>
    </select>
  </div>
  <div class="field full">
    <label for="description">Descrição</label>
    <textarea id="description" name="description">{{ old('description', $package->description ?? '') }}</textarea>
  </div>
  <div class="field full">
    <label>Serviços incluídos</label>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Serviço</th><th>Quantidade</th></tr></thead>
        <tbody>
          @foreach($rows as $index => $row)
            <tr>
              <td>
                <select name="items[{{ $index }}][petshop_service_id]">
                  <option value="">Selecione</option>
                  @foreach($services as $service)
                    <option value="{{ $service->id }}" @selected((int) ($row['petshop_service_id'] ?? 0) === $service->id)>{{ $service->name }}</option>
                  @endforeach
                </select>
              </td>
              <td><input name="items[{{ $index }}][quantity]" type="number" min="1" max="100" value="{{ $row['quantity'] ?? '' }}"></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <small class="muted">O valor de cada sessão (para comissão) é o preço dividido pelo total de sessões.</small>
  </div>
  <div class="field full">
    <div class="actions">
      <button type="submit">Salvar pacote</button>
      <a class="button secondary" href="{{ route('petshop-packages.index') }}">Cancelar</a>
    </div>
  </div>
</div>
