@php
  $lookupImageUrl = isset($product) && $product?->image_path
    ? route('products.lookup-image', ['filename' => basename($product->image_path)])
    : null;
  $prefillGtin = old('gtin', $product->gtin ?? $product->barcode ?? request('gtin', ''));
  $cancelRoute = match (request('from')) {
    'sales' => route('sales.create'),
    'inventory' => route('inventory-movements.create'),
    default => route('products.index'),
  };
@endphp

<div class="form-grid product-form">
  <div class="field">
    <label for="gtin">EAN/GTIN</label>
    <input
      id="gtin"
      name="gtin"
      inputmode="numeric"
      autocomplete="off"
      value="{{ $prefillGtin }}"
      data-product-lookup-url="{{ route('products.lookup', ['gtin' => '__GTIN__']) }}"
      data-product-lookup-auto="{{ request()->has('gtin') ? '1' : '0' }}"
    >
    <div class="lookup-status muted" data-product-lookup-status></div>
  </div>
  <input id="barcode" name="barcode" type="hidden" value="{{ old('barcode', $product->barcode ?? $prefillGtin) }}">
  <input id="image_path" name="image_path" type="hidden" value="{{ old('image_path', $product->image_path ?? '') }}">
  <input id="lookup_source" name="lookup_source" type="hidden" value="{{ old('lookup_source', $product->lookup_source ?? '') }}">
  <input id="lookup_metadata" name="lookup_metadata" type="hidden" value="{{ old('lookup_metadata', isset($product) && $product?->lookup_metadata ? json_encode($product->lookup_metadata) : '') }}">
  <input id="looked_up_at" name="looked_up_at" type="hidden" value="{{ old('looked_up_at', isset($product) && $product?->looked_up_at ? $product->looked_up_at->format('Y-m-d H:i:s') : '') }}">
  @if(in_array(request('from'), ['sales', 'inventory'], true))
    <input name="return_to" type="hidden" value="{{ request('from') }}">
  @endif

  <div class="field">
    <label for="name">Nome</label>
    <input id="name" name="name" value="{{ old('name', $product->name ?? '') }}" required>
  </div>
  <div class="field">
    <label for="category">Categoria</label>
    <input id="category" name="category" value="{{ old('category', $product->category ?? '') }}">
  </div>
  <div class="field">
    <label for="brand">Marca</label>
    <input id="brand" name="brand" value="{{ old('brand', $product->brand ?? '') }}">
  </div>
  <div class="field">
    <label for="manufacturer">Fabricante</label>
    <input id="manufacturer" name="manufacturer" value="{{ old('manufacturer', $product->manufacturer ?? '') }}">
  </div>
  <div class="field">
    <label for="sku">SKU</label>
    <input id="sku" name="sku" value="{{ old('sku', $product->sku ?? '') }}">
  </div>
  <div class="field">
    <label for="weight">Peso / volume</label>
    <input id="weight" name="weight" value="{{ old('weight', $product->weight ?? '') }}">
  </div>
  <div class="field">
    <label for="unit">Unidade</label>
    <input id="unit" name="unit" value="{{ old('unit', $product->unit ?? 'un') }}">
  </div>
  <div class="field">
    <label for="cost_price">Custo</label>
    <input id="cost_price" name="cost_price" type="text" inputmode="decimal" placeholder="0,00" value="{{ old('cost_price', $product->cost_price ?? 0) }}" data-money-input>
  </div>
  <div class="field">
    <label for="sale_price">Preco de venda</label>
    <input id="sale_price" name="sale_price" type="text" inputmode="decimal" placeholder="0,00" value="{{ old('sale_price', $product->sale_price ?? 0) }}" data-money-input>
  </div>
  <div class="field">
    <label for="stock_quantity">Estoque atual</label>
    <input id="stock_quantity" name="stock_quantity" type="number" step="0.001" min="0" value="{{ old('stock_quantity', $product->stock_quantity ?? 0) }}">
  </div>
  <div class="field">
    <label for="minimum_stock">Estoque minimo</label>
    <input id="minimum_stock" name="minimum_stock" type="number" step="0.001" min="0" value="{{ old('minimum_stock', $product->minimum_stock ?? 0) }}">
  </div>
  <div class="field">
    <label for="clinic_id">Clinica ID</label>
    <input id="clinic_id" name="clinic_id" type="number" value="{{ old('clinic_id', $product->clinic_id ?? '') }}">
  </div>
  <div class="field">
    <label for="active">Status</label>
    <select id="active" name="active">
      <option value="1" @selected(old('active', $product->active ?? true))>Ativo</option>
      <option value="0" @selected(! old('active', $product->active ?? true))>Inativo</option>
    </select>
  </div>
  <div class="field full">
    <label for="description">Descricao</label>
    <textarea id="description" name="description">{{ old('description', $product->description ?? '') }}</textarea>
  </div>
  <div class="field full">
    <label>Foto do produto</label>
    <input id="image_file" name="image_file" type="file" accept="image/jpeg,image/png,image/webp">
    <div class="product-image-preview" data-product-image-preview @if(! $lookupImageUrl) hidden @endif>
      <img src="{{ $lookupImageUrl ?: '' }}" alt="Foto do produto" data-product-image>
    </div>
  </div>
  <div class="field full">
    <div class="actions">
      <button type="submit">Salvar</button>
      <a class="button secondary" href="{{ $cancelRoute }}">Cancelar</a>
    </div>
  </div>
</div>
