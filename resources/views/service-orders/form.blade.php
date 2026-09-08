@php
  $statuses = [
    'open' => 'Aberta',
    'in_service' => 'Em atendimento',
    'waiting_pickup' => 'Aguardando retirada',
    'finished' => 'Finalizada',
    'cancelled' => 'Cancelada',
  ];

  $selectedClinicId = (int) old(
    'clinic_id',
    $order->clinic_id ?? request('clinic_id', $clinics->count() === 1 ? $clinics->first()->id : 0)
  );

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

<div
  class="form-grid"
  data-service-order-form
  data-service-order-clinic-id="{{ auth()->user()?->clinic_id ?? $selectedClinicId }}"
>
  @include('shared.clinic-required-alert', ['clinics' => $clinics])

  @if(auth()->user()?->clinic_id === null)
    <div class="field">
      <label for="clinic_id">Clinica</label>
      <select id="clinic_id" name="clinic_id" data-service-order-clinic-select required>
        <option value="">Selecione</option>
        @foreach($clinics as $clinic)
          <option value="{{ $clinic->id }}" @selected($selectedClinicId === $clinic->id)>{{ $clinic->trade_name ?? $clinic->corporate_name }}</option>
        @endforeach
      </select>
    </div>
  @endif

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
    <label for="tutor_id">Responsável</label>
    <select id="tutor_id" name="tutor_id" data-service-order-tutor-select>
      <option value="">Selecione</option>
      @foreach($tutors as $tutor)
        <option value="{{ $tutor->id }}" data-clinic-id="{{ $tutor->clinic_id }}" @selected((int) old('tutor_id', $order->tutor_id ?? 0) === $tutor->id)>{{ $tutor->name }}</option>
      @endforeach
    </select>
  </div>
  <div class="field">
    <label for="patient_id">Pet</label>
    <select id="patient_id" name="patient_id" data-service-order-patient-select>
      <option value="">Selecione</option>
      @foreach($patients as $patient)
        <option
          value="{{ $patient->id }}"
          data-clinic-id="{{ $patient->clinic_id }}"
          data-tutor-id="{{ $patient->tutor_id }}"
          @selected((int) old('patient_id', $order->patient_id ?? 0) === $patient->id)
        >{{ $patient->name }}</option>
      @endforeach
    </select>
  </div>
  <div class="field">
    <label for="discount_total">Desconto</label>
    <input id="discount_total" name="discount_total" type="number" step="0.01" min="0" value="{{ old('discount_total', $order->discount_total ?? 0) }}" data-service-order-discount>
  </div>
  <div class="field">
    <label for="assigned_user_id">Profissional responsável</label>
    <select id="assigned_user_id" name="assigned_user_id" data-service-order-assigned-user-select>
      <option value="">A definir</option>
      @foreach($assignedUsers as $assignedUser)
        <option
          value="{{ $assignedUser->id }}"
          data-clinic-id="{{ $assignedUser->clinic_id }}"
          @selected((string) old('assigned_user_id', $order->assigned_user_id ?? '') === (string) $assignedUser->id)
        >{{ $assignedUser->name }}</option>
      @endforeach
    </select>
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
            <th>Tabela do servico</th>
            <th>Descricao</th>
            <th>Qtd</th>
            <th>Valor</th>
            <th>Acao</th>
          </tr>
        </thead>
        <tbody>
          @foreach($rows as $index => $row)
            <tr data-service-order-item-row>
              <td>
                <select name="items[{{ $index }}][type]" data-service-order-item-type>
                  <option value="service" @selected(($row['type'] ?? 'service') === 'service')>Servico</option>
                  <option value="product" @selected(($row['type'] ?? '') === 'product')>Produto</option>
                  <option value="custom" @selected(($row['type'] ?? '') === 'custom')>Avulso</option>
                </select>
              </td>
              <td>
                <select name="items[{{ $index }}][petshop_service_id]" data-service-order-service-select>
                  <option value="">Selecione</option>
                  @foreach($petShopServices as $petShopService)
                    <option
                      value="{{ $petShopService->id }}"
                      data-clinic-id="{{ $petShopService->clinic_id }}"
                      data-name="{{ $petShopService->name }}"
                      data-price-base="{{ $petShopService->base_price }}"
                      data-price-small="{{ $petShopService->small_price }}"
                      data-price-medium="{{ $petShopService->medium_price }}"
                      data-price-large="{{ $petShopService->large_price }}"
                      data-price-giant="{{ $petShopService->giant_price }}"
                      @selected((int) ($row['petshop_service_id'] ?? 0) === $petShopService->id)
                    >
                      {{ $petShopService->name }}
                    </option>
                  @endforeach
                </select>
              </td>
              <td>
                <select name="items[{{ $index }}][product_id]" data-service-order-product-select>
                  <option value="">Selecione</option>
                  @foreach($products as $product)
                    <option
                      value="{{ $product->id }}"
                      data-clinic-id="{{ $product->clinic_id }}"
                      data-name="{{ $product->name }}"
                      data-sale-price="{{ $product->sale_price }}"
                      @selected((int) ($row['product_id'] ?? 0) === $product->id)
                    >
                      {{ $product->name }}
                    </option>
                  @endforeach
                </select>
              </td>
              <td>
                <select data-service-order-price-select disabled>
                  <option value="">Selecione o servico</option>
                </select>
              </td>
              <td>
                <input name="items[{{ $index }}][description]" value="{{ $row['description'] ?? '' }}" data-service-order-description>
              </td>
              <td>
                <input name="items[{{ $index }}][quantity]" type="number" step="0.001" min="0" value="{{ $row['quantity'] ?? '' }}" data-service-order-quantity>
              </td>
              <td>
                <input name="items[{{ $index }}][unit_price]" type="number" step="0.01" min="0" value="{{ $row['unit_price'] ?? '' }}" data-service-order-unit-price>
              </td>
              <td>
                <button type="button" class="secondary" data-service-order-clear-item>Limpar</button>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  <div class="field full">
    <div class="service-order-summary" aria-live="polite">
      <div>
        <span>Servicos</span>
        <strong data-service-order-services-total>R$ 0,00</strong>
      </div>
      <div>
        <span>Produtos</span>
        <strong data-service-order-products-total>R$ 0,00</strong>
      </div>
      <div>
        <span>Desconto</span>
        <strong data-service-order-discount-total>R$ 0,00</strong>
      </div>
      <div class="service-order-summary-total">
        <span>Total da comanda</span>
        <strong data-service-order-total>R$ 0,00</strong>
      </div>
    </div>
  </div>

  <div class="field full">
    <div class="actions">
      <button type="submit">Salvar</button>
      <a class="button secondary" href="{{ route('service-orders.index') }}">Cancelar</a>
    </div>
  </div>
</div>
