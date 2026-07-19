<div class="form-grid">
  <input name="purchase_entry_id" type="hidden" value="{{ old('purchase_entry_id', $transaction->purchase_entry_id ?? '') }}">
  <input name="installment_number" type="hidden" value="{{ old('installment_number', $transaction->installment_number ?? 1) }}">
  <input name="installment_total" type="hidden" value="{{ old('installment_total', $transaction->installment_total ?? 1) }}">

  <div class="field">
    <label for="description">Descricao</label>
    <input id="description" name="description" value="{{ old('description', $transaction->description ?? '') }}" required>
  </div>
  <div class="field">
    <label for="type">Tipo</label>
    <select id="type" name="type" required>
      <option value="income" @selected(old('type', $transaction->type ?? 'income') === 'income')>Entrada</option>
      <option value="expense" @selected(old('type', $transaction->type ?? 'income') === 'expense')>Saida</option>
    </select>
  </div>
  <div class="field">
    <label for="amount">Valor</label>
    <input id="amount" name="amount" type="number" step="0.01" min="0" value="{{ old('amount', $transaction->amount ?? '') }}" required>
  </div>
  <div class="field">
    <label for="supplier_id">Fornecedor</label>
    <select id="supplier_id" name="supplier_id">
      <option value="">Selecione</option>
      @foreach(($suppliers ?? collect()) as $supplier)
        <option value="{{ $supplier->id }}" @selected((int) old('supplier_id', $transaction->supplier_id ?? 0) === $supplier->id)>
          {{ $supplier->name }}
        </option>
      @endforeach
    </select>
  </div>
  <div class="field">
    <label for="due_date">Vencimento</label>
    <input id="due_date" name="due_date" type="date" value="{{ old('due_date', isset($transaction) && $transaction?->due_date ? $transaction->due_date->format('Y-m-d') : '') }}">
  </div>
  <div class="field">
    <label for="paid_at">Pagamento</label>
    <input id="paid_at" name="paid_at" type="datetime-local" value="{{ old('paid_at', isset($transaction) && $transaction?->paid_at ? $transaction->paid_at->format('Y-m-d\TH:i') : '') }}">
  </div>
  <div class="field">
    <label for="status">Status</label>
    <select id="status" name="status">
      <option value="pending" @selected(old('status', $transaction->status ?? 'pending') === 'pending')>Pendente</option>
      <option value="paid" @selected(old('status', $transaction->status ?? 'pending') === 'paid')>Pago</option>
      <option value="overdue" @selected(old('status', $transaction->status ?? 'pending') === 'overdue')>Vencido</option>
      <option value="cancelled" @selected(old('status', $transaction->status ?? 'pending') === 'cancelled')>Cancelado</option>
    </select>
  </div>
  <div class="field">
    <label for="payment_method">Forma de pagamento</label>
    <select id="payment_method" name="payment_method">
      <option value="">Selecione</option>
      <option value="cash" @selected(old('payment_method', $transaction->payment_method ?? '') === 'cash')>Dinheiro</option>
      <option value="pix" @selected(old('payment_method', $transaction->payment_method ?? '') === 'pix')>PIX</option>
      <option value="debit_card" @selected(old('payment_method', $transaction->payment_method ?? '') === 'debit_card')>Cartao de debito</option>
      <option value="credit_card" @selected(old('payment_method', $transaction->payment_method ?? '') === 'credit_card')>Cartao de credito</option>
      <option value="transfer" @selected(old('payment_method', $transaction->payment_method ?? '') === 'transfer')>Transferencia</option>
      <option value="bank_slip" @selected(old('payment_method', $transaction->payment_method ?? '') === 'bank_slip')>Boleto</option>
      <option value="other" @selected(old('payment_method', $transaction->payment_method ?? '') === 'other')>Outro</option>
    </select>
  </div>
  <div class="field">
    <label for="reference">Referencia</label>
    <input id="reference" name="reference" value="{{ old('reference', $transaction->reference ?? '') }}">
  </div>
  <div class="field full">
    <label for="notes">Observacoes</label>
    <textarea id="notes" name="notes">{{ old('notes', $transaction->notes ?? '') }}</textarea>
  </div>
  <div class="field full">
    <div class="actions">
      <button type="submit">Salvar</button>
      <a class="button secondary" href="{{ route('financial-transactions.index') }}">Cancelar</a>
    </div>
  </div>
</div>
