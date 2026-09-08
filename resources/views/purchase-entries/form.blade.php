@php
  $selectedClinicId = (int) old('clinic_id', $entry->clinic_id ?? request('clinic_id', $clinics->count() === 1 ? $clinics->first()->id : 0));
  $entryItems = $entry?->items?->map(fn ($item) => [
    'product_id' => $item->product_id,
    'description' => $item->description,
    'quantity' => $item->quantity,
    'unit_cost' => $item->unit_cost,
    'sale_price' => $item->sale_price,
    'margin_percent' => $item->margin_percent,
    'update_sale_price' => $item->update_sale_price,
    'minimum_stock_after_entry' => $item->minimum_stock_after_entry,
    'barcode_snapshot' => $item->barcode_snapshot,
    'supplier_sku' => $item->supplier_sku,
    'intelligence_status' => $item->intelligence_status,
    'intelligence_metadata' => $item->intelligence_metadata,
    'replenishment_adjustment_reason' => data_get($item->intelligence_metadata, 'replenishment_decision.adjustment_reason.code'),
    'replenishment_adjustment_note' => data_get($item->intelligence_metadata, 'replenishment_decision.adjustment_reason.note'),
    'lot_number' => $item->lot_number,
    'expires_at' => optional($item->expires_at)->format('Y-m-d'),
    'notes' => $item->notes,
  ])->toArray() ?? [];
  if ($entry === null && $entryItems === [] && ! empty($suggestedItem)) {
    $entryItems[] = $suggestedItem;
  }
  $rows = array_values(old('items', $entryItems));
  $replenishmentRows = collect($rows)->filter(
    fn ($item) => ($item['intelligence_status'] ?? null) === 'replenishment_suggestion'
  );
  $rowCount = max(12, count($rows));
  $financials = $entry?->financialTransactions?->sortBy('installment_number')->values() ?? collect();
  $firstFinancial = $financials->first();
  $secondFinancial = $financials->get(1);
  $installmentsCount = old('installments_count', $firstFinancial?->installment_total ?: max(1, $financials->count()));
  $installmentIntervalDays = old(
    'installment_interval_days',
    $firstFinancial?->due_date && $secondFinancial?->due_date
      ? max(1, $firstFinancial->due_date->diffInDays($secondFinancial->due_date))
      : 30
  );
  $paymentMethods = [
    'cash' => 'Dinheiro',
    'pix' => 'PIX',
    'debit_card' => 'Cartao de debito',
    'credit_card' => 'Cartao de credito',
    'transfer' => 'Transferencia',
    'bank_slip' => 'Boleto',
    'other' => 'Outro',
  ];
@endphp

<div class="form-grid">
  @include('shared.clinic-required-alert', ['clinics' => $clinics])

  <div class="field">
    <label for="status">Status</label>
    <select id="status" name="status">
      <option value="received" @selected(old('status', $entry->status ?? 'received') === 'received')>Recebida</option>
      <option value="draft" @selected(old('status', $entry->status ?? 'received') === 'draft')>Rascunho</option>
      <option value="cancelled" @selected(old('status', $entry->status ?? 'received') === 'cancelled')>Cancelada</option>
    </select>
  </div>
  @if(auth()->user()?->clinic_id === null)
    <div class="field">
      <label for="clinic_id">Clinica</label>
      <select id="clinic_id" name="clinic_id" data-purchase-clinic-select required>
        <option value="">Selecione</option>
        @foreach($clinics as $clinic)
          <option value="{{ $clinic->id }}" @selected($selectedClinicId === $clinic->id)>{{ $clinic->trade_name ?? $clinic->corporate_name }}</option>
        @endforeach
      </select>
    </div>
  @endif
  <div class="field">
    <label for="supplier_id">Fornecedor</label>
    <select id="supplier_id" name="supplier_id">
        <option value="">Selecione</option>
        @foreach($suppliers as $supplier)
          <option value="{{ $supplier->id }}" @selected((int) old('supplier_id', $entry->supplier_id ?? request('supplier_id', 0)) === $supplier->id)>
          {{ $supplier->name }}
        </option>
      @endforeach
    </select>
  </div>
  <div class="field full">
    <label for="invoice_scan">Leitura da chave da NF-e</label>
    <div
      class="purchase-scan"
      data-purchase-invoice-scanner
      data-purchase-nfe-key-import-url="{{ route('purchase-entries.import-nfe-key') }}"
    >
      <div class="sale-scan-row">
        <input id="invoice_scan" data-purchase-invoice-input inputmode="numeric" placeholder="Escaneie a chave de acesso com 44 digitos, codigo de barras do DANFE ou QR Code">
        <button type="button" data-purchase-invoice-button>Buscar NF-e</button>
      </div>
      <div class="field-hint">Busca a NF-e completa pela chave. Se a integracao nao encontrar, use o XML como fallback.</div>
      <div class="lookup-status" data-purchase-invoice-status></div>
    </div>
  </div>
  <div class="field full">
    <label for="nfe_xml_file">XML da NF-e (importacao completa)</label>
    <div
      class="purchase-scan"
      data-purchase-xml-importer
      data-purchase-xml-import-url="{{ route('purchase-entries.import-nfe-xml') }}"
    >
      <div class="sale-scan-row">
        <input id="nfe_xml_file" type="file" accept=".xml,text/xml,application/xml" data-purchase-xml-input>
        <label class="checkbox-inline">
          <input type="checkbox" data-purchase-xml-create-supplier checked>
          Cadastrar fornecedor novo
        </label>
        <label class="checkbox-inline">
          <input type="checkbox" data-purchase-xml-create-products checked>
          Cadastrar produtos novos
        </label>
        <button type="button" data-purchase-xml-button>Importar XML</button>
      </div>
      <div class="field-hint">Depois de ler a chave, importe o XML para preencher data da compra, fornecedor, produtos, quantidades e total.</div>
      <div class="lookup-status" data-purchase-xml-status></div>
    </div>
  </div>
  <div class="field">
    <label for="invoice_number">Numero da NF</label>
    <input id="invoice_number" name="invoice_number" value="{{ old('invoice_number', $entry->invoice_number ?? '') }}">
  </div>
  <div class="field">
    <label for="invoice_key">Chave da NF</label>
    <input id="invoice_key" name="invoice_key" value="{{ old('invoice_key', $entry->invoice_key ?? '') }}">
  </div>
  <div class="field">
    <label for="purchased_at">Data da compra</label>
    <input id="purchased_at" name="purchased_at" type="datetime-local" value="{{ old('purchased_at', isset($entry) && $entry?->purchased_at ? $entry->purchased_at->format('Y-m-d\TH:i') : now()->format('Y-m-d\TH:i')) }}">
  </div>
  <div class="field">
    <label for="received_at">Data do recebimento</label>
    <input id="received_at" name="received_at" type="datetime-local" value="{{ old('received_at', isset($entry) && $entry?->received_at ? $entry->received_at->format('Y-m-d\TH:i') : now()->format('Y-m-d\TH:i')) }}">
  </div>
  <div class="field">
    <label>Total estimado</label>
    <div class="readonly-total" data-purchase-total>R$ {{ number_format((float) ($entry->total ?? 0), 2, ',', '.') }}</div>
  </div>
  <div class="field">
    <label for="payment_due_date">Primeiro vencimento</label>
    <input id="payment_due_date" name="payment_due_date" type="date" value="{{ old('payment_due_date', $firstFinancial?->due_date ? $firstFinancial->due_date->format('Y-m-d') : '') }}">
  </div>
  <div class="field">
    <label for="installments_count">Parcelas</label>
    <input id="installments_count" name="installments_count" type="number" min="1" max="60" value="{{ $installmentsCount }}">
  </div>
  <div class="field">
    <label for="installment_interval_days">Intervalo entre parcelas</label>
    <input id="installment_interval_days" name="installment_interval_days" type="number" min="1" max="365" value="{{ $installmentIntervalDays }}">
  </div>
  <div class="field">
    <label for="payment_status">Pagamento</label>
    <select id="payment_status" name="payment_status">
      <option value="pending" @selected(old('payment_status', $firstFinancial->status ?? 'pending') === 'pending')>Pendente</option>
      <option value="paid" @selected(old('payment_status', $firstFinancial->status ?? 'pending') === 'paid')>Pago</option>
      <option value="overdue" @selected(old('payment_status', $firstFinancial->status ?? 'pending') === 'overdue')>Vencido</option>
      <option value="cancelled" @selected(old('payment_status', $firstFinancial->status ?? 'pending') === 'cancelled')>Cancelado</option>
    </select>
  </div>
  <div class="field">
    <label for="payment_method">Forma de pagamento</label>
    <select id="payment_method" name="payment_method">
      <option value="">Selecione</option>
      @foreach($paymentMethods as $value => $label)
        <option value="{{ $value }}" @selected(old('payment_method', $firstFinancial->payment_method ?? '') === $value)>{{ $label }}</option>
      @endforeach
    </select>
  </div>
  <div class="field">
    <label for="paid_at">Data do pagamento</label>
    <input id="paid_at" name="paid_at" type="datetime-local" value="{{ old('paid_at', $firstFinancial?->paid_at ? $firstFinancial->paid_at->format('Y-m-d\TH:i') : '') }}">
  </div>
  <div class="field">
    <label for="payment_reference">Referencia</label>
    <input id="payment_reference" name="payment_reference" value="{{ old('payment_reference', $firstFinancial->reference ?? '') }}" placeholder="Boleto, comprovante ou referencia">
  </div>
  <div class="field full">
    <label for="notes">Observacoes</label>
    <textarea id="notes" name="notes">{{ old('notes', $entry->notes ?? '') }}</textarea>
  </div>
</div>

<div class="panel nested-panel nfe-review" data-purchase-nfe-review hidden>
  <div class="panel-heading">
    <div>
      <h2>Conferencia da NF importada</h2>
      <p data-purchase-nfe-review-subtitle>Resumo para revisar antes de salvar a entrada.</p>
    </div>
    <span class="badge muted-badge" data-purchase-nfe-review-state>Aguardando XML</span>
  </div>
  <div class="panel-body">
    <div class="nfe-review-grid">
      <div>
        <span>Itens</span>
        <strong data-purchase-nfe-items-count>0</strong>
      </div>
      <div>
        <span>Vinculados</span>
        <strong data-purchase-nfe-matched-count>0</strong>
      </div>
      <div>
        <span>Criados</span>
        <strong data-purchase-nfe-created-count>0</strong>
      </div>
      <div>
        <span>Pendentes</span>
        <strong data-purchase-nfe-pending-count>0</strong>
      </div>
      <div>
        <span>Total XML</span>
        <strong data-purchase-nfe-xml-total>R$ 0,00</strong>
      </div>
      <div>
        <span>Total entrada</span>
        <strong data-purchase-nfe-entry-total>R$ 0,00</strong>
      </div>
      <div>
        <span>Diferenca</span>
        <strong data-purchase-nfe-total-diff>R$ 0,00</strong>
      </div>
    </div>
    <div class="nfe-review-meta">
      <div>
        <span>Nota</span>
        <strong data-purchase-nfe-invoice-label>-</strong>
      </div>
      <div>
        <span>Fornecedor</span>
        <strong data-purchase-nfe-supplier-label>-</strong>
      </div>
    </div>
    <div class="nfe-review-alerts" data-purchase-nfe-alerts></div>
    <div class="table-wrap nfe-review-table">
      <table>
        <thead>
          <tr>
            <th>Item</th>
            <th>EAN</th>
            <th>Status</th>
            <th>Qtd</th>
            <th>Custo</th>
            <th>Total</th>
            <th>Acao</th>
          </tr>
        </thead>
        <tbody data-purchase-nfe-items></tbody>
      </table>
    </div>
  </div>
</div>

<div class="panel nested-panel">
  <div class="panel-heading">
    <div>
      <h2>Produtos recebidos</h2>
      <p>Escaneie o EAN ou informe custo, quantidade, lote, validade e preco sugerido.</p>
    </div>
  </div>
  <div class="panel-body">
    <div
      class="purchase-scan"
      data-purchase-scanner
      data-purchase-lookup-url="{{ route('purchase-entries.product-lookup', ['gtin' => '__GTIN__']) }}"
      data-product-create-url="{{ route('products.create') }}?gtin=__GTIN__&from=purchase&return_to=purchase"
      data-purchase-lookup-auto="{{ ! empty($scanGtin) ? '1' : '0' }}"
    >
      <div class="sale-scan-row">
        <input data-purchase-barcode-input value="{{ $scanGtin ?? '' }}" inputmode="numeric" placeholder="Escaneie ou digite o EAN/GTIN para adicionar na entrada">
        <button type="button" data-purchase-barcode-button>Buscar EAN</button>
        <a class="button secondary" data-purchase-create-product-link href="#" hidden>Cadastrar produto agora</a>
      </div>
      <div class="lookup-status" data-purchase-lookup-status></div>
    </div>
  </div>
  <div class="table-wrap">
    <table class="purchase-items-table">
      <thead>
        <tr>
          <th>Produto</th>
          <th>Descricao</th>
          <th>EAN</th>
          <th>Qtd</th>
          <th>Custo unit.</th>
          <th>Venda</th>
          <th>Margem</th>
          <th>Atualizar venda</th>
          <th>Minimo</th>
          <th>Lote</th>
          <th>Validade</th>
          <th>Total</th>
        </tr>
      </thead>
      <tbody>
        @for($index = 0; $index < $rowCount; $index++)
          @php($item = $rows[$index] ?? [])
          <tr data-purchase-item-row>
            <td>
              <select name="items[{{ $index }}][product_id]" data-purchase-product-select>
                <option value="">Selecione</option>
                @foreach($products as $product)
                  <option
                    value="{{ $product->id }}"
                    data-description="{{ $product->name }}"
                    data-cost="{{ $product->cost_price }}"
                    data-sale-price="{{ $product->sale_price }}"
                    data-stock="{{ $product->stock_quantity }}"
                    data-minimum-stock="{{ $product->minimum_stock }}"
                    data-gtin="{{ $product->gtin ?: $product->barcode }}"
                    data-unit="{{ $product->unit }}"
                    @selected((int) ($item['product_id'] ?? 0) === $product->id)
                  >
                    {{ $product->name }}
                  </option>
                @endforeach
              </select>
            </td>
            <td><input name="items[{{ $index }}][description]" value="{{ $item['description'] ?? '' }}" data-purchase-description></td>
            <td>
              <input name="items[{{ $index }}][barcode_snapshot]" value="{{ $item['barcode_snapshot'] ?? '' }}" data-purchase-barcode-snapshot>
              <input type="hidden" name="items[{{ $index }}][supplier_sku]" value="{{ $item['supplier_sku'] ?? '' }}" data-purchase-supplier-sku>
              <input type="hidden" name="items[{{ $index }}][intelligence_status]" value="{{ $item['intelligence_status'] ?? '' }}" data-purchase-intelligence-status>
              <input type="hidden" name="items[{{ $index }}][intelligence_metadata]" value="{{ is_array($item['intelligence_metadata'] ?? null) ? json_encode($item['intelligence_metadata']) : ($item['intelligence_metadata'] ?? '') }}" data-purchase-intelligence-metadata>
            </td>
            <td><input name="items[{{ $index }}][quantity]" type="number" step="0.001" min="0" value="{{ $item['quantity'] ?? '' }}" data-purchase-quantity></td>
            <td><input name="items[{{ $index }}][unit_cost]" type="text" inputmode="decimal" placeholder="0,00" value="{{ $item['unit_cost'] ?? '' }}" data-money-input data-purchase-unit-cost></td>
            <td><input name="items[{{ $index }}][sale_price]" type="text" inputmode="decimal" placeholder="0,00" value="{{ $item['sale_price'] ?? '' }}" data-money-input data-purchase-sale-price></td>
            <td><input name="items[{{ $index }}][margin_percent]" type="text" inputmode="decimal" placeholder="%" value="{{ $item['margin_percent'] ?? '' }}" data-purchase-margin></td>
            <td>
              <label class="checkbox-inline">
                <input name="items[{{ $index }}][update_sale_price]" type="checkbox" value="1" @checked((bool) ($item['update_sale_price'] ?? false)) data-purchase-update-sale-price>
                Sim
              </label>
            </td>
            <td><input name="items[{{ $index }}][minimum_stock_after_entry]" type="number" step="0.001" min="0" value="{{ $item['minimum_stock_after_entry'] ?? '' }}" data-purchase-minimum-stock></td>
            <td><input name="items[{{ $index }}][lot_number]" value="{{ $item['lot_number'] ?? '' }}" placeholder="Lote" data-purchase-lot-number></td>
            <td><input name="items[{{ $index }}][expires_at]" type="date" value="{{ $item['expires_at'] ?? '' }}" data-purchase-expires-at></td>
            <td><span data-purchase-row-total>R$ 0,00</span></td>
          </tr>
        @endfor
      </tbody>
    </table>
  </div>
  @if($replenishmentRows->isNotEmpty())
    <div class="panel-body">
      <div class="intelligence-health">
        <div>
          <strong>Justificativa das alterações da reposição</strong>
          <span>Se quantidade, custo ou fornecedor forem diferentes da sugestão, informe o motivo antes de salvar.</span>
        </div>
      </div>
      <div class="form-grid">
        @foreach($replenishmentRows as $index => $item)
          <div class="field">
            <label for="replenishment-adjustment-reason-{{ $index }}">
              Motivo — {{ $item['description'] ?? 'Produto sugerido' }}
            </label>
            <select
              id="replenishment-adjustment-reason-{{ $index }}"
              name="items[{{ $index }}][replenishment_adjustment_reason]"
            >
              <option value="">Selecione somente se houver ajuste</option>
              @foreach($replenishmentAdjustmentReasons as $code => $label)
                <option value="{{ $code }}" @selected(($item['replenishment_adjustment_reason'] ?? null) === $code)>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="field">
            <label for="replenishment-adjustment-note-{{ $index }}">Observação do ajuste</label>
            <textarea
              id="replenishment-adjustment-note-{{ $index }}"
              name="items[{{ $index }}][replenishment_adjustment_note]"
              maxlength="500"
              rows="2"
              placeholder="Obrigatória quando selecionar Outro motivo"
            >{{ $item['replenishment_adjustment_note'] ?? '' }}</textarea>
          </div>
        @endforeach
      </div>
    </div>
  @endif
</div>

<div class="panel nested-panel purchase-impact-preview" data-purchase-impact-preview>
  <div class="panel-heading">
    <div>
      <h2>Conferencia antes de salvar</h2>
      <p>Estoque, lotes e contas que serao gerados por esta entrada.</p>
    </div>
    <span class="badge muted-badge" data-purchase-preview-status>Aguardando itens</span>
  </div>
  <div class="panel-body">
    <div class="purchase-preview-grid">
      <div>
        <span>Itens no estoque</span>
        <strong data-purchase-preview-stock-count>0</strong>
      </div>
      <div>
        <span>Lotes criados</span>
        <strong data-purchase-preview-lot-count>0</strong>
      </div>
      <div>
        <span>Contas a pagar</span>
        <strong data-purchase-preview-payable-count>0</strong>
      </div>
      <div>
        <span>Total da compra</span>
        <strong data-purchase-preview-total>R$ 0,00</strong>
      </div>
    </div>

    <div class="purchase-preview-columns">
      <div class="purchase-preview-section">
        <h3>Estoque que vai entrar</h3>
        <div class="table-wrap purchase-preview-table">
          <table>
            <thead>
              <tr>
                <th>Produto</th>
                <th>Qtd</th>
                <th>Saldo atual</th>
                <th>Saldo apos</th>
                <th>Total</th>
              </tr>
            </thead>
            <tbody data-purchase-preview-stock-body>
              <tr><td colspan="5" class="muted">Inclua produtos para visualizar o estoque.</td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="purchase-preview-section">
        <h3>Lotes criados</h3>
        <div class="table-wrap purchase-preview-table">
          <table>
            <thead>
              <tr>
                <th>Produto</th>
                <th>Lote</th>
                <th>Validade</th>
                <th>Qtd</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody data-purchase-preview-lot-body>
              <tr><td colspan="5" class="muted">Informe lote e validade quando precisar controlar o item.</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="purchase-preview-section">
      <h3>Contas a pagar geradas</h3>
      <div class="table-wrap purchase-preview-table">
        <table>
          <thead>
            <tr>
              <th>Parcela</th>
              <th>Valor</th>
              <th>Vencimento</th>
              <th>Status</th>
              <th>Referencia</th>
            </tr>
          </thead>
          <tbody data-purchase-preview-payable-body>
            <tr><td colspan="5" class="muted">Informe valor e pagamento para visualizar as contas.</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="actions form-actions">
  <button type="submit">Salvar entrada</button>
  <a class="button secondary" href="{{ route('purchase-entries.index') }}">Cancelar</a>
</div>
