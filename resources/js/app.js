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
    const rows = Array.from(purchaseForm.querySelectorAll('[data-purchase-item-row]'));
    const totalDisplay = purchaseForm.querySelector('[data-purchase-total]');

    const moneyFormatter = new Intl.NumberFormat('pt-BR', {
      currency: 'BRL',
      style: 'currency',
    });

    const purchaseField = (row, selector) => row.querySelector(selector);

    const formatAmountInput = (value) => Number(value || 0).toFixed(2).replace('.', ',');

    const updatePurchaseTotals = () => {
      let total = 0;

      rows.forEach((row) => {
        const quantity = toCurrencyNumber(purchaseField(row, '[data-purchase-quantity]')?.value);
        const unitCost = toCurrencyNumber(purchaseField(row, '[data-purchase-unit-cost]')?.value);
        const rowTotal = quantity * unitCost;
        const rowTotalDisplay = purchaseField(row, '[data-purchase-row-total]');

        total += rowTotal;

        if (rowTotalDisplay) {
          rowTotalDisplay.textContent = moneyFormatter.format(rowTotal);
        }
      });

      if (totalDisplay) {
        totalDisplay.textContent = moneyFormatter.format(total);
      }
    };

    const applySelectedPurchaseProduct = (row) => {
      const product = purchaseField(row, '[data-purchase-product-select]');
      const description = purchaseField(row, '[data-purchase-description]');
      const unitCost = purchaseField(row, '[data-purchase-unit-cost]');
      const quantity = purchaseField(row, '[data-purchase-quantity]');
      const selected = product?.selectedOptions?.[0];

      if (!selected?.value) {
        updatePurchaseTotals();
        return;
      }

      if (description && !description.value.trim()) {
        description.value = selected.dataset.description || selected.textContent.trim();
      }

      if (unitCost && toCurrencyNumber(unitCost.value) <= 0) {
        unitCost.value = formatAmountInput(toCurrencyNumber(selected.dataset.cost));
      }

      if (quantity && toCurrencyNumber(quantity.value) <= 0) {
        quantity.value = '1';
      }

      updatePurchaseTotals();
    };

    const normalizePurchaseMoneyField = (input) => {
      if (!input || !String(input.value || '').trim()) {
        return;
      }

      input.value = toCurrencyNumber(input.value).toFixed(2);
    };

    rows.forEach((row) => {
      purchaseField(row, '[data-purchase-product-select]')?.addEventListener('change', () => applySelectedPurchaseProduct(row));
      purchaseField(row, '[data-purchase-quantity]')?.addEventListener('input', updatePurchaseTotals);
      purchaseField(row, '[data-purchase-unit-cost]')?.addEventListener('input', updatePurchaseTotals);
    });

    purchaseForm.addEventListener('submit', () => {
      rows.forEach((row) => normalizePurchaseMoneyField(purchaseField(row, '[data-purchase-unit-cost]')));
    });

    updatePurchaseTotals();
  }
});
