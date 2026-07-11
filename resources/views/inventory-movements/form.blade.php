<div class="form-grid">
  <div
    class="field full"
    data-inventory-scanner
    data-inventory-lookup-url="{{ route('inventory-movements.product-lookup', ['gtin' => '__GTIN__']) }}"
    data-inventory-lookup-auto="{{ request()->has('scan') ? '1' : '0' }}"
    data-product-create-url="{{ route('products.create') }}?gtin=__GTIN__&from=inventory"
  >
    <label for="inventory_barcode">Leitor de codigo de barras</label>
    <div class="sale-scan-row">
      <input
        id="inventory_barcode"
        type="text"
        inputmode="numeric"
        autocomplete="off"
        placeholder="Escaneie ou digite o EAN/GTIN"
        value="{{ request('scan', '') }}"
        data-inventory-barcode-input
      >
      <button type="button" class="secondary" data-inventory-barcode-button>Buscar</button>
    </div>
    <div class="lookup-status muted" data-inventory-lookup-status></div>
    <div class="sale-scan-actions">
      <a class="button secondary" href="{{ route('products.create') }}" target="_blank" rel="noopener" data-inventory-create-product-link hidden>Cadastrar produto agora</a>
    </div>
  </div>

  <div class="field">
    <label for="product_id">Produto</label>
    <select id="product_id" name="product_id" required data-inventory-product-select>
      <option value="">Selecione</option>
      @foreach($products as $product)
        <option
          value="{{ $product->id }}"
          data-description="{{ $product->name }}"
          data-cost="{{ $product->cost_price }}"
          data-stock="{{ $product->stock_quantity }}"
          data-unit="{{ $product->unit }}"
          @selected((int) old('product_id', $movement->product_id ?? request('product_id', 0)) === $product->id)
        >
          {{ $product->name }} - estoque {{ number_format((float) $product->stock_quantity, 3, ',', '.') }} {{ $product->unit }}
        </option>
      @endforeach
    </select>
  </div>
  <div class="field">
    <label for="type">Tipo</label>
    <select id="type" name="type" required>
      <option value="entry" @selected(old('type', $movement->type ?? request('type', 'entry')) === 'entry')>Entrada</option>
      <option value="exit" @selected(old('type', $movement->type ?? request('type', 'entry')) === 'exit')>Saida</option>
      <option value="adjustment" @selected(old('type', $movement->type ?? request('type', 'entry')) === 'adjustment')>Ajuste positivo</option>
      <option value="lot_assignment" @selected(old('type', $movement->type ?? request('type', 'entry')) === 'lot_assignment')>Lote do estoque atual</option>
    </select>
  </div>
  <div class="field">
    <label for="quantity">Quantidade</label>
    <input id="quantity" name="quantity" type="number" step="0.001" min="0.001" value="{{ old('quantity', $movement->quantity ?? request('quantity', '')) }}" required>
  </div>
  <div class="field">
    <label for="unit_cost">Custo unitario</label>
    <input id="unit_cost" name="unit_cost" type="number" step="0.01" min="0" value="{{ old('unit_cost', $movement->unit_cost ?? '') }}">
  </div>
  <div class="field">
    <label for="lot_number">Lote</label>
    <input id="lot_number" name="lot_number" value="{{ old('lot_number', $movement->lot_number ?? '') }}" placeholder="Ex.: L2407A">
  </div>
  <div class="field">
    <label for="expires_at">Validade</label>
    <input id="expires_at" name="expires_at" type="date" value="{{ old('expires_at', isset($movement) && $movement?->expires_at ? $movement->expires_at->format('Y-m-d') : '') }}">
  </div>
  <div class="field">
    <label for="occurred_at">Data</label>
    <input id="occurred_at" name="occurred_at" type="datetime-local" value="{{ old('occurred_at', isset($movement) && $movement?->occurred_at ? $movement->occurred_at->format('Y-m-d\TH:i') : now()->format('Y-m-d\TH:i')) }}">
  </div>
  <div class="field">
    <label for="clinic_id">Clinica ID</label>
    <input id="clinic_id" name="clinic_id" type="number" value="{{ old('clinic_id', $movement->clinic_id ?? '') }}">
  </div>
  <div class="field full">
    <label for="reason">Motivo</label>
    <input id="reason" name="reason" value="{{ old('reason', $movement->reason ?? request('reason', '')) }}">
  </div>
  <div class="field full">
    <label for="notes">Observacoes</label>
    <textarea id="notes" name="notes">{{ old('notes', $movement->notes ?? '') }}</textarea>
  </div>
  <div class="field full">
    <div class="actions">
      <button type="submit">Salvar</button>
      <a class="button secondary" href="{{ route('inventory-movements.index') }}">Cancelar</a>
    </div>
  </div>
</div>
