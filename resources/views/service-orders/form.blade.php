@php
  $statuses = [
    'open' => 'Aberta',
    'in_service' => 'Em atendimento',
    'waiting_pickup' => 'Aguardando retirada',
    'finished' => 'Finalizada',
    'cancelled' => 'Cancelada',
  ];

  $rows = old('items');

  if ($rows === null && isset($order) && $order) {
    $rows = $order->items->map(fn ($item) => [
      'type' => $item->type,
      'product_id' => $item->product_id,
      'petshop_service_id' => $item->petshop_service_id,
      'description' => $item->description,
      'quantity' => $item->quantity,
      'unit_price' => $item->unit_price,
    ])->toArray();
  }

  $rows = array_pad($rows ?: [], 8, []);
@endphp

<div class="form-grid">
  <div class="field">
    <label for="status">Status</label>
    <select id="status" name="status">
      @foreach($statuses as $value => $label)
        <option value="{{ $value }}" @selected(old('status', $order->status ?? 'open') === $value)>{{ $label }}</option>
      @endforeach
    </select>
  </div>
  <div class="field">
    <label for="opened_at">Abertura</label>
    <input id="opened_at" name="opened_at" type="datetime-local" value="{{ old('opened_at', isset($order) && $order?->opened_at ? $order->opened_at->format('Y-m-d\TH:i') : now()->format('Y-m-d\TH:i')) }}">
  </div>
  <div class="field">
    <label for="scheduled_at">Agendamento</label>
    <input id="scheduled_at" name="scheduled_at" type="datetime-local" value="{{ old('scheduled_at', isset($order) && $order?->scheduled_at ? $order->scheduled_at->format('Y-m-d\TH:i') : '') }}">
  </div>
  <div class="field">
    <label for="closed_at">Fechamento</label>
    <input id="closed_at" name="closed_at" type="datetime-local" value="{{ old('closed_at', isset($order) && $order?->closed_at ? $order->closed_at->format('Y-m-d\TH:i') : '') }}">
  </div>
  <div class="field">
    <label for="tutor_id">Tutor</label>
    <select id="tutor_id" name="tutor_id">
      <option value="">Selecione</option>
      @foreach($tutors as $tutor)
        <option value="{{ $tutor->id }}" @selected((int) old('tutor_id', $order->tutor_id ?? 0) === $tutor->id)>{{ $tutor->name }}</option>
      @endforeach
    </select>
  </div>
  <div class="field">
    <label for="patient_id">Pet</label>
    <select id="patient_id" name="patient_id">
      <option value="">Selecione</option>
      @foreach($patients as $patient)
        <option value="{{ $patient->id }}" @selected((int) old('patient_id', $order->patient_id ?? 0) === $patient->id)>{{ $patient->name }}</option>
      @endforeach
    </select>
  </div>
  <div class="field">
    <label for="discount_total">Desconto</label>
    <input id="discount_total" name="discount_total" type="number" step="0.01" min="0" value="{{ old('discount_total', $order->discount_total ?? 0) }}">
  </div>
  <div class="field">
    <label for="clinic_id">Clinica ID</label>
    <input id="clinic_id" name="clinic_id" type="number" value="{{ old('clinic_id', $order->clinic_id ?? '') }}">
  </div>
  <div class="field full">
    <label for="notes">Observacoes</label>
    <textarea id="notes" name="notes">{{ old('notes', $order->notes ?? '') }}</textarea>
  </div>

  <div class="field full">
    <label>Itens da comanda</label>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Tipo</th>
            <th>Servico</th>
            <th>Produto</th>
            <th>Descricao</th>
            <th>Qtd</th>
            <th>Valor</th>
          </tr>
        </thead>
        <tbody>
          @foreach($rows as $index => $row)
            <tr>
              <td>
                <select name="items[{{ $index }}][type]">
                  <option value="service" @selected(($row['type'] ?? 'service') === 'service')>Servico</option>
                  <option value="product" @selected(($row['type'] ?? '') === 'product')>Produto</option>
                  <option value="custom" @selected(($row['type'] ?? '') === 'custom')>Avulso</option>
                </select>
              </td>
              <td>
                <select name="items[{{ $index }}][petshop_service_id]">
                  <option value="">Selecione</option>
                  @foreach($petShopServices as $petShopService)
                    <option value="{{ $petShopService->id }}" @selected((int) ($row['petshop_service_id'] ?? 0) === $petShopService->id)>
                      {{ $petShopService->name }}
                    </option>
                  @endforeach
                </select>
              </td>
              <td>
                <select name="items[{{ $index }}][product_id]">
                  <option value="">Selecione</option>
                  @foreach($products as $product)
                    <option value="{{ $product->id }}" @selected((int) ($row['product_id'] ?? 0) === $product->id)>
                      {{ $product->name }}
                    </option>
                  @endforeach
                </select>
              </td>
              <td>
                <input name="items[{{ $index }}][description]" value="{{ $row['description'] ?? '' }}">
              </td>
              <td>
                <input name="items[{{ $index }}][quantity]" type="number" step="0.001" min="0" value="{{ $row['quantity'] ?? '' }}">
              </td>
              <td>
                <input name="items[{{ $index }}][unit_price]" type="number" step="0.01" min="0" value="{{ $row['unit_price'] ?? '' }}">
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  <div class="field full">
    <div class="actions">
      <button type="submit">Salvar</button>
      <a class="button secondary" href="{{ route('service-orders.index') }}">Cancelar</a>
    </div>
  </div>
</div>
