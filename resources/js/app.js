document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-confirm]').forEach((element) => {
    element.addEventListener('click', (event) => {
      if (!window.confirm(element.dataset.confirm)) {
        event.preventDefault();
      }
    });
  });

  const normalizeBarcode = (value) => value.replace(/\D+/g, '');

  const setLookupStatus = (status, message, state = '') => {
    if (!status) {
      return;
    }

    status.textContent = message;
    status.classList.remove('is-success', 'is-warning', 'is-error');

    if (state) {
      status.classList.add(`is-${state}`);
    }
  };

  const toCurrencyNumber = (value) => {
    const raw = String(value || '').trim();

    if (!raw) {
      return 0;
    }

    const normalized = raw.includes(',')
      ? raw.replace(/\./g, '').replace(',', '.')
      : raw;

    return Number.parseFloat(normalized) || 0;
  };

  const formatCurrencyInput = (value, showZero = false) => {
    const amount = toCurrencyNumber(value);

    return amount > 0 || showZero ? amount.toFixed(2).replace('.', ',') : '';
  };

  const applyCurrencyInputMask = (input) => {
    if (!input || input.disabled) {
      return;
    }

    const digits = String(input.value || '').replace(/\D+/g, '');

    if (!digits) {
      input.value = '';
      return;
    }

    input.value = (Number.parseInt(digits, 10) / 100).toFixed(2).replace('.', ',');
  };

  document.querySelectorAll('[data-money-input]').forEach((input) => {
    input.value = formatCurrencyInput(input.value, false);
    input.addEventListener('input', () => applyCurrencyInputMask(input));
  });

  const gtinInput = document.querySelector('[data-product-lookup-url]');

  if (gtinInput) {
    const status = document.querySelector('[data-product-lookup-status]');
    const imagePreview = document.querySelector('[data-product-image-preview]');
    const image = document.querySelector('[data-product-image]');
    let lookupTimer = null;

    const setField = (id, value) => {
      if (value === undefined || value === null || value === '') {
        return;
      }

      const field = document.getElementById(id);

      if (field) {
        field.value = value;
      }
    };

    const fillProduct = (product) => {
      [
        'barcode',
        'name',
        'category',
        'brand',
        'manufacturer',
        'description',
        'unit',
        'weight',
        'image_path',
        'lookup_source',
        'looked_up_at',
      ].forEach((field) => setField(field, product[field]));

      if (product.lookup_metadata) {
        setField('lookup_metadata', JSON.stringify(product.lookup_metadata));
      }

      if (product.image_preview_url && image && imagePreview) {
        image.src = product.image_preview_url;
        imagePreview.hidden = false;
      }
    };

    const lookupProduct = async () => {
      const gtin = normalizeBarcode(gtinInput.value);

      if (gtin.length < 8) {
        setLookupStatus(status, '');
        return;
      }

      gtinInput.value = gtin;
      setField('barcode', gtin);

      setLookupStatus(status, 'Buscando produto pelo EAN/GTIN...');

      try {
        const url = gtinInput.dataset.productLookupUrl.replace('__GTIN__', encodeURIComponent(gtin));
        const response = await fetch(url, {
          headers: {
            Accept: 'application/json',
          },
        });

        if (!response.ok) {
          throw new Error('lookup failed');
        }

        const data = await response.json();

        if (!data.found) {
          setLookupStatus(status, data.message || 'Produto nao encontrado. Cadastro manual liberado.', 'warning');
          return;
        }

        fillProduct(data.product || {});
        setLookupStatus(status, 'Produto encontrado e campos preenchidos.', 'success');
      } catch (error) {
        setLookupStatus(status, 'Busca indisponivel agora. Cadastro manual liberado.', 'error');
      }
    };

    gtinInput.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') {
        event.preventDefault();
        lookupProduct();
      }
    });

    gtinInput.addEventListener('blur', lookupProduct);

    gtinInput.addEventListener('input', () => {
      window.clearTimeout(lookupTimer);
      lookupTimer = window.setTimeout(lookupProduct, 450);
    });

    if (gtinInput.dataset.productLookupAuto === '1' && normalizeBarcode(gtinInput.value).length >= 8) {
      lookupProduct();
    }
  }

  const inventoryScanner = document.querySelector('[data-inventory-scanner]');

  if (inventoryScanner) {
    const barcodeInput = inventoryScanner.querySelector('[data-inventory-barcode-input]');
    const barcodeButton = inventoryScanner.querySelector('[data-inventory-barcode-button]');
    const createProductLink = inventoryScanner.querySelector('[data-inventory-create-product-link]');
    const status = inventoryScanner.querySelector('[data-inventory-lookup-status]');
    const productSelect = document.querySelector('[data-inventory-product-select]');
    const unitCostInput = document.getElementById('unit_cost');
    const quantityInput = document.getElementById('quantity');
    const reasonInput = document.getElementById('reason');
    let lookupTimer = null;

    const createProductUrl = (gtin) => inventoryScanner.dataset.productCreateUrl.replace('__GTIN__', encodeURIComponent(gtin));

    const setInventoryLink = (url = '', label = 'Cadastrar produto agora') => {
      if (!createProductLink) {
        return;
      }

      if (!url) {
        createProductLink.hidden = true;
        createProductLink.href = '#';
        return;
      }

      createProductLink.href = url;
      createProductLink.textContent = label;
      createProductLink.hidden = false;
    };

    const ensureInventoryProductOption = (item) => {
      if (!productSelect || !item.product_id) {
        return;
      }

      if (productSelect.querySelector(`option[value="${item.product_id}"]`)) {
        return;
      }

      const option = document.createElement('option');
      option.value = item.product_id;
      option.textContent = `${item.name || 'Produto'} - estoque ${item.stock_quantity || 0} ${item.unit || 'un'}`;
      option.dataset.description = item.name || '';
      option.dataset.cost = item.cost_price || 0;
      option.dataset.stock = item.stock_quantity || 0;
      option.dataset.unit = item.unit || 'un';
      productSelect.appendChild(option);
    };

    const selectInventoryProduct = (item) => {
      ensureInventoryProductOption(item);

      if (productSelect) {
        productSelect.value = item.product_id || '';
      }

      const cost = Number.parseFloat(item.cost_price || '0') || 0;

      if (unitCostInput && cost > 0 && !String(unitCostInput.value || '').trim()) {
        unitCostInput.value = cost.toFixed(2);
      }

      if (reasonInput && !reasonInput.value.trim()) {
        reasonInput.value = 'Entrada de mercadoria';
      }

      const stock = Number.parseFloat(item.stock_quantity || '0') || 0;
      const unit = item.unit || 'un';
      setLookupStatus(status, `Produto selecionado. Estoque atual: ${stock.toLocaleString('pt-BR', { minimumFractionDigits: 3 })} ${unit}.`, 'success');

      if (quantityInput && !String(quantityInput.value || '').trim()) {
        quantityInput.focus();
      }
    };

    const lookupInventoryProduct = async () => {
      const gtin = normalizeBarcode(barcodeInput?.value || '');

      if (gtin.length < 8) {
        setLookupStatus(status, '');
        setInventoryLink('');
        return;
      }

      barcodeInput.value = gtin;
      setInventoryLink('');
      setLookupStatus(status, 'Buscando produto para estoque...');

      try {
        const url = inventoryScanner.dataset.inventoryLookupUrl.replace('__GTIN__', encodeURIComponent(gtin));
        const response = await fetch(url, {
          headers: {
            Accept: 'application/json',
          },
        });
        const data = await response.json();

        if (!response.ok || !data.found) {
          setLookupStatus(status, data.message || 'Produto nao encontrado.', 'warning');
          setInventoryLink(data.product_create_url || createProductUrl(gtin));
          return;
        }

        if (data.mode === 'product') {
          selectInventoryProduct(data.item || {});
          setInventoryLink(data.product_edit_url || '', 'Editar produto');
          barcodeInput.value = '';
          return;
        }

        setLookupStatus(status, data.message || 'Produto reconhecido. Cadastre antes de movimentar estoque.', 'warning');
        setInventoryLink(data.product_create_url || createProductUrl(gtin));
      } catch (error) {
        setLookupStatus(status, 'Busca indisponivel agora. Selecione o produto manualmente.', 'error');
        setInventoryLink(createProductUrl(gtin));
      }
    };

    barcodeButton?.addEventListener('click', () => {
      window.clearTimeout(lookupTimer);
      lookupInventoryProduct();
    });

    barcodeInput?.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') {
        event.preventDefault();
        window.clearTimeout(lookupTimer);
        lookupInventoryProduct();
      }
    });

    barcodeInput?.addEventListener('input', () => {
      window.clearTimeout(lookupTimer);
      lookupTimer = window.setTimeout(() => {
        if (normalizeBarcode(barcodeInput.value).length >= 12) {
          lookupInventoryProduct();
        }
      }, 350);
    });

    if (inventoryScanner.dataset.inventoryLookupAuto === '1' && normalizeBarcode(barcodeInput?.value || '').length >= 8) {
      lookupInventoryProduct();
    }
  }

  const saleScanner = document.querySelector('[data-sale-scanner]');

  if (saleScanner) {
    const barcodeInput = saleScanner.querySelector('[data-sale-barcode-input]');
    const barcodeButton = saleScanner.querySelector('[data-sale-barcode-button]');
    const createProductLink = saleScanner.querySelector('[data-sale-create-product-link]');
    const status = saleScanner.querySelector('[data-sale-lookup-status]');
    const rows = Array.from(document.querySelectorAll('[data-sale-item-row]'));
    const saleForm = document.querySelector('[data-sale-form]');
    const statusSelect = document.querySelector('[data-sale-status]');
    const discountInput = document.querySelector('[data-sale-discount]');
    const additionsInput = document.querySelector('[data-sale-additions]');
    const totalInput = document.querySelector('[data-sale-total-input]');
    const subtotalDisplay = document.querySelector('[data-sale-subtotal-display]');
    const discountDisplay = document.querySelector('[data-sale-discount-display]');
    const additionsDisplay = document.querySelector('[data-sale-additions-display]');
    const totalDisplay = document.querySelector('[data-sale-total-display]');
    const paidDisplay = document.querySelector('[data-sale-paid-display]');
    const balanceLabel = document.querySelector('[data-sale-balance-label]');
    const balanceDisplay = document.querySelector('[data-sale-balance-display]');
    const checkoutStatus = document.querySelector('[data-sale-checkout-status]');
    const payBalanceButton = document.querySelector('[data-sale-pay-balance]');
    const finalizeButton = document.querySelector('[data-sale-finalize]');
    const unitPriceInputs = Array.from(document.querySelectorAll('[data-sale-unit-price]'));
    const itemDiscountInputs = Array.from(document.querySelectorAll('[data-sale-item-discount]'));
    const paymentAmounts = Array.from(document.querySelectorAll('[data-sale-payment-amount]'));
    const paymentMethods = Array.from(document.querySelectorAll('[data-sale-payment-method]'));
    const receivedAmountInput = document.querySelector('[data-sale-received-amount]');
    const moneyInputs = [
      discountInput,
      additionsInput,
      receivedAmountInput,
      ...unitPriceInputs,
      ...itemDiscountInputs,
      ...paymentAmounts,
    ].filter(Boolean);
    const isLocked = saleForm?.dataset.saleLocked === '1';
    let scanTimer = null;

    const toNumber = (value) => {
      const raw = String(value || '').trim();

      if (!raw) {
        return 0;
      }

      const normalized = raw.includes(',')
        ? raw.replace(/\./g, '').replace(',', '.')
        : raw;

      return Number.parseFloat(normalized) || 0;
    };

    const formatMoney = (value) => new Intl.NumberFormat('pt-BR', {
      currency: 'BRL',
      style: 'currency',
    }).format(Math.max(0, value));

    const formatAmountInput = (value, showZero = false) => (value > 0 || showZero ? value.toFixed(2).replace('.', ',') : '');

    const formatMoneyInput = (input, showZero = false) => {
      if (!input || input.disabled || !String(input.value || '').trim()) {
        return 0;
      }

      const amount = toNumber(input.value);
      input.value = formatAmountInput(amount, showZero);

      return amount;
    };

    const applyMoneyMask = (input) => {
      if (!input || input.disabled) {
        return 0;
      }

      const digits = String(input.value || '').replace(/\D+/g, '');

      if (!digits) {
        input.value = '';

        return 0;
      }

      const amount = Number.parseInt(digits, 10) / 100;
      input.value = formatAmountInput(amount, true);

      return amount;
    };

    const normalizeMoneyField = (input) => {
      if (!input || input.disabled || !String(input.value || '').trim()) {
        return;
      }

      input.value = toNumber(input.value).toFixed(2);
    };

    const setText = (element, value) => {
      if (element) {
        element.textContent = value;
      }
    };

    const rowTotal = (row) => {
      const quantity = rowField(row, '[data-sale-quantity]');
      const unitPrice = rowField(row, '[data-sale-unit-price]');
      const itemDiscount = rowField(row, '[data-sale-item-discount]');

      return Math.max(0, (toNumber(quantity?.value) * toNumber(unitPrice?.value)) - toNumber(itemDiscount?.value));
    };

    const calculateSaleTotals = () => {
      const subtotal = rows.reduce((total, row) => total + rowTotal(row), 0);
      const discount = Math.min(subtotal, toNumber(discountInput?.value));
      const additions = toNumber(additionsInput?.value);
      const total = Math.max(0, subtotal + additions - discount);
      const paid = paymentAmounts.reduce((sum, input) => sum + toNumber(input.value), 0);
      const balance = total - paid;

      setText(subtotalDisplay, formatMoney(subtotal));
      setText(discountDisplay, formatMoney(discount));
      setText(additionsDisplay, formatMoney(additions));
      setText(totalDisplay, formatMoney(total));
      setText(paidDisplay, formatMoney(paid));
      setText(balanceLabel, Math.abs(balance) < 0.005 ? 'Quitado' : (balance < 0 ? 'Troco' : 'Falta'));
      setText(balanceDisplay, formatMoney(Math.abs(balance)));

      if (receivedAmountInput && document.activeElement !== receivedAmountInput) {
        receivedAmountInput.value = formatAmountInput(paid);
      }

      if (totalInput) {
        if ('value' in totalInput) {
          totalInput.value = formatMoney(total);
        } else {
          totalInput.textContent = formatMoney(total);
        }
      }

      if (!isLocked) {
        if (payBalanceButton) {
          payBalanceButton.disabled = total <= 0 || balance <= 0;
        }

        if (finalizeButton) {
          finalizeButton.disabled = total <= 0;
        }
      }

      if (checkoutStatus) {
        if (total <= 0) {
          setLookupStatus(checkoutStatus, 'Inclua pelo menos um item com valor para finalizar a venda.', 'warning');
        } else if (balance > 0) {
          setLookupStatus(checkoutStatus, 'Receba o saldo antes de finalizar.', 'warning');
        } else {
          setLookupStatus(checkoutStatus, 'Venda pronta para finalizar.', 'success');
        }
      }

      return { balance, paid, subtotal, total };
    };

    const fillBalancePayment = () => {
      const { balance } = calculateSaleTotals();

      if (balance <= 0) {
        return;
      }

      const targetAmount = paymentAmounts.find((input) => toNumber(input.value) <= 0) || paymentAmounts[0];

      if (targetAmount) {
        targetAmount.value = formatAmountInput(balance, true);
      }

      const targetIndex = paymentAmounts.indexOf(targetAmount);
      const targetMethod = paymentMethods[targetIndex] || paymentMethods[0];

      if (targetMethod && !targetMethod.value) {
        targetMethod.value = 'cash';
      }

      calculateSaleTotals();
    };

    const applyReceivedAmount = () => {
      const amount = toNumber(receivedAmountInput?.value);
      const targetAmount = paymentAmounts[0];

      if (targetAmount) {
        targetAmount.value = amount > 0 ? formatAmountInput(amount, true) : '';
      }

      const targetMethod = paymentMethods[0];

      if (targetMethod && amount > 0 && !targetMethod.value) {
        targetMethod.value = 'cash';
      }

      calculateSaleTotals();
    };

    const formatReceivedAmount = () => {
      if (!receivedAmountInput) {
        return;
      }

      receivedAmountInput.value = formatAmountInput(toNumber(receivedAmountInput.value), true);
      calculateSaleTotals();
    };

    const applySelectedCatalogItem = (row, itemType) => {
      const type = rowField(row, '[data-sale-item-type]');
      const product = rowField(row, '[data-sale-product-select]');
      const service = rowField(row, '[data-sale-service-select]');
      const description = rowField(row, '[data-sale-description]');
      const quantity = rowField(row, '[data-sale-quantity]');
      const unitPrice = rowField(row, '[data-sale-unit-price]');
      const itemDiscount = rowField(row, '[data-sale-item-discount]');
      const select = itemType === 'service' ? service : product;
      const selected = select?.selectedOptions?.[0];

      if (!selected?.value) {
        calculateSaleTotals();
        return;
      }

      if (type) {
        type.value = itemType;
      }

      if (itemType === 'product' && service) {
        service.value = '';
      }

      if (itemType === 'service' && product) {
        product.value = '';
      }

      if (description && (!description.value.trim() || description.value.trim() === 'Item avulso')) {
        description.value = selected.dataset.description || selected.textContent.trim();
      }

      if (quantity && toNumber(quantity.value) <= 0) {
        quantity.value = '1';
      }

      if (unitPrice && toNumber(unitPrice.value) <= 0) {
        unitPrice.value = formatAmountInput(toNumber(selected.dataset.price), true);
      }

      if (itemDiscount && toNumber(itemDiscount.value) <= 0) {
        itemDiscount.value = '';
      }

      calculateSaleTotals();
    };

    const setCreateProductLink = (gtin) => {
      if (!createProductLink) {
        return;
      }

      if (!gtin) {
        createProductLink.hidden = true;
        createProductLink.href = '#';
        return;
      }

      createProductLink.href = saleScanner.dataset.productCreateUrl.replace('__GTIN__', encodeURIComponent(gtin));
      createProductLink.textContent = 'Cadastrar produto agora';
      createProductLink.hidden = false;
    };

    const setEditProductLink = (url) => {
      if (!createProductLink || !url) {
        return;
      }

      createProductLink.href = url;
      createProductLink.textContent = 'Editar preco e estoque';
      createProductLink.hidden = false;
    };

    const rowField = (row, selector) => row.querySelector(selector);

    const applyManualRowDefaults = (row) => {
      const type = rowField(row, '[data-sale-item-type]');
      const product = rowField(row, '[data-sale-product-select]');
      const service = rowField(row, '[data-sale-service-select]');
      const description = rowField(row, '[data-sale-description]');
      const quantity = rowField(row, '[data-sale-quantity]');
      const unitPrice = rowField(row, '[data-sale-unit-price]');
      const itemDiscount = rowField(row, '[data-sale-item-discount]');
      const hasCatalogItem = Boolean(product?.value || service?.value);
      const hasManualEntry = Boolean(description?.value.trim()) || toNumber(unitPrice?.value) > 0 || toNumber(itemDiscount?.value) > 0;

      if (!hasCatalogItem && hasManualEntry) {
        if (type) {
          type.value = 'custom';
        }

        if (product) {
          product.value = '';
        }

        if (service) {
          service.value = '';
        }

        if (description && !description.value.trim() && toNumber(unitPrice?.value) > 0) {
          description.value = 'Item avulso';
        }
      }

      if (quantity && toNumber(unitPrice?.value) > 0 && toNumber(quantity.value) <= 0) {
        quantity.value = '1';
      }

      calculateSaleTotals();
    };

    const rowQuantity = (row) => {
      const quantity = rowField(row, '[data-sale-quantity]');

      return Number.parseFloat(quantity?.value || '0') || 0;
    };

    const isEmptyRow = (row) => {
      const product = rowField(row, '[data-sale-product-select]');
      const service = rowField(row, '[data-sale-service-select]');
      const description = rowField(row, '[data-sale-description]');

      return !product?.value && !service?.value && !description?.value.trim() && rowQuantity(row) <= 0;
    };

    const findProductRow = (productId) => rows.find((row) => {
      const type = rowField(row, '[data-sale-item-type]');
      const product = rowField(row, '[data-sale-product-select]');

      return type?.value === 'product' && product?.value === String(productId);
    });

    const findScannedRow = (item) => {
      const barcode = item.gtin || item.barcode;

      if (!barcode) {
        return null;
      }

      return rows.find((row) => row.dataset.saleGtin === String(barcode));
    };

    const firstEmptyRow = () => rows.find(isEmptyRow);

    const ensureProductOption = (select, item) => {
      if (!select || !item.product_id || select.querySelector(`option[value="${item.product_id}"]`)) {
        return;
      }

      const option = document.createElement('option');
      option.value = item.product_id;
      option.textContent = item.description || `Produto ${item.product_id}`;
      select.appendChild(option);
    };

    const fillSaleRow = (row, item, increment = false) => {
      const type = rowField(row, '[data-sale-item-type]');
      const product = rowField(row, '[data-sale-product-select]');
      const service = rowField(row, '[data-sale-service-select]');
      const description = rowField(row, '[data-sale-description]');
      const quantity = rowField(row, '[data-sale-quantity]');
      const unitPrice = rowField(row, '[data-sale-unit-price]');
      const itemDiscount = rowField(row, '[data-sale-item-discount]');
      const barcode = item.gtin || item.barcode || '';

      row.dataset.saleGtin = barcode ? String(barcode) : '';

      if (type) {
        type.value = item.type || 'product';
      }

      ensureProductOption(product, item);

      if (product) {
        product.value = item.product_id || '';
      }

      if (service) {
        service.value = item.petshop_service_id || '';
      }

      if (description) {
        description.value = item.description || '';
      }

      if (quantity) {
        const nextQuantity = increment ? rowQuantity(row) + 1 : (Number.parseFloat(item.quantity || '1') || 1);
        quantity.value = nextQuantity.toFixed(3).replace(/\.?0+$/, '');
      }

      if (unitPrice && item.unit_price !== undefined && item.unit_price !== null) {
        unitPrice.value = formatAmountInput(Number.parseFloat(item.unit_price || '0') || 0, true);
      }

      if (itemDiscount) {
        itemDiscount.value = '';
      }

      calculateSaleTotals();
    };

    const addSaleItem = (item) => {
      if (item.type === 'product' && item.product_id) {
        const existingRow = findProductRow(item.product_id);

        if (existingRow) {
          fillSaleRow(existingRow, item, true);
          return 'incremented';
        }
      }

      const scannedRow = findScannedRow(item);

      if (scannedRow) {
        fillSaleRow(scannedRow, item, true);
        return 'incremented';
      }

      const targetRow = firstEmptyRow();

      if (!targetRow) {
        return 'full';
      }

      fillSaleRow(targetRow, item);
      return 'added';
    };

    const lookupSaleProduct = async () => {
      const gtin = normalizeBarcode(barcodeInput?.value || '');

      if (gtin.length < 8) {
        setLookupStatus(status, '');
        return;
      }

      barcodeInput.value = gtin;
      setLookupStatus(status, 'Buscando item...');
      setCreateProductLink('');

      try {
        const url = saleScanner.dataset.saleLookupUrl.replace('__GTIN__', encodeURIComponent(gtin));
        const response = await fetch(url, {
          headers: {
            Accept: 'application/json',
          },
        });
        const data = await response.json();

        if (!response.ok || !data.found) {
          setLookupStatus(status, data.message || 'Produto nao encontrado.', 'warning');
          setCreateProductLink(gtin);
          return;
        }

        const result = addSaleItem(data.item || {});

        if (result === 'full') {
          setLookupStatus(status, 'Sem linhas vazias para adicionar o item.', 'error');
          return;
        }

        barcodeInput.value = '';
        barcodeInput.focus();

        const warnings = Array.isArray(data.warnings) ? data.warnings.filter(Boolean) : [];

        if (warnings.length > 0 && data.product_edit_url) {
          setEditProductLink(data.product_edit_url);
        } else {
          setCreateProductLink(data.mode === 'catalog' && data.manual_allowed ? (data.item?.gtin || gtin) : '');
        }

        const message = [data.message || 'Item adicionado a venda.', ...warnings].join(' ');
        setLookupStatus(status, message, data.mode === 'catalog' || warnings.length > 0 ? 'warning' : 'success');
      } catch (error) {
        setLookupStatus(status, 'Busca indisponivel agora. Adicione o item manualmente.', 'error');
        setCreateProductLink(gtin);
      }
    };

    barcodeButton?.addEventListener('click', () => {
      window.clearTimeout(scanTimer);
      lookupSaleProduct();
    });

    barcodeInput?.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') {
        event.preventDefault();
        window.clearTimeout(scanTimer);
        lookupSaleProduct();
      }
    });

    barcodeInput?.addEventListener('input', () => {
      window.clearTimeout(scanTimer);
      scanTimer = window.setTimeout(() => {
        if (normalizeBarcode(barcodeInput.value).length >= 12) {
          lookupSaleProduct();
        }
      }, 350);
    });

    rows.forEach((row) => {
      rowField(row, '[data-sale-product-select]')?.addEventListener('change', () => applySelectedCatalogItem(row, 'product'));
      rowField(row, '[data-sale-service-select]')?.addEventListener('change', () => applySelectedCatalogItem(row, 'service'));
      rowField(row, '[data-sale-description]')?.addEventListener('input', () => applyManualRowDefaults(row));
      rowField(row, '[data-sale-quantity]')?.addEventListener('input', () => applyManualRowDefaults(row));
      rowField(row, '[data-sale-unit-price]')?.addEventListener('input', (event) => {
        applyMoneyMask(event.target);
        applyManualRowDefaults(row);
      });
      rowField(row, '[data-sale-item-discount]')?.addEventListener('input', (event) => {
        applyMoneyMask(event.target);
        applyManualRowDefaults(row);
      });
      applyManualRowDefaults(row);
    });

    moneyInputs.forEach((input) => formatMoneyInput(input, false));
    discountInput?.addEventListener('input', (event) => {
      applyMoneyMask(event.target);
      calculateSaleTotals();
    });
    additionsInput?.addEventListener('input', (event) => {
      applyMoneyMask(event.target);
      calculateSaleTotals();
    });
    receivedAmountInput?.addEventListener('input', (event) => {
      applyMoneyMask(event.target);
      applyReceivedAmount();
    });
    receivedAmountInput?.addEventListener('blur', formatReceivedAmount);
    paymentAmounts.forEach((input) => input.addEventListener('input', (event) => {
      applyMoneyMask(event.target);
      calculateSaleTotals();
    }));
    saleForm?.addEventListener('submit', () => {
      normalizeMoneyField(discountInput);
      normalizeMoneyField(additionsInput);
      unitPriceInputs.forEach(normalizeMoneyField);
      itemDiscountInputs.forEach(normalizeMoneyField);
      paymentAmounts.forEach(normalizeMoneyField);
    });
    payBalanceButton?.addEventListener('click', fillBalancePayment);
    finalizeButton?.addEventListener('click', () => {
      const { total } = calculateSaleTotals();

      if (total <= 0) {
        setLookupStatus(checkoutStatus, 'Inclua pelo menos um item com valor para finalizar a venda.', 'warning');
        return;
      }

      fillBalancePayment();

      if (statusSelect) {
        statusSelect.value = 'completed';
      }

      saleForm?.requestSubmit();
    });

    if (saleScanner.dataset.saleLookupAuto === '1' && normalizeBarcode(barcodeInput?.value || '').length >= 8) {
      lookupSaleProduct();
    }

    calculateSaleTotals();
  }

  const purchaseForm = document.querySelector('[data-purchase-form]');

  if (purchaseForm) {
    let rows = Array.from(purchaseForm.querySelectorAll('[data-purchase-item-row]'));
    const totalDisplay = purchaseForm.querySelector('[data-purchase-total]');
    const purchaseScanner = purchaseForm.querySelector('[data-purchase-scanner]');
    const barcodeInput = purchaseScanner?.querySelector('[data-purchase-barcode-input]');
    const barcodeButton = purchaseScanner?.querySelector('[data-purchase-barcode-button]');
    const createProductLink = purchaseScanner?.querySelector('[data-purchase-create-product-link]');
    const lookupStatus = purchaseScanner?.querySelector('[data-purchase-lookup-status]');
    const invoiceScanner = purchaseForm.querySelector('[data-purchase-invoice-scanner]');
    const invoiceInput = invoiceScanner?.querySelector('[data-purchase-invoice-input]');
    const invoiceButton = invoiceScanner?.querySelector('[data-purchase-invoice-button]');
    const invoiceStatus = invoiceScanner?.querySelector('[data-purchase-invoice-status]');
    const nfeKeyImportUrl = invoiceScanner?.dataset.purchaseNfeKeyImportUrl || '';
    const statusInput = document.getElementById('status');
    const invoiceNumberInput = document.getElementById('invoice_number');
    const invoiceKeyInput = document.getElementById('invoice_key');
    const purchasedAtInput = document.getElementById('purchased_at');
    const paymentReferenceInput = document.getElementById('payment_reference');
    const paymentDueDateInput = document.getElementById('payment_due_date');
    const installmentsCountInput = document.getElementById('installments_count');
    const installmentIntervalInput = document.getElementById('installment_interval_days');
    const paymentStatusInput = document.getElementById('payment_status');
    const xmlImporter = purchaseForm.querySelector('[data-purchase-xml-importer]');
    const xmlInput = xmlImporter?.querySelector('[data-purchase-xml-input]');
    const xmlButton = xmlImporter?.querySelector('[data-purchase-xml-button]');
    const xmlStatus = xmlImporter?.querySelector('[data-purchase-xml-status]');
    const xmlCreateSupplier = xmlImporter?.querySelector('[data-purchase-xml-create-supplier]');
    const xmlCreateProducts = xmlImporter?.querySelector('[data-purchase-xml-create-products]');
    const supplierSelect = document.getElementById('supplier_id');
    const nfeReview = purchaseForm.querySelector('[data-purchase-nfe-review]');
    const nfeReviewSubtitle = purchaseForm.querySelector('[data-purchase-nfe-review-subtitle]');
    const nfeReviewState = purchaseForm.querySelector('[data-purchase-nfe-review-state]');
    const nfeItemsCount = purchaseForm.querySelector('[data-purchase-nfe-items-count]');
    const nfeMatchedCount = purchaseForm.querySelector('[data-purchase-nfe-matched-count]');
    const nfeCreatedCount = purchaseForm.querySelector('[data-purchase-nfe-created-count]');
    const nfePendingCount = purchaseForm.querySelector('[data-purchase-nfe-pending-count]');
    const nfeXmlTotal = purchaseForm.querySelector('[data-purchase-nfe-xml-total]');
    const nfeEntryTotal = purchaseForm.querySelector('[data-purchase-nfe-entry-total]');
    const nfeTotalDiff = purchaseForm.querySelector('[data-purchase-nfe-total-diff]');
    const nfeInvoiceLabel = purchaseForm.querySelector('[data-purchase-nfe-invoice-label]');
    const nfeSupplierLabel = purchaseForm.querySelector('[data-purchase-nfe-supplier-label]');
    const nfeAlerts = purchaseForm.querySelector('[data-purchase-nfe-alerts]');
    const nfeItemsBody = purchaseForm.querySelector('[data-purchase-nfe-items]');
    const previewStatus = purchaseForm.querySelector('[data-purchase-preview-status]');
    const previewStockCount = purchaseForm.querySelector('[data-purchase-preview-stock-count]');
    const previewLotCount = purchaseForm.querySelector('[data-purchase-preview-lot-count]');
    const previewPayableCount = purchaseForm.querySelector('[data-purchase-preview-payable-count]');
    const previewTotal = purchaseForm.querySelector('[data-purchase-preview-total]');
    const previewStockBody = purchaseForm.querySelector('[data-purchase-preview-stock-body]');
    const previewLotBody = purchaseForm.querySelector('[data-purchase-preview-lot-body]');
    const previewPayableBody = purchaseForm.querySelector('[data-purchase-preview-payable-body]');
    let purchaseLookupTimer = null;
    let invoiceLookupTimer = null;
    let purchaseEntryTotal = 0;
    let nfeImportedInvoiceTotal = null;
    const initialPurchasedAtValue = purchasedAtInput?.value || '';

    const moneyFormatter = new Intl.NumberFormat('pt-BR', {
      currency: 'BRL',
      style: 'currency',
    });

    const purchaseField = (row, selector) => row.querySelector(selector);

    const formatAmountInput = (value) => Number(value || 0).toFixed(2).replace('.', ',');

    const setText = (element, value) => {
      if (element) {
        element.textContent = value;
      }
    };

    const setFormFieldValue = (id, value) => {
      if (value === undefined || value === null || value === '') {
        return;
      }

      const field = document.getElementById(id);

      if (field) {
        field.value = value;
      }
    };

    const updateNfeReviewTotals = () => {
      if (!nfeReview || nfeReview.hidden) {
        return;
      }

      const xmlTotal = Number(nfeImportedInvoiceTotal || 0);
      const diff = purchaseEntryTotal - xmlTotal;
      const isBalanced = Math.abs(diff) <= 0.01;

      setText(nfeXmlTotal, moneyFormatter.format(xmlTotal));
      setText(nfeEntryTotal, moneyFormatter.format(purchaseEntryTotal));
      setText(nfeTotalDiff, moneyFormatter.format(diff));

      nfeTotalDiff?.classList.toggle('is-success', isBalanced);
      nfeTotalDiff?.classList.toggle('is-warning', !isBalanced);
    };

    const extractInvoiceAccessKey = (value) => {
      const raw = String(value || '').trim();
      const keyFromText = raw.match(/\d{44}/);

      if (keyFromText) {
        return keyFromText[0];
      }

      const digits = normalizeBarcode(raw);

      return digits.length === 44 ? digits : '';
    };

    const invoiceNumberFromKey = (key) => {
      if (!key || key.length !== 44) {
        return '';
      }

      return key.slice(25, 34).replace(/^0+/, '') || '0';
    };

    const invoicePeriodFromKey = (key) => {
      if (!key || key.length !== 44) {
        return '';
      }

      const year = `20${key.slice(2, 4)}`;
      const month = key.slice(4, 6);

      if (Number(month) < 1 || Number(month) > 12) {
        return '';
      }

      return `${month}/${year}`;
    };

    const guideXmlImport = (message = 'Proximo passo: importe o XML da NF-e para completar data, fornecedor, itens e total.') => {
      if (!xmlImporter) {
        return;
      }

      xmlImporter.classList.add('is-next-step');
      setLookupStatus(xmlStatus, message, 'warning');
      xmlImporter.scrollIntoView({ behavior: 'smooth', block: 'center' });
      window.setTimeout(() => xmlInput?.focus({ preventScroll: true }), 300);
    };

    const clearXmlImportGuide = () => {
      xmlImporter?.classList.remove('is-next-step');
    };

    const formatNfeKeyDiagnostics = (data) => {
      const diagnostics = Array.isArray(data?.diagnostics) ? data.diagnostics : [];

      if (diagnostics.length === 0) {
        return 'Importe o XML uma vez para esta chave ficar salva no VetFlow.';
      }

      const cacheMissing = diagnostics.some((item) => item?.source === 'Cache VetFlow' && item?.status !== 'found');
      const localChecked = diagnostics.some((item) => item?.source === 'Arquivo local' && Number(item?.checked_files || 0) > 0);
      const fiscalNotConfigured = diagnostics.some((item) => item?.source === 'Integracao fiscal' && item?.status === 'not_configured');
      const fiscalError = diagnostics.find((item) => item?.source === 'Integracao fiscal' && !['not_configured', 'found'].includes(item?.status));
      const parts = [];

      if (cacheMissing) {
        parts.push('O XML ainda nao esta no cache.');
      }

      if (localChecked) {
        parts.push('Arquivos locais foram verificados, mas nenhum correspondeu a esta chave.');
      }

      if (fiscalNotConfigured) {
        parts.push('A integracao fiscal ainda nao esta configurada.');
      } else if (fiscalError?.message) {
        parts.push(fiscalError.message);
      }

      parts.push('Importe o XML uma vez para esta chave ficar salva no VetFlow.');

      return Array.from(new Set(parts)).join(' ');
    };

    const importNfeFromKey = async (accessKey, invoiceNumber = '', invoicePeriod = '') => {
      if (!nfeKeyImportUrl) {
        guideXmlImport('Busca por chave ainda nao configurada. Escolha o XML da NF-e para concluir a entrada.');
        return;
      }

      const token = purchaseForm.querySelector('input[name="_token"]')?.value || '';
      const formData = new FormData();
      formData.append('access_key', accessKey);
      formData.append('create_missing_supplier', xmlCreateSupplier?.checked ? '1' : '0');
      formData.append('create_missing_products', xmlCreateProducts?.checked ? '1' : '0');

      const previousButtonText = invoiceButton?.textContent || '';

      if (invoiceButton) {
        invoiceButton.disabled = true;
        invoiceButton.textContent = 'Buscando...';
      }

      clearXmlImportGuide();
      setLookupStatus(invoiceStatus, `Buscando NF-e completa pela chave${invoiceNumber ? ` ${invoiceNumber}` : ''}${invoicePeriod ? ` (${invoicePeriod})` : ''}...`);

      try {
        const response = await fetch(nfeKeyImportUrl, {
          method: 'POST',
          headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': token,
          },
          body: formData,
        });
        const data = await parseJsonResponse(response);

        if (!response.ok || data.found === false) {
          const message = [responseErrorMessage(data, 'Nao encontrei a NF-e completa pela chave neste ambiente.'), formatNfeKeyDiagnostics(data)]
            .filter(Boolean)
            .join(' ');

          setLookupStatus(invoiceStatus, message, 'error');
          guideXmlImport('Nao encontrei automaticamente pela chave. Escolha o XML da NF-e para concluir esta entrada agora.');
          return;
        }

        applyNfeXmlPayload(data);
        clearXmlImportGuide();
        setLookupStatus(invoiceStatus, data.message || 'NF-e carregada pela chave e campos preenchidos.', 'success');
      } catch (error) {
        setLookupStatus(invoiceStatus, error?.message || 'Busca pela chave indisponivel agora.', 'error');
        guideXmlImport('Busca pela chave indisponivel. Escolha o XML da NF-e para concluir esta entrada agora.');
      } finally {
        if (invoiceButton) {
          invoiceButton.disabled = false;
          invoiceButton.textContent = previousButtonText || 'Buscar NF-e';
        }
      }
    };

    const applyInvoiceScan = () => {
      if (!invoiceInput) {
        return;
      }

      const raw = invoiceInput.value || '';
      const key = extractInvoiceAccessKey(raw);
      const digits = normalizeBarcode(raw);

      if (key) {
        if (invoiceKeyInput) {
          invoiceKeyInput.value = key;
        }

        const invoiceNumber = invoiceNumberFromKey(key);

        if (invoiceNumberInput && invoiceNumber) {
          invoiceNumberInput.value = invoiceNumber;
        }

        if (paymentReferenceInput && invoiceNumber && !paymentReferenceInput.value.trim()) {
          paymentReferenceInput.value = invoiceNumber;
        }

        if (purchasedAtInput && purchasedAtInput.value === initialPurchasedAtValue) {
          purchasedAtInput.value = '';
        }

        const invoicePeriod = invoicePeriodFromKey(key);

        invoiceInput.value = '';
        importNfeFromKey(key, invoiceNumber, invoicePeriod);
        return;
      }

      if (digits.length > 0 && digits.length <= 9) {
        if (invoiceNumberInput) {
          invoiceNumberInput.value = digits.replace(/^0+/, '') || digits;
        }

        invoiceInput.value = '';
        setLookupStatus(invoiceStatus, 'Numero da NF preenchido. Para chave, data real, fornecedor, itens e total, escaneie a chave com 44 digitos ou importe o XML.', 'warning');
        guideXmlImport('Numero preenchido. Para completar a entrada automaticamente, escolha o XML da NF-e e clique em Importar XML.');
        return;
      }

      setLookupStatus(invoiceStatus, 'Nao encontrei uma chave de NF-e com 44 digitos nesta leitura.', 'warning');
    };

    const marginPercent = (unitCost, salePrice) => {
      if (salePrice <= 0) {
        return '';
      }

      return (((salePrice - unitCost) / salePrice) * 100).toFixed(2).replace('.', ',');
    };

    const formatQuantity = (value, unit = '') => {
      const formatted = Number(value || 0).toLocaleString('pt-BR', {
        maximumFractionDigits: 3,
        minimumFractionDigits: 0,
      });

      return unit ? `${formatted} ${unit}` : formatted;
    };

    const parseLocalDate = (value) => {
      if (!value) {
        return null;
      }

      const [datePart] = String(value).split('T');
      const parts = datePart.split('-').map((part) => Number.parseInt(part, 10));

      if (parts.length !== 3 || parts.some(Number.isNaN)) {
        return null;
      }

      return new Date(parts[0], parts[1] - 1, parts[2]);
    };

    const formatDate = (date) => date
      ? date.toLocaleDateString('pt-BR')
      : '-';

    const addDays = (date, days) => {
      const nextDate = new Date(date.getTime());
      nextDate.setDate(nextDate.getDate() + days);

      return nextDate;
    };

    const appendPreviewCell = (row, text, className = '') => {
      const cell = document.createElement('td');
      cell.textContent = text;

      if (className) {
        cell.className = className;
      }

      row.appendChild(cell);

      return cell;
    };

    const appendPreviewBadgeCell = (row, label, className) => {
      const cell = document.createElement('td');
      const badge = document.createElement('span');
      badge.className = `badge ${className}`;
      badge.textContent = label;
      cell.appendChild(badge);
      row.appendChild(cell);
    };

    const paymentStatusLabel = (status) => ({
      cancelled: 'Cancelado',
      overdue: 'Vencido',
      paid: 'Pago',
      pending: 'Pendente',
    })[status] || 'Pendente';

    const paymentStatusBadge = (status) => ({
      cancelled: 'danger',
      overdue: 'danger',
      paid: 'success',
      pending: 'warning',
    })[status] || 'warning';

    const activePurchaseItems = () => rows
      .map((row) => {
        const product = purchaseField(row, '[data-purchase-product-select]');
        const selected = product?.selectedOptions?.[0];
        const description = purchaseField(row, '[data-purchase-description]')?.value.trim() || selected?.textContent.trim() || '';
        const quantity = rowQuantity(row);

        if (quantity <= 0 || (!product?.value && !description)) {
          return null;
        }

        const unitCost = toCurrencyNumber(purchaseField(row, '[data-purchase-unit-cost]')?.value);
        const lotNumber = purchaseField(row, '[data-purchase-lot-number]')?.value.trim() || '';
        const expiresAt = purchaseField(row, '[data-purchase-expires-at]')?.value || '';
        const unit = selected?.dataset.unit || '';
        const currentStock = Number.parseFloat(selected?.dataset.stock || '0') || 0;

        return {
          currentStock,
          description: description || 'Produto sem descricao',
          expiresAt,
          lotNumber,
          quantity,
          totalCost: quantity * unitCost,
          unit,
          unitCost,
        };
      })
      .filter(Boolean);

    const lotStatusForItem = (item) => {
      if (!item.lotNumber && !item.expiresAt) {
        return {
          className: 'muted-badge',
          label: 'Sem lote',
        };
      }

      if (!item.lotNumber) {
        return {
          className: 'warning',
          label: 'Sem lote',
        };
      }

      if (!item.expiresAt) {
        return {
          className: 'warning',
          label: 'Sem validade',
        };
      }

      const expiresAt = parseLocalDate(item.expiresAt);
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      const warningLimit = addDays(today, 30);

      if (expiresAt && expiresAt < today) {
        return {
          className: 'danger',
          label: 'Vencido',
        };
      }

      if (expiresAt && expiresAt <= warningLimit) {
        return {
          className: 'warning',
          label: 'Proximo',
        };
      }

      return {
        className: 'success',
        label: 'OK',
      };
    };

    const installmentAmountsPreview = (total, count) => {
      const totalCents = Math.round(total * 100);
      const baseCents = Math.floor(totalCents / count);
      const remainder = totalCents % count;

      return Array.from({ length: count }, (_, index) => (baseCents + (index < remainder ? 1 : 0)) / 100);
    };

    const payablePreviewRows = () => {
      const status = statusInput?.value || 'received';

      if (status !== 'received' || purchaseEntryTotal <= 0) {
        return [];
      }

      const installments = Math.min(60, Math.max(1, Number.parseInt(installmentsCountInput?.value || '1', 10) || 1));
      const intervalDays = Math.min(365, Math.max(1, Number.parseInt(installmentIntervalInput?.value || '30', 10) || 30));
      const firstDueDate = parseLocalDate(paymentDueDateInput?.value)
        || parseLocalDate(purchasedAtInput?.value)
        || new Date();
      const paymentStatus = paymentStatusInput?.value || 'pending';
      const reference = paymentReferenceInput?.value.trim() || invoiceNumberInput?.value.trim() || '-';

      return installmentAmountsPreview(purchaseEntryTotal, installments).map((amount, index) => ({
        amount,
        dueDate: addDays(firstDueDate, intervalDays * index),
        installment: `${index + 1}/${installments}`,
        reference: installments > 1 && reference !== '-' ? `${reference} - Parcela ${index + 1}/${installments}` : reference,
        status: paymentStatus,
      }));
    };

    const renderEmptyPreviewRow = (body, colspan, message) => {
      if (!body) {
        return;
      }

      body.textContent = '';

      const row = document.createElement('tr');
      appendPreviewCell(row, message, 'muted');
      row.firstChild.colSpan = colspan;
      body.appendChild(row);
    };

    const renderStockPreview = (items) => {
      if (!previewStockBody) {
        return;
      }

      previewStockBody.textContent = '';

      if (items.length === 0) {
        renderEmptyPreviewRow(previewStockBody, 5, 'Inclua produtos para visualizar o estoque.');
        return;
      }

      items.forEach((item) => {
        const row = document.createElement('tr');
        appendPreviewCell(row, item.description);
        appendPreviewCell(row, formatQuantity(item.quantity, item.unit));
        appendPreviewCell(row, formatQuantity(item.currentStock, item.unit));
        appendPreviewCell(row, formatQuantity(item.currentStock + item.quantity, item.unit));
        appendPreviewCell(row, moneyFormatter.format(item.totalCost));
        previewStockBody.appendChild(row);
      });
    };

    const renderLotPreview = (lots, items) => {
      if (!previewLotBody) {
        return;
      }

      previewLotBody.textContent = '';

      if (lots.length === 0) {
        const message = items.length > 0
          ? 'Nenhum lote sera criado. Os produtos entrarao como estoque sem lote.'
          : 'Informe lote e validade quando precisar controlar o item.';
        renderEmptyPreviewRow(previewLotBody, 5, message);
        return;
      }

      lots.forEach((item) => {
        const row = document.createElement('tr');
        const status = lotStatusForItem(item);
        appendPreviewCell(row, item.description);
        appendPreviewCell(row, item.lotNumber || '-');
        appendPreviewCell(row, formatDate(parseLocalDate(item.expiresAt)));
        appendPreviewCell(row, formatQuantity(item.quantity, item.unit));
        appendPreviewBadgeCell(row, status.label, status.className);
        previewLotBody.appendChild(row);
      });
    };

    const renderPayablePreview = (payables) => {
      if (!previewPayableBody) {
        return;
      }

      previewPayableBody.textContent = '';

      if (statusInput?.value !== 'received') {
        renderEmptyPreviewRow(previewPayableBody, 5, 'Rascunho ou entrada cancelada nao gera contas a pagar agora.');
        return;
      }

      if (payables.length === 0) {
        renderEmptyPreviewRow(previewPayableBody, 5, 'Informe valor e pagamento para visualizar as contas.');
        return;
      }

      payables.forEach((payable) => {
        const row = document.createElement('tr');
        appendPreviewCell(row, payable.installment);
        appendPreviewCell(row, moneyFormatter.format(payable.amount));
        appendPreviewCell(row, formatDate(payable.dueDate));
        appendPreviewBadgeCell(row, paymentStatusLabel(payable.status), paymentStatusBadge(payable.status));
        appendPreviewCell(row, payable.reference);
        previewPayableBody.appendChild(row);
      });
    };

    const updatePurchaseImpactPreview = () => {
      const items = activePurchaseItems();
      const lots = items.filter((item) => item.lotNumber || item.expiresAt);
      const payables = payablePreviewRows();
      const status = statusInput?.value || 'received';
      const hasIncompleteLots = lots.some((item) => !item.lotNumber || !item.expiresAt);

      setText(previewStockCount, String(items.length));
      setText(previewLotCount, String(lots.length));
      setText(previewPayableCount, String(payables.length));
      setText(previewTotal, moneyFormatter.format(purchaseEntryTotal));

      if (previewStatus) {
        previewStatus.className = `badge ${items.length === 0 ? 'muted-badge' : (status !== 'received' || hasIncompleteLots ? 'warning' : 'success')}`;
        previewStatus.textContent = items.length === 0
          ? 'Aguardando itens'
          : (status !== 'received' ? 'Nao lanca agora' : (hasIncompleteLots ? 'Revisar lotes' : 'Pronto para salvar'));
      }

      renderStockPreview(items);
      renderLotPreview(lots, items);
      renderPayablePreview(payables);
    };

    const updatePurchaseTotals = () => {
      let total = 0;

      rows.forEach((row) => {
        const quantity = toCurrencyNumber(purchaseField(row, '[data-purchase-quantity]')?.value);
        const unitCost = toCurrencyNumber(purchaseField(row, '[data-purchase-unit-cost]')?.value);
        const salePrice = toCurrencyNumber(purchaseField(row, '[data-purchase-sale-price]')?.value);
        const margin = purchaseField(row, '[data-purchase-margin]');
        const rowTotal = quantity * unitCost;
        const rowTotalDisplay = purchaseField(row, '[data-purchase-row-total]');

        total += rowTotal;

        if (margin && document.activeElement !== margin) {
          margin.value = marginPercent(unitCost, salePrice);
        }

        if (rowTotalDisplay) {
          rowTotalDisplay.textContent = moneyFormatter.format(rowTotal);
        }
      });

      if (totalDisplay) {
        totalDisplay.textContent = moneyFormatter.format(total);
      }

      purchaseEntryTotal = total;
      updateNfeReviewTotals();
      updatePurchaseImpactPreview();
    };

    const applySelectedPurchaseProduct = (row) => {
      const product = purchaseField(row, '[data-purchase-product-select]');
      const description = purchaseField(row, '[data-purchase-description]');
      const barcode = purchaseField(row, '[data-purchase-barcode-snapshot]');
      const unitCost = purchaseField(row, '[data-purchase-unit-cost]');
      const salePrice = purchaseField(row, '[data-purchase-sale-price]');
      const quantity = purchaseField(row, '[data-purchase-quantity]');
      const minimumStock = purchaseField(row, '[data-purchase-minimum-stock]');
      const intelligenceStatus = purchaseField(row, '[data-purchase-intelligence-status]');
      const intelligenceMetadata = purchaseField(row, '[data-purchase-intelligence-metadata]');
      const selected = product?.selectedOptions?.[0];

      if (!selected?.value) {
        updatePurchaseTotals();
        return;
      }

      row.classList.remove('is-warning');

      if (description && !description.value.trim()) {
        description.value = selected.dataset.description || selected.textContent.trim();
      }

      if (barcode && !barcode.value.trim()) {
        barcode.value = selected.dataset.gtin || '';
      }

      if (unitCost && toCurrencyNumber(unitCost.value) <= 0) {
        unitCost.value = formatAmountInput(toCurrencyNumber(selected.dataset.cost));
      }

      if (salePrice && toCurrencyNumber(salePrice.value) <= 0) {
        salePrice.value = formatAmountInput(toCurrencyNumber(selected.dataset.salePrice));
      }

      if (quantity && toCurrencyNumber(quantity.value) <= 0) {
        quantity.value = '1';
      }

      if (minimumStock && toCurrencyNumber(minimumStock.value) <= 0) {
        minimumStock.value = selected.dataset.minimumStock || '';
      }

      if (intelligenceStatus && !intelligenceStatus.value) {
        intelligenceStatus.value = 'manual_select';
      }

      if (intelligenceMetadata && !intelligenceMetadata.value) {
        intelligenceMetadata.value = JSON.stringify({
          selected_from: 'purchase_form',
          stock_quantity: selected.dataset.stock || null,
          unit: selected.dataset.unit || null,
        });
      }

      updatePurchaseTotals();
    };

    const normalizePurchaseMoneyField = (input) => {
      if (!input || !String(input.value || '').trim()) {
        return;
      }

      input.value = toCurrencyNumber(input.value).toFixed(2);
    };

    const normalizePurchaseDecimalField = (input) => {
      if (!input || !String(input.value || '').trim()) {
        return;
      }

      input.value = String(input.value).replace(/\./g, '').replace(',', '.');
    };

    const rowQuantity = (row) => toCurrencyNumber(purchaseField(row, '[data-purchase-quantity]')?.value);

    const isEmptyPurchaseRow = (row) => {
      const product = purchaseField(row, '[data-purchase-product-select]');
      const description = purchaseField(row, '[data-purchase-description]');

      return !product?.value && !description?.value.trim() && rowQuantity(row) <= 0;
    };

    const firstEmptyPurchaseRow = () => rows.find(isEmptyPurchaseRow);

    const findPurchaseProductRow = (productId) => rows.find((row) => {
      const product = purchaseField(row, '[data-purchase-product-select]');

      return product?.value === String(productId);
    });

    const ensurePurchaseProductOption = (select, item) => {
      if (!select || !item.product_id || select.querySelector(`option[value="${item.product_id}"]`)) {
        return;
      }

      const option = document.createElement('option');
      option.value = item.product_id;
      option.textContent = item.product_name || item.name || item.description || `Produto ${item.product_id}`;
      option.dataset.description = item.description || item.name || '';
      option.dataset.cost = item.cost_price || 0;
      option.dataset.salePrice = item.sale_price || 0;
      option.dataset.stock = item.stock_quantity || 0;
      option.dataset.minimumStock = item.minimum_stock || 0;
      option.dataset.gtin = item.gtin || item.barcode || '';
      option.dataset.unit = item.unit || 'un';
      select.appendChild(option);
    };

    const fillPurchaseRow = (row, item, increment = false) => {
      const product = purchaseField(row, '[data-purchase-product-select]');
      const description = purchaseField(row, '[data-purchase-description]');
      const barcode = purchaseField(row, '[data-purchase-barcode-snapshot]');
      const quantity = purchaseField(row, '[data-purchase-quantity]');
      const unitCost = purchaseField(row, '[data-purchase-unit-cost]');
      const salePrice = purchaseField(row, '[data-purchase-sale-price]');
      const minimumStock = purchaseField(row, '[data-purchase-minimum-stock]');
      const supplierSku = purchaseField(row, '[data-purchase-supplier-sku]');
      const updateSalePrice = purchaseField(row, '[data-purchase-update-sale-price]');
      const intelligenceStatus = purchaseField(row, '[data-purchase-intelligence-status]');
      const intelligenceMetadata = purchaseField(row, '[data-purchase-intelligence-metadata]');

      ensurePurchaseProductOption(product, item);

      if (product) {
        product.value = item.product_id || '';
      }

      if (description) {
        description.value = item.description || item.name || '';
      }

      if (barcode) {
        barcode.value = item.gtin || item.barcode || '';
      }

      if (quantity) {
        const nextQuantity = increment
          ? rowQuantity(row) + (Number.parseFloat(item.suggested_quantity || '1') || 1)
          : (Number.parseFloat(item.suggested_quantity || '1') || 1);
        quantity.value = nextQuantity.toFixed(3).replace(/\.?0+$/, '');
      }

      if (unitCost) {
        unitCost.value = formatAmountInput(toCurrencyNumber(item.cost_price));
      }

      if (salePrice) {
        salePrice.value = formatAmountInput(toCurrencyNumber(item.suggested_sale_price || item.sale_price));
      }

      if (minimumStock) {
        minimumStock.value = item.minimum_stock || '';
      }

      if (supplierSku) {
        supplierSku.value = item.supplier_sku || '';
      }

      if (updateSalePrice) {
        updateSalePrice.checked = Boolean(item.update_sale_price);
      }

      if (intelligenceStatus) {
        intelligenceStatus.value = item.intelligence_status || (item.product_id ? 'ean_lookup' : 'nfe_xml_unmatched');
      }

      if (intelligenceMetadata) {
        intelligenceMetadata.value = typeof item.intelligence_metadata === 'string'
          ? item.intelligence_metadata
          : JSON.stringify(item.intelligence_metadata || {
            source: 'purchase_entry_lookup',
            global_product_id: item.global_product_id || null,
            global_status: item.global_status || null,
            stock_quantity: item.stock_quantity || 0,
            minimum_stock: item.minimum_stock || 0,
            suggested_quantity: item.suggested_quantity || 1,
          });
      }

      row.classList.toggle('is-warning', !item.product_id);
      updatePurchaseTotals();
    };

    const setPurchaseCreateProductLink = (url = '', label = 'Cadastrar produto agora') => {
      if (!createProductLink) {
        return;
      }

      if (!url) {
        createProductLink.hidden = true;
        createProductLink.href = '#';
        return;
      }

      createProductLink.href = url;
      createProductLink.textContent = label;
      createProductLink.hidden = false;
    };

    const lookupPurchaseProduct = async () => {
      const gtin = normalizeBarcode(barcodeInput?.value || '');

      if (gtin.length < 8 || !purchaseScanner) {
        setLookupStatus(lookupStatus, '');
        setPurchaseCreateProductLink('');
        return;
      }

      barcodeInput.value = gtin;
      setLookupStatus(lookupStatus, 'Buscando produto para entrada...');
      setPurchaseCreateProductLink('');

      try {
        const url = purchaseScanner.dataset.purchaseLookupUrl.replace('__GTIN__', encodeURIComponent(gtin));
        const response = await fetch(url, {
          headers: {
            Accept: 'application/json',
          },
        });
        const data = await response.json();

        if (!response.ok || !data.found) {
          setLookupStatus(lookupStatus, data.message || 'Produto nao encontrado.', 'warning');
          setPurchaseCreateProductLink(data.product_create_url || purchaseScanner.dataset.productCreateUrl.replace('__GTIN__', encodeURIComponent(gtin)));
          return;
        }

        if (data.mode !== 'product') {
          setLookupStatus(lookupStatus, data.message || 'Produto reconhecido. Cadastre antes de lancar a compra.', 'warning');
          setPurchaseCreateProductLink(data.product_create_url || purchaseScanner.dataset.productCreateUrl.replace('__GTIN__', encodeURIComponent(gtin)));
          return;
        }

        const item = data.item || {};
        const existingRow = item.product_id ? findPurchaseProductRow(item.product_id) : null;
        const targetRow = existingRow || firstEmptyPurchaseRow();

        if (!targetRow) {
          setLookupStatus(lookupStatus, 'Sem linhas vazias para adicionar o item.', 'error');
          return;
        }

        fillPurchaseRow(targetRow, item, Boolean(existingRow));
        barcodeInput.value = '';
        barcodeInput.focus();
        setPurchaseCreateProductLink(data.product_edit_url || '', 'Editar produto');

        const warnings = Array.isArray(data.warnings) ? data.warnings.filter(Boolean) : [];
        const message = [data.message || 'Produto adicionado na entrada.', ...warnings].join(' ');
        setLookupStatus(lookupStatus, message, warnings.length > 0 ? 'warning' : 'success');
      } catch (error) {
        setLookupStatus(lookupStatus, 'Busca indisponivel agora. Selecione o produto manualmente.', 'error');
        setPurchaseCreateProductLink(purchaseScanner.dataset.productCreateUrl.replace('__GTIN__', encodeURIComponent(gtin)));
      }
    };

    const bindPurchaseRow = (row) => {
      if (!row || row.dataset.purchaseRowBound === '1') {
        return;
      }

      row.dataset.purchaseRowBound = '1';
      purchaseField(row, '[data-purchase-product-select]')?.addEventListener('change', () => applySelectedPurchaseProduct(row));
      purchaseField(row, '[data-purchase-quantity]')?.addEventListener('input', updatePurchaseTotals);
      purchaseField(row, '[data-purchase-unit-cost]')?.addEventListener('input', updatePurchaseTotals);
      purchaseField(row, '[data-purchase-sale-price]')?.addEventListener('input', updatePurchaseTotals);
      purchaseField(row, '[data-purchase-margin]')?.addEventListener('input', updatePurchaseTotals);
      purchaseField(row, '[data-purchase-lot-number]')?.addEventListener('input', updatePurchaseImpactPreview);
      purchaseField(row, '[data-purchase-expires-at]')?.addEventListener('change', updatePurchaseImpactPreview);
      row.querySelectorAll('[data-money-input]').forEach((input) => {
        input.addEventListener('input', () => applyCurrencyInputMask(input));
      });
      applySelectedPurchaseProduct(row);
    };

    const resetPurchaseRow = (row, index) => {
      row.dataset.purchaseRowBound = '';
      row.classList.remove('is-warning');

      row.querySelectorAll('[name]').forEach((field) => {
        field.name = field.name.replace(/items\[\d+]/, `items[${index}]`);
      });

      row.querySelectorAll('input, select, textarea').forEach((field) => {
        if (field.type === 'checkbox') {
          field.checked = false;
          return;
        }

        field.value = '';
      });

      row.querySelectorAll('[data-purchase-row-total]').forEach((field) => {
        field.textContent = 'R$ 0,00';
      });
    };

    const ensurePurchaseRowCapacity = (neededRows) => {
      const tableBody = rows[0]?.parentElement;

      if (!tableBody || rows.length === 0) {
        return;
      }

      while (rows.length < neededRows) {
        const index = rows.length;
        const row = rows[rows.length - 1].cloneNode(true);

        resetPurchaseRow(row, index);
        tableBody.appendChild(row);
        rows.push(row);
        bindPurchaseRow(row);
      }
    };

    const occupiedPurchaseRowsCount = () => rows.filter((row) => !isEmptyPurchaseRow(row)).length;

    rows.forEach(bindPurchaseRow);

    const ensureSupplierOption = (supplier) => {
      if (!supplierSelect || !supplier?.id) {
        return;
      }

      if (!supplierSelect.querySelector(`option[value="${supplier.id}"]`)) {
        const option = document.createElement('option');
        option.value = supplier.id;
        option.textContent = supplier.name || `Fornecedor ${supplier.id}`;
        supplierSelect.appendChild(option);
      }

      supplierSelect.value = supplier.id;
    };

    const appendNfeNote = (data) => {
      const notes = document.getElementById('notes');

      if (!notes) {
        return;
      }

      const invoice = data.invoice || {};
      const supplier = data.supplier || {};
      const note = [
        invoice.number ? `NF-e ${invoice.number}` : null,
        supplier.name || null,
        supplier.document ? `CNPJ/CPF ${supplier.document}` : null,
      ].filter(Boolean).join(' | ');

      if (!note || notes.value.includes(note)) {
        return;
      }

      notes.value = notes.value.trim()
        ? `${notes.value.trim()}\n${note}`
        : note;
    };

    const importedItemStatus = (item) => {
      if (!item.product_id) {
        return {
          label: 'Pendente',
          className: 'danger',
        };
      }

      if (item.product_created) {
        return {
          label: 'Criado',
          className: 'warning',
        };
      }

      return {
        label: 'Vinculado',
        className: 'success',
      };
    };

    const appendCell = (row, text) => {
      const cell = document.createElement('td');
      cell.textContent = text;
      row.appendChild(cell);

      return cell;
    };

    const renderNfeReviewItems = (items) => {
      if (!nfeItemsBody) {
        return;
      }

      nfeItemsBody.textContent = '';

      items.forEach((item) => {
        const row = document.createElement('tr');
        const status = importedItemStatus(item);
        const quantity = Number(item.suggested_quantity || 0);
        const unitCost = Number(item.cost_price || 0);

        appendCell(row, item.description || item.name || '-');
        appendCell(row, item.gtin || item.barcode || '-');

        const statusCell = document.createElement('td');
        const badge = document.createElement('span');
        badge.className = `badge ${status.className}`;
        badge.textContent = status.label;
        statusCell.appendChild(badge);
        row.appendChild(statusCell);

        appendCell(row, quantity.toLocaleString('pt-BR', { maximumFractionDigits: 3 }));
        appendCell(row, moneyFormatter.format(unitCost));
        appendCell(row, moneyFormatter.format(quantity * unitCost));

        const actionCell = document.createElement('td');
        const actionUrl = item.product_edit_url || item.product_create_url;

        if (actionUrl) {
          const link = document.createElement('a');
          link.className = 'button secondary compact-button';
          link.href = actionUrl;
          link.textContent = item.product_id ? 'Editar' : 'Cadastrar';
          actionCell.appendChild(link);
        } else {
          actionCell.textContent = '-';
        }

        row.appendChild(actionCell);

        if (!item.product_id) {
          row.classList.add('is-warning');
        }

        nfeItemsBody.appendChild(row);
      });
    };

    const renderNfeAlerts = (data) => {
      if (!nfeAlerts) {
        return;
      }

      const warnings = Array.isArray(data.warnings) ? data.warnings.filter(Boolean) : [];
      const items = Array.isArray(data.items) ? data.items : [];
      const itemWarnings = items.flatMap((item) => Array.isArray(item.warnings) ? item.warnings : []);
      const alerts = [...warnings, ...itemWarnings];

      nfeAlerts.textContent = '';

      if (alerts.length === 0) {
        const alert = document.createElement('div');
        alert.className = 'nfe-review-alert is-success';
        alert.textContent = 'Nenhuma pendencia critica encontrada no XML.';
        nfeAlerts.appendChild(alert);
        return;
      }

      Array.from(new Set(alerts)).forEach((message) => {
        const alert = document.createElement('div');
        alert.className = 'nfe-review-alert is-warning';
        alert.textContent = message;
        nfeAlerts.appendChild(alert);
      });
    };

    const renderNfeReview = (data) => {
      if (!nfeReview) {
        return;
      }

      const invoice = data.invoice || {};
      const supplier = data.supplier || {};
      const summary = data.summary || {};
      const items = Array.isArray(data.items) ? data.items : [];
      const pendingCount = Number(summary.unmatched_products || 0);
      const createdCount = Number(summary.created_products || 0);
      const matchedCount = Number(summary.matched_products || 0);

      nfeImportedInvoiceTotal = Number(invoice.total || summary.invoice_total || 0);
      nfeReview.hidden = false;

      setText(nfeItemsCount, String(summary.items_count ?? items.length));
      setText(nfeMatchedCount, String(matchedCount));
      setText(nfeCreatedCount, String(createdCount));
      setText(nfePendingCount, String(pendingCount));
      setText(nfeInvoiceLabel, invoice.number ? `NF-e ${invoice.number}` : '-');
      setText(nfeSupplierLabel, supplier.name || '-');
      setText(nfeReviewSubtitle, invoice.access_key ? `Chave ${invoice.access_key}` : 'XML importado para conferencia.');

      if (nfeReviewState) {
        nfeReviewState.className = `badge ${pendingCount > 0 ? 'warning' : 'success'}`;
        nfeReviewState.textContent = pendingCount > 0 ? 'Revisar pendencias' : 'Pronta para revisar';
      }

      renderNfeAlerts(data);
      renderNfeReviewItems(items);
      updateNfeReviewTotals();
    };

    const applyNfeXmlPayload = (data) => {
      const invoice = data.invoice || {};
      const items = Array.isArray(data.items) ? data.items : [];

      setFormFieldValue('invoice_number', invoice.number);
      setFormFieldValue('invoice_key', invoice.access_key);
      setFormFieldValue('purchased_at', invoice.purchased_at);
      setFormFieldValue('received_at', invoice.received_at);
      setFormFieldValue('payment_due_date', invoice.payment_due_date);
      setFormFieldValue('payment_reference', invoice.payment_reference);
      setFormFieldValue('installments_count', invoice.installments_count);

      ensureSupplierOption(data.supplier);
      appendNfeNote(data);
      ensurePurchaseRowCapacity(occupiedPurchaseRowsCount() + items.length);

      items.forEach((item) => {
        const targetRow = firstEmptyPurchaseRow();

        if (!targetRow) {
          return;
        }

        fillPurchaseRow(targetRow, item, false);
      });

      renderNfeReview(data);
      updatePurchaseTotals();
    };

    const responseErrorMessage = (data, fallback) => {
      const errors = data?.errors
        ? Object.values(data.errors).flat().filter(Boolean)
        : [];

      return data?.message || errors.join(' ') || fallback;
    };

    const parseJsonResponse = async (response) => {
      const text = await response.text();

      if (!text.trim()) {
        return {};
      }

      try {
        return JSON.parse(text);
      } catch (error) {
        const jsonStart = text.search(/[\[{]/);

        if (jsonStart >= 0) {
          try {
            return JSON.parse(text.slice(jsonStart));
          } catch (jsonError) {
            //
          }
        }

        return {
          found: false,
          message: text.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 300),
        };
      }
    };

    const importNfeXml = async () => {
      if (!xmlImporter || !xmlInput?.files?.length) {
        guideXmlImport('Selecione o XML da NF-e para importar e completar a entrada.');
        return;
      }

      const token = purchaseForm.querySelector('input[name="_token"]')?.value || '';
      const formData = new FormData();
      formData.append('xml_file', xmlInput.files[0]);
      formData.append('create_missing_supplier', xmlCreateSupplier?.checked ? '1' : '0');
      formData.append('create_missing_products', xmlCreateProducts?.checked ? '1' : '0');

      clearXmlImportGuide();
      setLookupStatus(xmlStatus, 'Importando XML da NF-e...');

      try {
        const response = await fetch(xmlImporter.dataset.purchaseXmlImportUrl, {
          method: 'POST',
          headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': token,
          },
          body: formData,
        });
        const data = await parseJsonResponse(response);

        if (!response.ok || data.found === false) {
          setLookupStatus(xmlStatus, responseErrorMessage(data, 'Nao foi possivel importar este XML.'), 'error');
          return;
        }

        applyNfeXmlPayload(data);

        const warnings = Array.isArray(data.warnings) ? data.warnings.filter(Boolean) : [];
        const unmatched = Number(data.summary?.unmatched_products || 0);
        const message = [data.message || 'XML importado.', ...warnings].join(' ');

        setLookupStatus(xmlStatus, message, unmatched > 0 || warnings.length > 0 ? 'warning' : 'success');
      } catch (error) {
        setLookupStatus(xmlStatus, error?.message || 'Importacao indisponivel agora. Confira o arquivo XML e tente novamente.', 'error');
      }
    };

    barcodeButton?.addEventListener('click', () => {
      window.clearTimeout(purchaseLookupTimer);
      lookupPurchaseProduct();
    });

    barcodeInput?.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') {
        event.preventDefault();
        window.clearTimeout(purchaseLookupTimer);
        lookupPurchaseProduct();
      }
    });

    barcodeInput?.addEventListener('input', () => {
      window.clearTimeout(purchaseLookupTimer);
      purchaseLookupTimer = window.setTimeout(() => {
        if (normalizeBarcode(barcodeInput.value).length >= 12) {
          lookupPurchaseProduct();
        }
      }, 350);
    });

    invoiceButton?.addEventListener('click', () => {
      window.clearTimeout(invoiceLookupTimer);
      applyInvoiceScan();
    });

    invoiceInput?.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') {
        event.preventDefault();
        window.clearTimeout(invoiceLookupTimer);
        applyInvoiceScan();
      }
    });

    invoiceInput?.addEventListener('input', () => {
      window.clearTimeout(invoiceLookupTimer);
      invoiceLookupTimer = window.setTimeout(() => {
        const raw = invoiceInput.value || '';
        const hasAccessKey = Boolean(extractInvoiceAccessKey(raw));
        const digits = normalizeBarcode(raw);

        if (hasAccessKey || digits.length >= 44) {
          applyInvoiceScan();
        }
      }, 250);
    });

    xmlButton?.addEventListener('click', importNfeXml);
    xmlInput?.addEventListener('change', () => {
      if (xmlInput.files?.length) {
        xmlImporter?.classList.add('is-next-step');
        setLookupStatus(xmlStatus, 'XML selecionado. Clique em Importar XML para preencher a entrada.', 'warning');
      }
    });

    [
      statusInput,
      purchasedAtInput,
      paymentDueDateInput,
      installmentsCountInput,
      installmentIntervalInput,
      paymentStatusInput,
      paymentReferenceInput,
      invoiceNumberInput,
    ].forEach((field) => {
      field?.addEventListener('input', updatePurchaseImpactPreview);
      field?.addEventListener('change', updatePurchaseImpactPreview);
    });

    purchaseForm.addEventListener('submit', () => {
      rows.forEach((row) => {
        normalizePurchaseMoneyField(purchaseField(row, '[data-purchase-unit-cost]'));
        normalizePurchaseMoneyField(purchaseField(row, '[data-purchase-sale-price]'));
        normalizePurchaseDecimalField(purchaseField(row, '[data-purchase-margin]'));
      });

      if (invoiceKeyInput) {
        invoiceKeyInput.value = extractInvoiceAccessKey(invoiceKeyInput.value) || normalizeBarcode(invoiceKeyInput.value);
      }
    });

    if (purchaseScanner?.dataset.purchaseLookupAuto === '1' && normalizeBarcode(barcodeInput?.value || '').length >= 8) {
      lookupPurchaseProduct();
    }

    updatePurchaseTotals();
  }
});
