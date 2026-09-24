document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector('[data-pdv-form]');
  if (!form) return;

  const find = (selector) => form.querySelector(selector);
  const cart = find('[data-pdv-cart]');
  const empty = find('[data-pdv-empty]');
  const search = find('[data-pdv-search]');
  const results = find('[data-pdv-results]');
  const lookupStatus = find('[data-pdv-lookup-status]');
  const checkoutStatus = find('[data-pdv-checkout-status]');
  const paymentStatus = find('[data-pdv-payment-status]');
  const dialog = find('[data-pdv-payment-dialog]');
  const payments = find('[data-pdv-payments]');
  const clinic = find('[data-pdv-clinic]');
  const tutor = find('[data-pdv-tutor]');
  const patient = find('[data-pdv-patient]');
  const status = find('[data-pdv-status]');
  const discount = find('[data-pdv-discount]');
  const additions = find('[data-pdv-additions]');
  const createProduct = find('[data-pdv-create-product]');
  const quickProductDialog = find('[data-pdv-quick-product-dialog]');
  const quickProductCode = find('[data-pdv-quick-product-code]');
  const quickProductName = find('[data-pdv-quick-product-name]');
  const quickProductPrice = find('[data-pdv-quick-product-price]');
  const quickProductStock = find('[data-pdv-quick-product-stock]');
  const quickProductUnit = find('[data-pdv-quick-product-unit]');
  const quickProductCost = find('[data-pdv-quick-product-cost]');
  const quickProductStatus = find('[data-pdv-quick-product-status]');
  const saleType = find('[data-pdv-sale-type]');
  const delivery = find('[data-pdv-delivery]');
  const deliveryAddress = find('[data-pdv-delivery-address]');
  const deliveryFeeField = find('[data-pdv-delivery-fee-field]');
  const deliveryFee = find('[data-pdv-delivery-fee]');
  const validUntil = find('[data-pdv-valid-until]');
  const modeInput = find('[data-pdv-mode-input]');
  const summaryLabel = find('[data-pdv-summary-label]');
  const saveQuote = find('[data-pdv-save-quote]');
  const shortcuts = find('[data-pdv-method-shortcuts]');
  let deliveryTypes = [];
  try { deliveryTypes = JSON.parse(form.dataset.deliveryTypes || '[]'); } catch { deliveryTypes = []; }
  let paymentMethods = [];
  try { paymentMethods = JSON.parse(form.dataset.paymentMethods || '[]'); } catch { paymentMethods = []; }
  let cashSessions = {};
  try { cashSessions = JSON.parse(form.dataset.cashSessions || '{}') || {}; } catch { cashSessions = {}; }
  let suggestedOpenings = {};
  try { suggestedOpenings = JSON.parse(form.dataset.cashSuggested || '{}') || {}; } catch { suggestedOpenings = {}; }
  const cashDialog = find('[data-pdv-cash-dialog]');
  const cashOpening = find('[data-pdv-cash-opening]');
  const cashStatus = find('[data-pdv-cash-status]');
  const cashConfirm = find('[data-pdv-cash-confirm]');
  const cashLabel = find('[data-pdv-cash-label]');
  const cashLink = find('[data-pdv-cash-link]');
  const cashOpenButton = find('[data-pdv-cash-open]');
  let afterCashOpen = null;
  const hasDelivery = () => Boolean(saleType && deliveryTypes.includes(saleType.value));
  const isQuoteMode = () => form.dataset.pdvMode === 'quote';
  const brl = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
  const money = (cents) => brl.format(cents / 100);
  const parseMoney = (value) => {
    const raw = String(value ?? '').trim();
    if (!raw) return 0;
    const normalized = raw.includes(',') ? raw.replace(/\./g, '').replace(',', '.') : raw;
    return Math.max(0, Math.round((Number.parseFloat(normalized) || 0) * 100));
  };
  const moneyInput = (cents) => (cents / 100).toFixed(2).replace('.', ',');
  const quantity = (value) => Math.max(0, Number.parseFloat(String(value).replace(',', '.')) || 0);
  const rows = () => Array.from(cart.querySelectorAll('[data-pdv-row]'));
  const paymentRows = () => Array.from(payments.querySelectorAll('[data-pdv-payment-row]'));
  let nextItem = 0;
  let nextPayment = 0;
  let searchTimer;
  let searchSerial = 0;
  let activeResults = [];
  let pendingQuickProductGtin = '';

  const message = (node, value, kind = '') => {
    node.textContent = value;
    node.className = 'lookup-status' + (kind ? ' is-' + kind : '');
  };
  const selectedClinic = () => clinic?.value || '';
  const withClinic = (url) => {
    if (!clinic) return url;
    const address = new URL(url, window.location.origin);
    address.searchParams.set('clinic_id', selectedClinic());
    return address.toString();
  };
  const normalizeRows = () => {
    rows().forEach((row) => {
      row.querySelectorAll('[data-pdv-money]').forEach((input) => {
        input.value = (parseMoney(input.value) / 100).toFixed(2);
      });
    });
    paymentRows().forEach((row) => {
      const input = row.querySelector('[data-pdv-payment-amount]');
      input.value = (parseMoney(input.value) / 100).toFixed(2);
    });
    discount.value = (parseMoney(discount.value) / 100).toFixed(2);
    additions.value = (parseMoney(additions.value) / 100).toFixed(2);
    if (deliveryFee) deliveryFee.value = (parseMoney(deliveryFee.value) / 100).toFixed(2);
  };
  const totals = () => {
    let gross = 0;
    let itemDiscounts = 0;
    rows().forEach((row) => {
      const qty = quantity(row.querySelector('[data-pdv-quantity]').value);
      const price = parseMoney(row.querySelector('[data-pdv-price]').value);
      const reduction = parseMoney(row.querySelector('[data-pdv-item-discount]').value);
      const lineGross = Math.round(qty * price);
      gross += lineGross;
      itemDiscounts += Math.min(lineGross, reduction);
      row.querySelector('[data-pdv-line-total]').textContent = money(Math.max(0, lineGross - reduction));
      const stock = Number.parseFloat(row.dataset.stock);
      const stockNote = row.querySelector('[data-pdv-stock]');
      if (row.dataset.type === 'product' && Number.isFinite(stock)) {
        stockNote.textContent = qty > stock ? 'Estoque vendável insuficiente: ' + stock : 'Estoque vendável: ' + stock;
        stockNote.classList.toggle('is-error', qty > stock);
      }
    });
    const saleDiscount = Math.min(gross - itemDiscounts, parseMoney(discount.value));
    const fee = hasDelivery() && deliveryFee ? parseMoney(deliveryFee.value) : 0;
    const total = Math.max(0, gross - itemDiscounts - saleDiscount + parseMoney(additions.value) + fee);
    const paid = paymentRows().reduce((sum, row) => sum + parseMoney(row.querySelector('[data-pdv-payment-amount]').value), 0);
    const balance = Math.max(0, total - paid);
    const change = Math.max(0, paid - total);
    find('[data-pdv-subtotal]').textContent = money(gross);
    find('[data-pdv-item-discounts]').textContent = money(itemDiscounts);
    find('[data-pdv-total]').textContent = money(total);
    find('[data-pdv-paid]').textContent = money(paid);
    find('[data-pdv-balance-label]').textContent = change ? 'Troco' : 'Falta';
    find('[data-pdv-balance]').textContent = money(change || balance);
    find('[data-pdv-payment-total]').textContent = money(total);
    find('[data-pdv-dialog-paid]').textContent = money(paid);
    find('[data-pdv-dialog-balance]').textContent = money(balance);
    find('[data-pdv-dialog-change]').textContent = money(change);
    find('[data-pdv-item-count]').textContent = rows().length + (rows().length === 1 ? ' item' : ' itens');
    empty.hidden = rows().length > 0;
    return { total, paid, balance, change };
  };

  const addRow = (item) => {
    const type = item.type || 'custom';
    const productId = String(item.product_id || '');
    const serviceId = String(item.petshop_service_id || '');
    const existing = productId
      ? rows().find((row) => row.dataset.type === 'product' && row.dataset.productId === productId)
      : serviceId ? rows().find((row) => row.dataset.type === 'service' && row.dataset.serviceId === serviceId) : null;
    if (existing) {
      const qty = existing.querySelector('[data-pdv-quantity]');
      qty.value = String(Math.round((quantity(qty.value) + quantity(item.quantity || 1)) * 1000) / 1000);
      totals();
      return existing;
    }
    const index = nextItem++;
    const row = document.createElement('article');
    row.className = 'pdv-cart-row';
    row.dataset.pdvRow = '';
    row.dataset.type = type;
    row.dataset.productId = productId;
    row.dataset.serviceId = serviceId;
    if (item.stock_quantity !== undefined) row.dataset.stock = String(item.stock_quantity);
    row.innerHTML = `
      <div class="pdv-row-top"><span data-pdv-kind></span><button type="button" class="secondary" data-pdv-remove aria-label="Remover item">Remover</button></div>
      <input type="hidden" name="items[${index}][type]">
      <input type="hidden" name="items[${index}][product_id]">
      <input type="hidden" name="items[${index}][petshop_service_id]">
      <div class="field pdv-description"><label>Item</label><input name="items[${index}][description]" maxlength="255" data-pdv-description required></div>
      <div class="pdv-row-fields">
        <div class="field"><label>Qtd</label><div class="pdv-quantity"><button type="button" class="secondary" data-pdv-minus aria-label="Diminuir quantidade">−</button><input name="items[${index}][quantity]" type="number" min="0.001" step="0.001" data-pdv-quantity required><button type="button" class="secondary" data-pdv-plus aria-label="Aumentar quantidade">+</button></div></div>
        <div class="field"><label>Preço unit.</label><input name="items[${index}][unit_price]" type="text" inputmode="decimal" data-pdv-price data-pdv-money required></div>
        <div class="field"><label>Desc. item</label><input name="items[${index}][discount_total]" type="text" inputmode="decimal" data-pdv-item-discount data-pdv-money></div>
        <div class="pdv-line-total"><span>Total</span><strong data-pdv-line-total>R$ 0,00</strong></div>
      </div>
      <small data-pdv-stock></small>`;
    row.querySelector('[name$="[type]"]').value = type;
    row.querySelector('[name$="[product_id]"]').value = productId;
    row.querySelector('[name$="[petshop_service_id]"]').value = serviceId;
    row.querySelector('[data-pdv-kind]').textContent = type === 'product' ? 'Produto' : type === 'service' ? 'Serviço' : 'Avulso';
    row.querySelector('[data-pdv-description]').value = item.description || '';
    row.querySelector('[data-pdv-quantity]').value = String(item.quantity || 1);
    row.querySelector('[data-pdv-price]').value = moneyInput(parseMoney(item.unit_price));
    row.querySelector('[data-pdv-item-discount]').value = moneyInput(parseMoney(item.discount_total));
    row.querySelector('[data-pdv-remove]').addEventListener('click', () => { row.remove(); totals(); search.focus(); });
    row.querySelector('[data-pdv-minus]').addEventListener('click', () => {
      const input = row.querySelector('[data-pdv-quantity]');
      input.value = String(Math.max(0.001, Math.round((quantity(input.value) - 1) * 1000) / 1000));
      totals();
    });
    row.querySelector('[data-pdv-plus]').addEventListener('click', () => {
      const input = row.querySelector('[data-pdv-quantity]');
      input.value = String(Math.round((quantity(input.value) + 1) * 1000) / 1000);
      totals();
    });
    row.querySelectorAll('input').forEach((input) => input.addEventListener('input', totals));
    cart.appendChild(row);
    totals();
    return row;
  };

  // Payment methods of the selected clinic (one per card machine and type).
  // A global user sees none until a clinic is chosen.
  const availableMethods = () => {
    if (clinic && !selectedClinic()) return [];
    return paymentMethods.filter((method) => !clinic || String(method.clinic_id) === selectedClinic());
  };
  const methodById = (id) => paymentMethods.find((method) => String(method.id) === String(id || ''));
  const rowMethod = (row) => methodById(row.querySelector('[data-pdv-payment-method]').value);
  const rowKind = (row) => rowMethod(row)?.kind || '';
  const isCardKind = (kind) => kind === 'credit_card' || kind === 'debit_card';
  const fillMethodOptions = (select, selectedId, fallbackKind = '') => {
    const methods = availableMethods();
    const selected = methods.find((method) => String(method.id) === String(selectedId || ''))
      || (fallbackKind ? methods.find((method) => method.kind === fallbackKind) : null);
    select.replaceChildren(new Option(methods.length ? 'Selecione' : 'Nenhuma forma ativa', ''));
    methods.forEach((method) => {
      const option = new Option(method.name, String(method.id));
      option.dataset.kind = method.kind;
      select.appendChild(option);
    });
    select.value = selected ? String(selected.id) : '';
  };
  const addPayment = (payment = {}) => {
    const index = nextPayment++;
    const row = document.createElement('div');
    row.className = 'pdv-payment-row';
    row.dataset.pdvPaymentRow = '';
    row.innerHTML = `
      <div class="pdv-payment-fields">
        <div class="field"><label>Forma</label><select name="payments[${index}][payment_method_id]" data-pdv-payment-method></select>
          <input type="hidden" name="payments[${index}][method]" data-pdv-payment-kind></div>
        <div class="field"><label>Valor</label><input name="payments[${index}][amount]" type="text" inputmode="decimal" data-pdv-payment-amount></div>
        <button type="button" class="secondary" data-pdv-remove-payment aria-label="Remover pagamento">Remover</button>
      </div>
      <div class="pdv-card-fields" data-pdv-card-fields hidden>
        <div class="field" data-pdv-installments-field><label>Parcelas</label><select name="payments[${index}][installments]" data-pdv-installments></select></div>
        <div class="field" data-pdv-brand-field><label>Bandeira</label><input name="payments[${index}][card_brand]" maxlength="80" data-pdv-card-brand></div>
      </div>
      <div class="field pdv-reference"><label data-pdv-reference-label>Referência (opcional)</label><input name="payments[${index}][reference]" maxlength="255" data-pdv-reference></div>`;
    const method = row.querySelector('[data-pdv-payment-method]');
    const kind = row.querySelector('[data-pdv-payment-kind]');
    const amount = row.querySelector('[data-pdv-payment-amount]');
    const installments = row.querySelector('[data-pdv-installments]');
    const brand = row.querySelector('[data-pdv-card-brand]');
    const updateCard = () => {
      const selected = rowMethod(row);
      const card = isCardKind(selected?.kind);
      const maxInstallments = Math.max(1, Number(selected?.max_installments) || 1);
      const wanted = Number(installments.value) || Number(installments.dataset.initial) || 1;
      delete installments.dataset.initial;
      const current = Math.min(maxInstallments, Math.max(1, wanted));
      kind.value = selected?.kind || '';
      row.querySelector('[data-pdv-card-fields]').hidden = !card;
      installments.replaceChildren(...Array.from({ length: maxInstallments }, (_, position) => new Option(position === 0 ? '1x à vista' : (position + 1) + 'x', String(position + 1))));
      installments.value = String(current);
      installments.disabled = !card;
      row.querySelector('[data-pdv-installments-field]').hidden = maxInstallments <= 1;
      const brandFromMethod = Boolean(selected?.card_brand);
      row.querySelector('[data-pdv-brand-field]').hidden = !card || brandFromMethod;
      brand.disabled = !card || brandFromMethod;
      const required = Boolean(selected?.requires_reference);
      row.querySelector('[data-pdv-reference-label]').textContent = (selected?.reference_label || 'Referência') + (required ? '' : ' (opcional)');
      totals();
    };
    row.pdvRefreshMethods = () => {
      const previous = method.value;
      fillMethodOptions(method, previous);
      updateCard();
    };
    fillMethodOptions(method, payment.payment_method_id, payment.method || '');
    amount.value = moneyInput(parseMoney(payment.amount));
    installments.dataset.initial = String(payment.installments || 1);
    brand.value = payment.card_brand || '';
    row.querySelector('[data-pdv-reference]').value = payment.reference || payment.transaction_reference || '';
    method.addEventListener('change', updateCard);
    amount.addEventListener('input', totals);
    row.querySelector('[data-pdv-remove-payment]').addEventListener('click', () => { row.remove(); totals(); });
    payments.appendChild(row);
    updateCard();
    return row;
  };
  const renderShortcuts = () => {
    if (!shortcuts) return;
    const methods = availableMethods();
    shortcuts.replaceChildren(...methods.map((method) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'secondary';
      button.dataset.pdvMethod = String(method.id);
      button.textContent = method.name;
      return button;
    }));
    if (!methods.length) {
      const note = document.createElement('span');
      note.className = 'muted';
      note.textContent = clinic && !selectedClinic() ? 'Selecione a clínica para ver as formas de pagamento.' : 'Nenhuma forma de pagamento ativa.';
      shortcuts.appendChild(note);
    }
  };

  const clearResults = () => { results.replaceChildren(); results.hidden = true; activeResults = []; };
  const addSearchItem = (item) => {
    addRow(item);
    search.value = '';
    clearResults();
    createProduct.hidden = true;
    message(lookupStatus, (item.description || 'Item') + ' adicionado.', 'success');
    search.focus();
  };
  const openQuickProduct = (gtin, item = {}) => {
    pendingQuickProductGtin = gtin;
    quickProductCode.textContent = gtin;
    quickProductName.value = item.description || '';
    quickProductPrice.value = parseMoney(item.unit_price) > 0 ? moneyInput(parseMoney(item.unit_price)) : '';
    quickProductStock.value = '1';
    quickProductUnit.value = 'un';
    quickProductCost.value = '';
    message(quickProductStatus, 'Informe preço e quantidade disponível para controlar o estoque.');
    createProduct.href = form.dataset.productCreateUrl.replace('__GTIN__', encodeURIComponent(gtin));
    createProduct.hidden = false;
    quickProductDialog.showModal();
    (quickProductName.value ? quickProductPrice : quickProductName).focus();
  };
  const saveQuickProduct = async () => {
    const button = find('[data-pdv-save-quick-product]');
    const payload = {
      clinic_id: selectedClinic() || null,
      gtin: pendingQuickProductGtin,
      name: quickProductName.value.trim(),
      sale_price: quickProductPrice.value,
      stock_quantity: quickProductStock.value,
      unit: quickProductUnit.value,
      cost_price: quickProductCost.value,
    };
    if (!payload.name || parseMoney(payload.sale_price) <= 0 || quantity(payload.stock_quantity) <= 0) {
      message(quickProductStatus, 'Informe nome, preço de venda e estoque inicial.', 'warning');
      return;
    }
    button.disabled = true;
    message(quickProductStatus, 'Salvando produto...');
    try {
      const response = await fetch(form.dataset.quickProductUrl, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value,
        },
        body: JSON.stringify(payload),
      });
      const data = await response.json();
      if (response.status === 409 && data.item) {
        quickProductDialog.close();
        addSearchItem(data.item);
        return;
      }
      if (!response.ok) {
        const errors = data.errors ? Object.values(data.errors).flat() : [];
        throw new Error(errors[0] || data.message || 'Não foi possível cadastrar o produto.');
      }
      quickProductDialog.close();
      addSearchItem(data.item);
      message(lookupStatus, data.message, 'success');
    } catch (error) {
      message(quickProductStatus, error.message || 'Não foi possível cadastrar o produto.', 'error');
    } finally {
      button.disabled = false;
    }
  };
  const quickSearch = async () => {
    const term = search.value.trim();
    const serial = ++searchSerial;
    if (term.length < 2) { clearResults(); return; }
    if (clinic && !clinic.value) { message(lookupStatus, 'Selecione uma clínica.', 'warning'); return; }
    try {
      const url = new URL(form.dataset.searchUrl, window.location.origin);
      url.searchParams.set('q', term);
      if (clinic) url.searchParams.set('clinic_id', clinic.value);
      const response = await fetch(url, { headers: { Accept: 'application/json' } });
      const data = await response.json();
      if (serial !== searchSerial || term !== search.value.trim()) return;
      if (!response.ok) throw new Error(data.message || 'Busca indisponível.');
      activeResults = data.items || [];
      results.replaceChildren();
      activeResults.forEach((item) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.setAttribute('role', 'option');
        button.className = 'pdv-result';
        const name = document.createElement('strong');
        name.textContent = item.description;
        const detail = document.createElement('span');
        detail.textContent = [item.type === 'service' ? 'Serviço' : 'Produto', item.sku || item.gtin || item.barcode || '', money(parseMoney(item.unit_price)), item.type === 'product' ? 'Estoque: ' + item.stock_quantity : ''].filter(Boolean).join(' · ');
        button.append(name, detail);
        button.addEventListener('click', () => addSearchItem(item));
        results.appendChild(button);
      });
      results.hidden = activeResults.length === 0;
      message(lookupStatus, activeResults.length ? '' : 'Nenhum item encontrado.', activeResults.length ? '' : 'warning');
    } catch (error) {
      message(lookupStatus, error.message || 'Busca indisponível.', 'error');
    }
  };
  const lookupBarcode = async () => {
    const gtin = search.value.replace(/\D/g, '');
    if (gtin.length < 8) return quickSearch();
    if (clinic && !clinic.value) { message(lookupStatus, 'Selecione uma clínica.', 'warning'); return; }
    const serial = ++searchSerial;
    clearResults();
    message(lookupStatus, 'Consultando código...');
    try {
      const url = withClinic(form.dataset.lookupUrl.replace('__GTIN__', encodeURIComponent(gtin)));
      const response = await fetch(url, { headers: { Accept: 'application/json' } });
      const data = await response.json();
      if (serial !== searchSerial || search.value.replace(/\D/g, '') !== gtin) return;
      if (!response.ok || !data.found) {
        message(lookupStatus, data.message || 'Produto não encontrado.', 'warning');
        if (data.manual_allowed !== false) openQuickProduct(gtin, data.item || {});
        return;
      }
      if (data.mode === 'catalog') {
        message(lookupStatus, data.message, 'warning');
        openQuickProduct(gtin, data.item || {});
        return;
      }
      addSearchItem(data.item);
      const warnings = Array.isArray(data.warnings) ? data.warnings : [];
      message(lookupStatus, [data.message, ...warnings].filter(Boolean).join(' '), warnings.length || data.mode === 'catalog' ? 'warning' : 'success');
    } catch {
      message(lookupStatus, 'Consulta indisponível. Faça o cadastro rápido para continuar.', 'warning');
      openQuickProduct(gtin);
    }
  };
  // Receiving requires the operator's open cash session in the sale clinic.
  const cashClinicId = () => selectedClinic() || form.dataset.userClinicId || '';
  const currentCash = () => cashSessions[cashClinicId()] || null;
  const renderCash = () => {
    if (!cashLabel) return;
    const session = currentCash();
    const needsClinic = Boolean(clinic) && !selectedClinic();
    cashLabel.textContent = session
      ? 'Caixa ' + session.code + ' aberto desde ' + session.opened_at
      : (needsClinic ? 'Selecione a clínica para ver o caixa' : 'Caixa fechado');
    cashLink.hidden = !session;
    if (session) cashLink.href = session.url;
    cashOpenButton.hidden = Boolean(session) || needsClinic;
    cashOpenButton.disabled = isQuoteMode();
  };
  const openCashDialog = (then = null) => {
    if (!cashClinicId()) { message(checkoutStatus, 'Selecione uma clínica.', 'warning'); return; }
    afterCashOpen = then;
    const suggested = Number(suggestedOpenings[cashClinicId()] || 0);
    cashOpening.value = moneyInput(Math.round(suggested * 100));
    message(cashStatus, '');
    if (dialog.open) dialog.close();
    cashDialog.showModal();
    cashOpening.select();
  };
  const confirmCashOpen = async () => {
    const clinicId = cashClinicId();
    if (!clinicId) { message(cashStatus, 'Selecione uma clínica.', 'warning'); return; }
    cashConfirm.disabled = true;
    message(cashStatus, 'Abrindo caixa...');
    try {
      const response = await fetch(form.dataset.cashOpenUrl, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value,
        },
        body: JSON.stringify({
          clinic_id: clinic ? clinicId : null,
          opening_amount: (parseMoney(cashOpening.value) / 100).toFixed(2),
        }),
      });
      const data = await response.json().catch(() => ({}));
      if (!response.ok) {
        const errors = data.errors ? Object.values(data.errors).flat() : [];
        throw new Error(errors[0] || data.message || 'Não foi possível abrir o caixa.');
      }
      cashSessions[clinicId] = data.session;
      renderCash();
      cashDialog.close();
      message(checkoutStatus, data.message || 'Caixa aberto.', 'success');
      const next = afterCashOpen;
      afterCashOpen = null;
      if (next) next();
    } catch (error) {
      message(cashStatus, error.message || 'Não foi possível abrir o caixa.', 'error');
    } finally {
      cashConfirm.disabled = false;
    }
  };
  const openPayment = () => {
    if (isQuoteMode()) return;
    if (totals().total <= 0 || rows().length === 0) {
      message(checkoutStatus, 'Inclua um item com valor antes de receber.', 'warning');
      return;
    }
    if (!currentCash()) {
      openCashDialog(openPayment);
      return;
    }
    if (paymentRows().length === 0) addPayment();
    dialog.showModal();
    paymentRows()[0]?.querySelector('[data-pdv-payment-method]')?.focus();
  };
  const finish = () => {
    if (isQuoteMode()) return;
    if (!currentCash()) { openCashDialog(openPayment); return; }
    const { total, paid, balance, change } = totals();
    if (total <= 0 || rows().length === 0) { message(paymentStatus, 'Inclua itens com valor.', 'warning'); return; }
    if (paymentRows().some((row) => !row.querySelector('[data-pdv-payment-method]').value || parseMoney(row.querySelector('[data-pdv-payment-amount]').value) <= 0)) {
      message(paymentStatus, 'Informe a forma e o valor de cada pagamento.', 'warning'); return;
    }
    const cash = paymentRows().filter((row) => rowKind(row) === 'cash')
      .reduce((sum, row) => sum + parseMoney(row.querySelector('[data-pdv-payment-amount]').value), 0);
    if (balance > 0) { message(paymentStatus, 'Faltam ' + money(balance) + ' para concluir.', 'warning'); return; }
    if (change > cash) { message(paymentStatus, 'Troco só pode sair de valor recebido em dinheiro.', 'warning'); return; }
    const missingReference = paymentRows().find((row) => rowMethod(row)?.requires_reference && !row.querySelector('[data-pdv-reference]').value.trim());
    if (missingReference) {
      const selected = rowMethod(missingReference);
      message(paymentStatus, 'Informe o ' + (selected.reference_label || 'NSU') + ' de ' + selected.name + '.', 'warning');
      missingReference.querySelector('[data-pdv-reference]').focus();
      return;
    }
    if (!form.reportValidity()) return;
    status.value = 'completed';
    normalizeRows();
    find('[data-pdv-finish]').disabled = true;
    form.requestSubmit();
  };
  try { Object.values(JSON.parse(form.dataset.oldItems || '[]')).forEach(addRow); } catch { /* no old cart */ }
  renderShortcuts();
  try { Object.values(JSON.parse(form.dataset.oldPayments || '[]')).forEach((payment) => addPayment(payment)); } catch { /* no old payments */ }
  if (new URLSearchParams(window.location.search).get('scan')) search.value = form.dataset.initialScan || '';
  totals();
  if (search.value) lookupBarcode();
  if (paymentRows().length && form.dataset.pdvMode !== 'quote' && currentCash()) dialog.showModal();

  search.addEventListener('input', () => {
    window.clearTimeout(searchTimer);
    ++searchSerial;
    clearResults();
    if (!search.value.trim()) { ++searchSerial; clearResults(); return; }
    if (/^\d{12,}$/.test(search.value.trim())) {
      searchTimer = window.setTimeout(lookupBarcode, 350);
    } else {
      searchTimer = window.setTimeout(quickSearch, 180);
    }
  });
  search.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter') return;
    event.preventDefault();
    window.clearTimeout(searchTimer);
    if (/^\d{8,}$/.test(search.value.trim())) lookupBarcode();
    else if (activeResults.length) addSearchItem(activeResults[0]);
    else quickSearch();
  });
  find('[data-pdv-search-button]').addEventListener('click', () => {
    if (/^\d{8,}$/.test(search.value.trim())) lookupBarcode(); else quickSearch();
  });
  find('[data-pdv-add-custom]').addEventListener('click', () => addRow({ type: 'custom', quantity: 1, unit_price: 0 }).querySelector('[data-pdv-description]').focus());
  [discount, additions].forEach((input) => input.addEventListener('input', totals));
  find('[data-pdv-open-payment]')?.addEventListener('click', openPayment);
  cashOpenButton?.addEventListener('click', () => openCashDialog());
  cashConfirm?.addEventListener('click', confirmCashOpen);
  cashOpening?.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') { event.preventDefault(); confirmCashOpen(); }
  });
  find('[data-pdv-close-cash]')?.addEventListener('click', () => { afterCashOpen = null; cashDialog.close(); });
  find('[data-pdv-close-payment]').addEventListener('click', () => dialog.close());
  find('[data-pdv-close-quick-product]').addEventListener('click', () => { quickProductDialog.close(); search.focus(); });
  find('[data-pdv-save-quick-product]').addEventListener('click', saveQuickProduct);
  quickProductDialog.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') { event.preventDefault(); saveQuickProduct(); }
  });
  find('[data-pdv-add-payment]').addEventListener('click', () => {
    const row = addPayment({ amount: (totals().balance / 100).toFixed(2) });
    row.querySelector('[data-pdv-payment-method]').focus();
  });
  shortcuts?.addEventListener('click', (event) => {
    const button = event.target.closest('[data-pdv-method]');
    if (!button) return;
    const row = paymentRows().find((entry) => !entry.querySelector('[data-pdv-payment-method]').value) || addPayment();
    row.querySelector('[data-pdv-payment-method]').value = button.dataset.pdvMethod;
    row.querySelector('[data-pdv-payment-method]').dispatchEvent(new Event('change'));
    row.querySelector('[data-pdv-payment-amount]').value = moneyInput(totals().balance);
    row.querySelector('[data-pdv-payment-amount]').focus();
    totals();
  });
  find('[data-pdv-finish]').addEventListener('click', finish);
  find('[data-pdv-suspend]')?.addEventListener('click', (event) => {
    if (!rows().length) { event.preventDefault(); message(checkoutStatus, 'Inclua um item para suspender a venda.', 'warning'); }
  });
  saveQuote?.addEventListener('click', (event) => {
    if (!rows().length) {
      event.preventDefault();
      message(checkoutStatus, 'Inclua um item para salvar o orçamento.', 'warning');
      return;
    }
    if (!form.reportValidity()) event.preventDefault();
  });
  form.addEventListener('submit', () => {
    if (status.value === 'draft') {
      paymentRows().forEach((row) => {
        if (!row.querySelector('[data-pdv-payment-method]').value || parseMoney(row.querySelector('[data-pdv-payment-amount]').value) <= 0) row.remove();
      });
    }
    normalizeRows();
  });
  const customer = find('[data-pdv-customer]');
  const customerSummary = find('[data-pdv-customer-summary]');
  const syncCustomer = () => {
    const clinicId = selectedClinic();
    [tutor, patient].forEach((select) => {
      Array.from(select.options).forEach((option) => {
        option.hidden = Boolean(clinicId && option.dataset.clinicId && option.dataset.clinicId !== clinicId);
      });
      if (select.selectedOptions[0]?.hidden) select.value = '';
    });
    customerSummary.firstChild.textContent = tutor.value ? tutor.selectedOptions[0].textContent + ' · Alterar cliente ' : 'Consumidor não identificado · Identificar cliente ';
  };
  tutor.addEventListener('change', () => { syncCustomer(); syncDelivery(true); });
  clinic?.addEventListener('change', () => {
    ++searchSerial;
    clearResults();
    syncCustomer();
    renderShortcuts();
    renderCash();
    paymentRows().forEach((row) => row.pdvRefreshMethods?.());
  });
  syncCustomer();

  const tutorAddress = () => tutor.selectedOptions[0]?.dataset.address || '';
  function syncDelivery(tutorChanged = false) {
    if (!saleType) return;
    const show = hasDelivery();
    if (delivery) delivery.hidden = !show;
    if (deliveryFeeField) deliveryFeeField.hidden = !show;
    if (show && deliveryAddress) {
      const address = tutorAddress();
      const untouched = !deliveryAddress.value.trim() || deliveryAddress.dataset.autofilled === '1';
      if (address && untouched && (tutorChanged || !deliveryAddress.value.trim())) {
        deliveryAddress.value = address;
        deliveryAddress.dataset.autofilled = '1';
      }
    }
    totals();
  }
  deliveryAddress?.addEventListener('input', () => { deliveryAddress.dataset.autofilled = '0'; });
  saleType?.addEventListener('change', () => syncDelivery(false));
  deliveryFee?.addEventListener('input', totals);
  syncDelivery(false);

  const setMode = (mode) => {
    const quote = mode === 'quote';
    form.dataset.pdvMode = quote ? 'quote' : 'sale';
    if (modeInput) modeInput.value = form.dataset.pdvMode;
    form.querySelectorAll('[data-pdv-sale-only]').forEach((node) => {
      node.hidden = quote;
      if (node.matches('button')) node.disabled = quote;
    });
    form.querySelectorAll('[data-pdv-quote-only]').forEach((node) => {
      node.hidden = !quote;
      if (node.matches('button')) node.disabled = !quote;
    });
    if (validUntil) validUntil.disabled = !quote;
    form.querySelectorAll('[data-pdv-mode-button]').forEach((button) => {
      const active = button.dataset.pdvModeButton === form.dataset.pdvMode;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
    if (summaryLabel) summaryLabel.textContent = quote ? 'Orçamento em andamento' : 'Venda em andamento';
    if (quote && dialog.open) dialog.close();
    if (quote && cashDialog?.open) cashDialog.close();
    renderCash();
    message(checkoutStatus, '');
  };
  form.querySelectorAll('[data-pdv-mode-button]').forEach((button) => button.addEventListener('click', () => {
    setMode(button.dataset.pdvModeButton);
    search.focus();
  }));
  setMode(form.dataset.pdvMode);
  document.addEventListener('keydown', (event) => {
    if (!['F2', 'F4', 'F6', 'F8', 'F10'].includes(event.key)) return;
    event.preventDefault();
    if (event.key === 'F2') { if (dialog.open) dialog.close(); search.focus(); }
    if (event.key === 'F4') { customer.open = true; tutor.focus(); }
    if (event.key === 'F6') discount.focus();
    if (event.key === 'F8') openPayment();
    if (event.key === 'F10' && !isQuoteMode()) { if (!dialog.open) openPayment(); else finish(); }
  });
});
