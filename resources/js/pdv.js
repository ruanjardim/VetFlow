import { PdvOfflineStore, readOfflineScaleLabel, reserveOfflineStock, searchOfflineCatalog } from './pdv-offline.js';

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
  const scannerState = find('[data-pdv-scanner-state]');
  const scannerLabel = find('[data-pdv-scanner-label]');
  const scannerDetail = find('[data-pdv-scanner-detail]');
  const headerClinic = document.querySelector('[data-pdv-header-clinic]');
  const clock = document.querySelector('[data-pdv-clock]');
  const fullscreenButton = document.querySelector('[data-pdv-fullscreen]');
  const closeButton = document.querySelector('[data-pdv-close]');
  const connectionState = document.querySelector('[data-pdv-connection-state]');
  const connectionLabel = document.querySelector('[data-pdv-connection-label]');
  const connectivityAlert = find('[data-pdv-connectivity-alert]');
  const connectivityMessage = find('[data-pdv-connectivity-message]');
  const reauthenticateLink = find('[data-pdv-reauthenticate]');
  const offlineQueueButton = document.querySelector('[data-pdv-offline-queue]');
  const offlineQueueCount = document.querySelector('[data-pdv-offline-count]');
  const offlineDialog = find('[data-pdv-offline-dialog]');
  const offlineSummary = find('[data-pdv-offline-summary]');
  const offlineList = find('[data-pdv-offline-list]');
  const offlineSyncButton = find('[data-pdv-offline-sync]');
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
  const customerBalance = find('[data-pdv-customer-balance]');
  const payLater = find('[data-pdv-pay-later]');
  const changeAsCredit = find('[data-pdv-change-as-credit]');
  const customerOptionsHint = find('[data-pdv-customer-options-hint]');
  const CREDIT = 'customer_credit';
  let customerCredit = 0;
  let balanceSerial = 0;
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
  let scannerResetTimer;
  let scannerBuffer = '';
  let scannerBufferTimer;
  let pdvConnection = 'checking';
  let lastHeartbeatAt = 0;
  let heartbeatTimer;
  let offlineStore = null;
  let offlineCatalog = null;
  let offlineInitializing = false;
  let offlineSyncing = false;
  let offlineStorageError = '';

  const connectionCopy = {
    checking: ['Verificando', 'Conferindo a conexão segura com o VetFlow.'],
    online: ['Online', 'PDV conectado e sessão protegida.'],
    offline: ['Sem conexão', 'Sem conexão com o VetFlow. Se o catálogo local estiver pronto, a venda pode entrar na fila criptografada.'],
    expired: ['Sessão encerrada', 'Sua sessão encerrou. O carrinho continua nesta tela. Reabra a sessão em outra aba e volte ao PDV.'],
    unavailable: ['Servidor indisponível', 'O VetFlow não respondeu. Se o catálogo local estiver pronto, a venda pode entrar na fila criptografada.'],
  };
  const offlineCatalogFresh = () => {
    const generatedAt = Date.parse(offlineCatalog?.generated_at || '');
    return Boolean(
      offlineCatalog?.complete
      && typeof offlineCatalog?.offline_context_token === 'string'
      && offlineCatalog.offline_context_token.length > 0
      && Number.isFinite(generatedAt)
      && Date.now() - generatedAt <= 24 * 60 * 60 * 1000
    );
  };
  const offlineQueueAvailable = () => Boolean(
    offlineStore
    && offlineCatalogFresh()
    && String(offlineCatalog?.clinic?.id || '') === String(clinic?.value || form.dataset.userClinicId || '')
  );
  const refreshFinishAvailability = () => {
    const finishButton = find('[data-pdv-finish]');
    if (!finishButton) return;
    const offlineState = pdvConnection === 'offline' || pdvConnection === 'unavailable';
    finishButton.disabled = pdvConnection === 'checking'
      || pdvConnection === 'expired'
      || (offlineState && !offlineQueueAvailable());
    const label = offlineState && offlineQueueAvailable() ? 'Salvar na fila offline' : 'Finalizar venda';
    const textNode = Array.from(finishButton.childNodes).find((node) => node.nodeType === Node.TEXT_NODE);
    if (textNode) textNode.textContent = label + ' ';
  };
  const setConnectionState = (state, detail = '') => {
    pdvConnection = state;
    const copy = connectionCopy[state] || connectionCopy.unavailable;
    if (connectionState) connectionState.className = 'pdv-connection is-' + state;
    if (connectionLabel) connectionLabel.textContent = copy[0];
    if (connectivityMessage) connectivityMessage.textContent = detail || copy[1];
    if (connectivityAlert) connectivityAlert.hidden = state === 'online' || state === 'checking';
    if (reauthenticateLink) reauthenticateLink.hidden = state !== 'expired';
    refreshFinishAvailability();
    renderCash();
    if (state === 'online' && offlineStore && !offlineSyncing) {
      window.setTimeout(() => syncOfflineQueue(), 0);
    }
  };
  const isLoginResponse = (response) => response.status === 401
    || response.status === 419
    || (response.redirected && new URL(response.url, window.location.origin).pathname === new URL(form.dataset.sessionLoginUrl, window.location.origin).pathname);
  const pdvFetch = async (url, options = {}) => {
    try {
      const response = await fetch(url, options);
      if (isLoginResponse(response)) {
        setConnectionState('expired');
        throw new Error('Sua sessão encerrou. Reabra a sessão em outra aba; o carrinho foi mantido nesta tela.');
      }
      if (response.status >= 500) setConnectionState('unavailable');
      else setConnectionState('online');
      return response;
    } catch (error) {
      if (pdvConnection !== 'expired' && pdvConnection !== 'unavailable') setConnectionState('offline');
      throw error;
    }
  };
  const heartbeat = async (force = false) => {
    const now = Date.now();
    if (!force && now - lastHeartbeatAt < 30000) return pdvConnection === 'online';
    lastHeartbeatAt = now;
    if (!navigator.onLine) {
      setConnectionState('offline');
      return false;
    }
    try {
      const response = await fetch(form.dataset.sessionHeartbeatUrl, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
        cache: 'no-store',
      });
      if (isLoginResponse(response)) {
        setConnectionState('expired');
        return false;
      }
      setConnectionState(response.ok ? 'online' : 'unavailable');
      return response.ok;
    } catch {
      setConnectionState('offline');
      return false;
    }
  };

  const scannerMessages = {
    ready: ['Leitura pronta', 'Aponte o leitor para o código de barras ou pesquise pelo nome.'],
    reading: ['Lendo código', 'Consultando produto e estoque desta unidade...'],
    success: ['Produto adicionado', 'Pronto para ler o próximo código.'],
    warning: ['Confira a leitura', 'O código precisa de atenção antes de continuar.'],
    error: ['Leitura indisponível', 'Tente novamente ou pesquise o produto pelo nome.'],
  };
  const setScannerState = (state = 'ready', detail = '', autoReset = false) => {
    if (!scannerState) return;
    window.clearTimeout(scannerResetTimer);
    scannerState.className = 'pdv-scanner-state is-' + state;
    scannerLabel.textContent = scannerMessages[state]?.[0] || scannerMessages.ready[0];
    scannerDetail.textContent = detail || scannerMessages[state]?.[1] || scannerMessages.ready[1];
    if (autoReset) {
      scannerResetTimer = window.setTimeout(() => setScannerState('ready'), 2600);
    }
  };
  const feedbackTone = (success = true) => {
    try {
      const Audio = window.AudioContext || window.webkitAudioContext;
      if (!Audio) return;
      const context = new Audio();
      const oscillator = context.createOscillator();
      const gain = context.createGain();
      oscillator.type = 'sine';
      oscillator.frequency.value = success ? 880 : 240;
      gain.gain.setValueAtTime(0.035, context.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.0001, context.currentTime + (success ? 0.08 : 0.14));
      oscillator.connect(gain);
      gain.connect(context.destination);
      oscillator.start();
      oscillator.stop(context.currentTime + (success ? 0.08 : 0.14));
      oscillator.addEventListener('ended', () => context.close());
    } catch { /* sound feedback is optional */ }
  };

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
    const serviceOrderItemId = String(item.service_order_item_id || '');
    const existing = serviceOrderItemId ? null : productId
      ? rows().find((row) => row.dataset.type === 'product' && row.dataset.productId === productId)
      : serviceId ? rows().find((row) => row.dataset.type === 'service' && row.dataset.serviceId === serviceId) : null;
    if (existing) {
      const qty = existing.querySelector('[data-pdv-quantity]');
      qty.value = String(Math.round((quantity(qty.value) + quantity(item.quantity || 1)) * 1000) / 1000);
      totals();
      rows().forEach((entry) => entry.classList.remove('is-latest'));
      existing.classList.add('is-latest');
      existing.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
      return existing;
    }
    const index = nextItem++;
    const row = document.createElement('article');
    row.className = 'pdv-cart-row';
    row.dataset.pdvRow = '';
    row.dataset.type = type;
    row.dataset.productId = productId;
    row.dataset.serviceId = serviceId;
    row.dataset.serviceOrderItemId = serviceOrderItemId;
    if (item.stock_quantity !== undefined) row.dataset.stock = String(item.stock_quantity);
    row.innerHTML = `
      <div class="pdv-row-top"><div class="pdv-row-identity"><span data-pdv-kind></span><small data-pdv-code></small></div><button type="button" class="secondary" data-pdv-remove aria-label="Remover item">Remover</button></div>
      <input type="hidden" name="items[${index}][type]">
      <input type="hidden" name="items[${index}][product_id]">
      <input type="hidden" name="items[${index}][petshop_service_id]">
      <input type="hidden" name="items[${index}][service_order_item_id]">
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
    row.querySelector('[name$="[service_order_item_id]"]').value = serviceOrderItemId;
    row.querySelector('[data-pdv-kind]').textContent = type === 'product' ? 'Produto' : type === 'service' ? 'Serviço' : 'Avulso';
    row.querySelector('[data-pdv-code]').textContent = item.gtin || item.barcode || item.sku || '';
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
    rows().forEach((entry) => entry.classList.toggle('is-latest', entry === row));
    row.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    return row;
  };

  // Payment methods of the selected clinic (one per card machine and type).
  // A global user sees none until a clinic is chosen.
  // The customer's credit works as one more payment method while the
  // identified customer has some.
  const creditMethod = () => ({
    id: CREDIT,
    name: 'Crédito do cliente (' + brl.format(customerCredit) + ')',
    kind: CREDIT,
    max_installments: 1,
    requires_reference: false,
    reference_label: 'Referência',
  });
  const availableMethods = () => {
    if (clinic && !selectedClinic()) return [];
    const methods = paymentMethods.filter((method) => !clinic || String(method.clinic_id) === selectedClinic());
    return tutor.value && customerCredit > 0 ? [creditMethod(), ...methods] : methods;
  };
  const methodById = (id) => (String(id || '') === CREDIT && customerCredit > 0
    ? creditMethod()
    : paymentMethods.find((method) => String(method.id) === String(id || '')));
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
    row.dataset.wantedMethod = payment.method === CREDIT ? CREDIT : String(payment.payment_method_id || '');
    row.pdvRefreshMethods = () => {
      const previous = method.value || row.dataset.wantedMethod || '';
      fillMethodOptions(method, previous);
      updateCard();
    };
    fillMethodOptions(method, payment.method === CREDIT ? CREDIT : payment.payment_method_id, payment.method || '');
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
      button.className = 'pdv-method-card';
      button.dataset.pdvMethod = String(method.id);
      button.dataset.kind = method.kind;
      const name = document.createElement('span');
      name.textContent = method.name;
      const hint = document.createElement('small');
      hint.textContent = method.kind === 'cash' ? 'Dinheiro e troco'
        : method.kind === 'pix' ? 'Confirmação manual'
          : method.kind === 'debit_card' ? 'Débito na maquininha'
            : method.kind === 'credit_card' ? 'Crédito e parcelas'
              : method.kind === CREDIT ? 'Usar saldo disponível'
                : 'Selecionar pagamento';
      button.append(name, hint);
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
  const renderSearchResults = (items, offline = false) => {
    activeResults = items || [];
    results.replaceChildren();
    activeResults.forEach((item) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.setAttribute('role', 'option');
      button.className = 'pdv-result';
      const name = document.createElement('strong');
      name.textContent = item.description;
      const detail = document.createElement('span');
      detail.textContent = [
        offline ? 'Catálogo local' : (item.type === 'service' ? 'Serviço' : 'Produto'),
        item.sku || item.gtin || item.barcode || '',
        money(parseMoney(item.unit_price)),
        item.type === 'product' ? (offline ? 'Estoque do último sincronismo: ' : 'Estoque: ') + item.stock_quantity : '',
      ].filter(Boolean).join(' · ');
      button.append(name, detail);
      button.addEventListener('click', () => addSearchItem(item, offline ? {
        state: 'warning',
        detail: 'Item adicionado do catálogo local; o estoque será conferido na sincronização.',
        autoReset: false,
      } : {}));
      results.appendChild(button);
    });
    results.hidden = activeResults.length === 0;
    message(
      lookupStatus,
      activeResults.length ? (offline ? 'Resultado do catálogo local criptografado.' : '') : 'Nenhum item encontrado.',
      activeResults.length ? (offline ? 'warning' : '') : 'warning'
    );
  };
  const addSearchItem = (item, scannerFeedback = {}) => {
    const feedbackState = scannerFeedback.state || 'success';
    addRow(item);
    search.value = '';
    clearResults();
    createProduct.hidden = true;
    message(lookupStatus, (item.description || 'Item') + ' adicionado.', 'success');
    setScannerState(
      feedbackState,
      scannerFeedback.detail || (item.description || 'Produto') + ' entrou no carrinho.',
      scannerFeedback.autoReset ?? feedbackState === 'success',
    );
    feedbackTone(scannerFeedback.successTone ?? feedbackState === 'success');
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
    setScannerState('warning', 'Código ' + gtin + ' ainda não está cadastrado. Complete o cadastro rápido.');
    feedbackTone(false);
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
      const response = await pdvFetch(form.dataset.quickProductUrl, {
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
      const response = await pdvFetch(url, { headers: { Accept: 'application/json' } });
      const data = await response.json();
      if (serial !== searchSerial || term !== search.value.trim()) return;
      if (!response.ok) throw new Error(data.message || 'Busca indisponível.');
      renderSearchResults(data.items || []);
    } catch (error) {
      const localItems = searchOfflineCatalog(offlineCatalog, term);
      if (localItems.length || offlineQueueAvailable()) {
        renderSearchResults(localItems, true);
        return;
      }
      message(lookupStatus, error.message || 'Busca indisponível.', 'error');
    }
  };
  const lookupBarcode = async () => {
    const gtin = search.value.replace(/\D/g, '');
    if (gtin.length < 8) return quickSearch();
    if (clinic && !clinic.value) {
      message(lookupStatus, 'Selecione uma clínica.', 'warning');
      setScannerState('warning', 'Selecione a unidade antes de ler produtos.');
      feedbackTone(false);
      return;
    }
    const serial = ++searchSerial;
    clearResults();
    message(lookupStatus, 'Consultando código...');
    setScannerState('reading', 'Código ' + gtin + ' recebido. Consultando o cadastro...');
    try {
      const url = withClinic(form.dataset.lookupUrl.replace('__GTIN__', encodeURIComponent(gtin)));
      const response = await pdvFetch(url, { headers: { Accept: 'application/json' } });
      const data = await response.json();
      if (serial !== searchSerial || search.value.replace(/\D/g, '') !== gtin) return;
      if (!response.ok || !data.found) {
        message(lookupStatus, data.message || 'Produto não encontrado.', 'warning');
        if (data.manual_allowed !== false) openQuickProduct(gtin, data.item || {});
        else {
          setScannerState('warning', data.message || 'Produto não encontrado nesta unidade.');
          feedbackTone(false);
        }
        return;
      }
      if (data.mode === 'catalog') {
        message(lookupStatus, data.message, 'warning');
        openQuickProduct(gtin, data.item || {});
        return;
      }
      const warnings = Array.isArray(data.warnings) ? data.warnings : [];
      addSearchItem(data.item, warnings.length ? {
        state: 'warning',
        detail: warnings.join(' '),
        autoReset: false,
        successTone: false,
      } : {});
      message(lookupStatus, [data.message, ...warnings].filter(Boolean).join(' '), warnings.length || data.mode === 'catalog' ? 'warning' : 'success');
    } catch {
      const scaleReading = readOfflineScaleLabel(offlineCatalog, gtin);
      if (scaleReading.recognized && !scaleReading.valid) {
        message(lookupStatus, scaleReading.message, 'warning');
        setScannerState('warning', scaleReading.message);
        feedbackTone(false);
        return;
      }
      const localItem = scaleReading.valid ? scaleReading.item : searchOfflineCatalog(offlineCatalog, gtin, 1)[0];
      if (localItem) {
        addSearchItem(localItem, {
          state: 'warning',
          detail: localItem.scale_label
            ? `Etiqueta lida offline: ${quantity(localItem.quantity).toLocaleString('pt-BR', { minimumFractionDigits: 3 })} kg. O estoque será conferido na sincronização.`
            : 'Código encontrado no catálogo local; o estoque será conferido na sincronização.',
          autoReset: false,
        });
        return;
      }
      message(lookupStatus, 'Código ausente do catálogo local. Aguarde a conexão para cadastrar ou consultar.', 'warning');
      setScannerState('error', 'Não foi possível encontrar este código no catálogo local.');
      feedbackTone(false);
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
    cashOpenButton.disabled = isQuoteMode() || pdvConnection !== 'online';
  };

  const refreshOfflineQueue = async () => {
    if (!offlineStore) return [];
    const queue = await offlineStore.queuedSales();
    const conflicts = queue.filter((sale) => sale.localState === 'conflict').length;
    if (offlineQueueButton) offlineQueueButton.hidden = queue.length === 0;
    if (offlineQueueCount) offlineQueueCount.textContent = String(queue.length);
    if (offlineSummary) {
      offlineSummary.textContent = queue.length === 0
        ? 'Nenhuma venda aguardando sincronização.'
        : `${queue.length} venda${queue.length === 1 ? '' : 's'} aguardando o servidor${conflicts ? ` · ${conflicts} precisa${conflicts === 1 ? '' : 'm'} de revisão` : ''}.`;
    }
    if (offlineList) {
      offlineList.replaceChildren(...queue.map((sale) => {
        const article = document.createElement('article');
        article.className = 'pdv-offline-item is-' + (sale.localState || 'pending');
        const top = document.createElement('div');
        const title = document.createElement('strong');
        title.textContent = sale.clinicName || 'Venda offline';
        const amount = document.createElement('strong');
        amount.textContent = money(Number(sale.total || 0));
        top.append(title, amount);
        const detail = document.createElement('span');
        const captured = new Date(sale.capturedAt);
        detail.textContent = `${sale.itemCount || 0} item${Number(sale.itemCount) === 1 ? '' : 's'} · ${Number.isNaN(captured.getTime()) ? sale.capturedAt : captured.toLocaleString('pt-BR')}`;
        const state = document.createElement('small');
        state.textContent = sale.localState === 'conflict'
          ? `Revisão necessária: ${sale.localDetail || 'o servidor recusou a sincronização.'}`
          : sale.localState === 'syncing' ? 'Sincronizando com o servidor...' : 'Protegida neste navegador e aguardando conexão.';
        article.append(top, detail, state);
        return article;
      }));
    }
    if (offlineSyncButton) offlineSyncButton.disabled = offlineSyncing || queue.length === 0 || pdvConnection !== 'online';

    return queue;
  };

  const loadOfflineCatalog = async () => {
    const clinicId = cashClinicId();
    offlineCatalog = offlineStore && clinicId ? await offlineStore.catalog(clinicId) : null;
    refreshFinishAvailability();
  };

  const applyOfflineSnapshot = async (snapshot) => {
    if (!offlineStore || !snapshot?.clinic?.id) return;
    offlineCatalog = snapshot;
    await offlineStore.saveCatalog(snapshot.clinic.id, snapshot);
    paymentMethods = paymentMethods
      .filter((method) => String(method.clinic_id || '') !== String(snapshot.clinic.id))
      .concat(snapshot.payment_methods || []);
    if (snapshot.cash_session) cashSessions[String(snapshot.clinic.id)] = snapshot.cash_session;
    else delete cashSessions[String(snapshot.clinic.id)];
    renderShortcuts();
    renderCash();
    refreshFinishAvailability();
  };

  const refreshOfflineCatalog = async () => {
    const clinicId = cashClinicId();
    if (!offlineStore || !clinicId || pdvConnection !== 'online') return false;
    const url = new URL(form.dataset.offlineCatalogUrl, window.location.origin);
    url.searchParams.set('clinic_id', clinicId);
    const response = await fetch(url, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
      cache: 'no-store',
    });
    if (isLoginResponse(response)) {
      setConnectionState('expired');
      return false;
    }
    if (!response.ok) return false;
    const snapshot = await response.json();
    await applyOfflineSnapshot(snapshot);
    if (!snapshot.complete) {
      message(checkoutStatus, `O catálogo tem ${snapshot.total_items} itens e excede o limite local de ${snapshot.limit}; venda offline não foi liberada.`, 'warning');
    }

    return snapshot.complete;
  };

  const offlineResponseMessage = (data, fallback) => {
    const errors = data?.errors ? Object.values(data.errors).flat().filter(Boolean) : [];
    return errors[0] || data?.message || fallback;
  };

  const syncOfflineQueue = async (force = false) => {
    if (!offlineStore || offlineSyncing || pdvConnection !== 'online') return;
    offlineSyncing = true;
    let synchronized = 0;
    try {
      const queue = await refreshOfflineQueue();
      for (const sale of queue) {
        if (!force && sale.localState === 'conflict') continue;
        await offlineStore.setSaleState(sale.id, 'syncing');
        await refreshOfflineQueue();
        const payload = new FormData();
        sale.entries.forEach(([name, value]) => payload.append(name, value));
        payload.set('client_request_id', sale.id);
        payload.set('offline_device_id', offlineStore.deviceId);
        payload.set('offline_captured_at', sale.capturedAt);
        payload.set('offline_context_token', sale.contextToken);
        payload.set('clinic_id', sale.clinicId);
        try {
          const response = await fetch(form.dataset.offlineSyncUrl, {
            method: 'POST',
            headers: {
              Accept: 'application/json',
              'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value,
            },
            credentials: 'same-origin',
            body: payload,
          });
          if (isLoginResponse(response)) {
            await offlineStore.setSaleState(sale.id, 'pending', 'Sessão encerrada. Entre novamente para sincronizar.');
            setConnectionState('expired');
            break;
          }
          const data = await response.json().catch(() => ({}));
          if (response.ok && data.status === 'synced') {
            await offlineStore.removeSale(sale.id);
            synchronized += 1;
            continue;
          }
          const detail = offlineResponseMessage(data, 'O servidor recusou esta venda.');
          if (response.status === 409 || response.status === 422) {
            await offlineStore.setSaleState(sale.id, 'conflict', detail);
            continue;
          }
          await offlineStore.setSaleState(sale.id, 'pending', detail);
          if (response.status >= 500 || response.status === 429) break;
        } catch {
          await offlineStore.setSaleState(sale.id, 'pending', 'Conexão interrompida durante a sincronização.');
          setConnectionState('offline');
          break;
        }
      }
      if (synchronized > 0) {
        message(checkoutStatus, `${synchronized} venda${synchronized === 1 ? '' : 's'} offline sincronizada${synchronized === 1 ? '' : 's'} com sucesso.`, 'success');
        await refreshOfflineCatalog();
      }
    } finally {
      offlineSyncing = false;
      await refreshOfflineQueue();
      refreshFinishAvailability();
    }
  };

  const resetAfterOfflineCapture = () => {
    rows().forEach((row) => row.remove());
    paymentRows().forEach((row) => row.remove());
    discount.value = '0,00';
    additions.value = '0,00';
    if (deliveryFee) deliveryFee.value = '0,00';
    if (deliveryAddress) deliveryAddress.value = '';
    const notes = form.querySelector('[name="notes"]');
    if (notes) notes.value = '';
    if (payLater) payLater.checked = false;
    if (changeAsCredit) changeAsCredit.checked = false;
    tutor.value = '';
    patient.value = '';
    customerCredit = 0;
    status.value = 'draft';
    if (dialog.open) dialog.close();
    syncCustomer();
    syncCustomerOptions();
    renderShortcuts();
    totals();
    search.focus();
  };

  const captureOfflineSale = async () => {
    if (!offlineQueueAvailable()) {
      const detail = offlineStorageError
        || (!offlineCatalog?.complete ? 'O catálogo local ainda não está pronto.' : 'O catálogo local venceu; reconecte para atualizá-lo.');
      message(paymentStatus, detail, 'warning');
      return false;
    }
    if (!currentCash()) {
      message(paymentStatus, 'A venda offline exige um caixa já aberto e confirmado pelo servidor.', 'warning');
      return false;
    }
    if (form.querySelector('[name="service_order_id"], [name="pet_package_id"], [name="sale_quote_id"]')) {
      message(paymentStatus, 'Comandas, pacotes e conversões de orçamento precisam ser concluídos online.', 'warning');
      return false;
    }
    if (payLater?.checked || changeAsCredit?.checked || paymentRows().some((row) => rowKind(row) === CREDIT)) {
      message(paymentStatus, 'Fiado, troco em crédito e uso do saldo do cliente precisam de conexão.', 'warning');
      return false;
    }

    const capturedAt = new Date().toISOString();
    const id = window.crypto.randomUUID();
    const entries = Array.from(new FormData(form).entries())
      .filter(([name, value]) => name !== '_token' && typeof value === 'string')
      .map(([name, value]) => [name, value]);
    const sale = {
      id,
      clinicId: cashClinicId(),
      clinicName: clinic?.selectedOptions?.[0]?.textContent || headerClinic?.textContent || 'Unidade',
      capturedAt,
      contextToken: offlineCatalog.offline_context_token,
      itemCount: rows().length,
      total: totals().total,
      entries,
    };

    await offlineStore.queueSale(sale);
    reserveOfflineStock(offlineCatalog, entries);
    await offlineStore.saveCatalog(sale.clinicId, offlineCatalog);
    await refreshOfflineQueue();
    resetAfterOfflineCapture();
    message(checkoutStatus, 'Venda salva na fila criptografada. Ela será sincronizada automaticamente quando o VetFlow voltar.', 'success');

    return true;
  };

  const initializeOfflineMode = async () => {
    if (offlineInitializing || offlineStore) return;
    offlineInitializing = true;
    try {
      offlineStore = await PdvOfflineStore.open();
      offlineStorageError = '';
      await loadOfflineCatalog();
      await refreshOfflineQueue();
      if (pdvConnection === 'online') {
        await refreshOfflineCatalog();
        await syncOfflineQueue();
      }
    } catch (error) {
      offlineStore = null;
      offlineStorageError = error?.message || 'O armazenamento offline não pôde ser iniciado.';
      if (offlineQueueButton) offlineQueueButton.hidden = true;
    } finally {
      offlineInitializing = false;
      refreshFinishAvailability();
    }
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
      const response = await pdvFetch(form.dataset.cashOpenUrl, {
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
  const finish = async () => {
    if (isQuoteMode()) return;
    if (!currentCash()) {
      if (pdvConnection === 'online') openCashDialog(openPayment);
      else message(paymentStatus, 'Sem conexão, só é possível continuar com um caixa que já estava aberto.', 'warning');
      return;
    }
    const { total, paid, balance, change } = totals();
    if (total <= 0 || rows().length === 0) { message(paymentStatus, 'Inclua itens com valor.', 'warning'); return; }
    if (payLater?.checked && tutor.value) {
      paymentRows().forEach((row) => {
        if (!row.querySelector('[data-pdv-payment-method]').value && parseMoney(row.querySelector('[data-pdv-payment-amount]').value) <= 0) row.remove();
      });
    }
    if (paymentRows().some((row) => !row.querySelector('[data-pdv-payment-method]').value || parseMoney(row.querySelector('[data-pdv-payment-amount]').value) <= 0)) {
      message(paymentStatus, 'Informe a forma e o valor de cada pagamento.', 'warning'); return;
    }
    const cash = paymentRows().filter((row) => rowKind(row) === 'cash')
      .reduce((sum, row) => sum + parseMoney(row.querySelector('[data-pdv-payment-amount]').value), 0);
    const fiado = Boolean(payLater?.checked && tutor.value);
    const keepChange = Boolean(changeAsCredit?.checked && tutor.value);
    if (balance > 0 && !fiado) { message(paymentStatus, 'Faltam ' + money(balance) + ' para concluir. Com o cliente identificado, dá para marcar "receber depois".', 'warning'); return; }
    if (change > cash && !keepChange) { message(paymentStatus, 'Troco só pode sair de valor recebido em dinheiro.', 'warning'); return; }
    const creditUsed = paymentRows().filter((row) => rowKind(row) === CREDIT)
      .reduce((sum, row) => sum + parseMoney(row.querySelector('[data-pdv-payment-amount]').value), 0);
    if (creditUsed > Math.round(customerCredit * 100)) { message(paymentStatus, 'O cliente tem ' + brl.format(customerCredit) + ' de crédito.', 'warning'); return; }
    if (creditUsed > total) { message(paymentStatus, 'O crédito usado não pode passar do total da venda.', 'warning'); return; }
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
    if (await heartbeat(true)) {
      find('[data-pdv-finish]').disabled = true;
      form.requestSubmit();
      return;
    }
    if (pdvConnection === 'offline' || pdvConnection === 'unavailable') {
      try {
        await captureOfflineSale();
      } catch (error) {
        message(paymentStatus, error?.message || 'Não foi possível proteger esta venda na fila local.', 'error');
      } finally {
        refreshFinishAvailability();
      }
      return;
    }
    message(paymentStatus, connectionCopy[pdvConnection]?.[1] || connectionCopy.unavailable[1], 'warning');
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
  find('[data-pdv-close-cash]')?.addEventListener('click', () => { afterCashOpen = null; cashDialog.close(); search.focus(); });
  find('[data-pdv-close-payment]').addEventListener('click', () => { dialog.close(); search.focus(); });
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
    const due = totals().balance;
    row.querySelector('[data-pdv-payment-amount]').value = moneyInput(button.dataset.pdvMethod === CREDIT ? Math.min(due, Math.round(customerCredit * 100)) : due);
    row.querySelector('[data-pdv-payment-amount]').focus();
    totals();
  });
  find('[data-pdv-finish]').addEventListener('click', finish);
  offlineQueueButton?.addEventListener('click', async () => {
    await refreshOfflineQueue();
    offlineDialog?.showModal();
  });
  find('[data-pdv-offline-close]')?.addEventListener('click', () => offlineDialog?.close());
  offlineSyncButton?.addEventListener('click', () => syncOfflineQueue(true));
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
  form.addEventListener('submit', (event) => {
    if (pdvConnection !== 'online') {
      event.preventDefault();
      message(checkoutStatus, connectionCopy[pdvConnection]?.[1] || connectionCopy.unavailable[1], 'warning');
      heartbeat(true);
      return;
    }
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
  const syncCustomerOptions = () => {
    const identified = Boolean(tutor.value);
    [payLater, changeAsCredit].forEach((input) => {
      if (!input) return;
      input.disabled = !identified;
      if (!identified) input.checked = false;
    });
    if (customerOptionsHint) customerOptionsHint.hidden = identified;
  };
  const refreshCustomerBalance = async () => {
    const serial = ++balanceSerial;
    customerCredit = 0;
    if (customerBalance) { customerBalance.hidden = true; customerBalance.replaceChildren(); }
    syncCustomerOptions();
    renderShortcuts();
    paymentRows().forEach((row) => row.pdvRefreshMethods?.());
    if (!tutor.value || !form.dataset.customerBalanceUrl) return;
    try {
      const response = await pdvFetch(form.dataset.customerBalanceUrl.replace('__TUTOR__', encodeURIComponent(tutor.value)), { headers: { Accept: 'application/json' } });
      if (!response.ok || serial !== balanceSerial) return;
      const data = await response.json();
      customerCredit = Number(data.credit) || 0;
      if (customerBalance && (Number(data.debt) > 0 || customerCredit !== 0)) {
        const parts = [];
        if (Number(data.debt) > 0) parts.push('Deve ' + brl.format(Number(data.debt)) + ' em ' + data.open_sales + (Number(data.open_sales) === 1 ? ' venda' : ' vendas'));
        if (customerCredit !== 0) parts.push('crédito de ' + brl.format(customerCredit));
        customerBalance.append(parts.join(' · ') + ' · ');
        const link = document.createElement('a');
        link.href = data.url;
        link.target = '_blank';
        link.rel = 'noopener';
        link.textContent = 'ver saldo';
        customerBalance.append(link);
        customerBalance.classList.toggle('is-debt', Number(data.debt) > 0);
        customerBalance.hidden = false;
      }
      renderShortcuts();
      paymentRows().forEach((row) => row.pdvRefreshMethods?.());
      totals();
    } catch { /* balance is informative only */ }
  };
  tutor.addEventListener('change', () => { syncCustomer(); syncDelivery(true); refreshCustomerBalance(); });
  clinic?.addEventListener('change', async () => {
    ++searchSerial;
    clearResults();
    syncCustomer();
    renderShortcuts();
    renderCash();
    paymentRows().forEach((row) => row.pdvRefreshMethods?.());
    if (headerClinic) headerClinic.textContent = clinic.selectedOptions[0]?.textContent || 'Selecione a clínica';
    await loadOfflineCatalog();
    if (pdvConnection === 'online') await refreshOfflineCatalog();
    search.focus();
  });
  syncCustomer();
  refreshCustomerBalance();

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

  const updateClock = () => {
    if (clock) clock.textContent = new Intl.DateTimeFormat('pt-BR', { hour: '2-digit', minute: '2-digit' }).format(new Date());
  };
  updateClock();
  window.setInterval(updateClock, 30000);
  setConnectionState('checking');
  Promise.all([initializeOfflineMode(), heartbeat(true)]).then(async ([, online]) => {
    if (online) {
      await refreshOfflineCatalog();
      await syncOfflineQueue();
    }
  });
  heartbeatTimer = window.setInterval(() => heartbeat(), 4 * 60 * 1000);
  window.addEventListener('online', async () => {
    if (await heartbeat(true)) {
      await refreshOfflineCatalog();
      await syncOfflineQueue();
    }
  });
  window.addEventListener('offline', () => setConnectionState('offline'));
  window.addEventListener('focus', () => heartbeat());
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') heartbeat();
  });
  window.addEventListener('beforeunload', () => window.clearInterval(heartbeatTimer));

  closeButton?.addEventListener('click', () => {
    window.close();
    window.setTimeout(() => window.location.assign(closeButton.dataset.pdvExitUrl), 150);
  });

  fullscreenButton?.addEventListener('click', async () => {
    try {
      if (document.fullscreenElement) await document.exitFullscreen();
      else await document.documentElement.requestFullscreen();
    } catch { /* fullscreen may be blocked by the browser or device */ }
  });
  document.addEventListener('fullscreenchange', () => {
    if (!fullscreenButton) return;
    const active = Boolean(document.fullscreenElement);
    fullscreenButton.textContent = active ? 'Sair da tela cheia' : 'Tela cheia';
    fullscreenButton.setAttribute('aria-pressed', active ? 'true' : 'false');
  });

  document.addEventListener('keydown', (event) => {
    const target = event.target;
    const editing = target instanceof Element && target.matches('input,textarea,select,[contenteditable="true"]');
    const modalOpen = dialog.open || cashDialog?.open || quickProductDialog?.open;
    if (!editing && !modalOpen && /^\d$/.test(event.key)) {
      window.clearTimeout(scannerBufferTimer);
      scannerBuffer += event.key;
      setScannerState('reading', 'Recebendo código do leitor...');
      scannerBufferTimer = window.setTimeout(() => {
        if (scannerBuffer.length >= 8) {
          search.value = scannerBuffer;
          scannerBuffer = '';
          lookupBarcode();
        } else {
          scannerBuffer = '';
          setScannerState('ready');
        }
      }, 120);
      return;
    }
    if (!editing && !modalOpen && event.key === 'Enter' && scannerBuffer.length >= 8) {
      event.preventDefault();
      window.clearTimeout(scannerBufferTimer);
      search.value = scannerBuffer;
      scannerBuffer = '';
      lookupBarcode();
      return;
    }
    if (!['F2', 'F4', 'F6', 'F8', 'F10'].includes(event.key)) return;
    event.preventDefault();
    if (event.key === 'F2') {
      if (dialog.open) dialog.close();
      if (cashDialog?.open) cashDialog.close();
      if (quickProductDialog?.open) quickProductDialog.close();
      search.focus();
      search.select();
    }
    if (event.key === 'F4') { customer.open = true; tutor.focus(); }
    if (event.key === 'F6') discount.focus();
    if (event.key === 'F8') openPayment();
    if (event.key === 'F10' && !isQuoteMode()) { if (!dialog.open) openPayment(); else finish(); }
  });
});
