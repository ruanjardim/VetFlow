export function initServiceOrderForm() {
const serviceOrderForm = document.querySelector('[data-service-order-form]');

if (serviceOrderForm) {
  const clinicSelect = serviceOrderForm.querySelector('[data-service-order-clinic-select]');
  const tutorSelect = serviceOrderForm.querySelector('[data-service-order-tutor-select]');
  const patientSelect = serviceOrderForm.querySelector('[data-service-order-patient-select]');
  const assignedUserSelect = serviceOrderForm.querySelector('[data-service-order-assigned-user-select]');
  const itemRows = Array.from(serviceOrderForm.querySelectorAll('[data-service-order-item-row]'));
  const discountInput = serviceOrderForm.querySelector('[data-service-order-discount]');
  const servicesTotalDisplay = serviceOrderForm.querySelector('[data-service-order-services-total]');
  const productsTotalDisplay = serviceOrderForm.querySelector('[data-service-order-products-total]');
  const discountTotalDisplay = serviceOrderForm.querySelector('[data-service-order-discount-total]');
  const totalDisplay = serviceOrderForm.querySelector('[data-service-order-total]');

  const currentClinicId = () => String(
    clinicSelect?.value || serviceOrderForm.dataset.serviceOrderClinicId || '',
  );

  const optionMatchesClinic = (option, clinicId) => {
    if (!option.value || !option.dataset.clinicId) {
      return true;
    }

    return clinicId !== '' && option.dataset.clinicId === clinicId;
  };

  const filterSelectByClinic = (select, clinicId) => {
    Array.from(select?.options || []).forEach((option) => {
      const visible = optionMatchesClinic(option, clinicId);
      option.hidden = !visible;
      option.disabled = !visible;
    });

    if (select?.selectedOptions[0]?.disabled) {
      select.value = '';
    }
  };

  const numberValue = (value) => Number.parseFloat(String(value || '0').replace(',', '.')) || 0;
  const formatMoney = (value) => new Intl.NumberFormat('pt-BR', {
    currency: 'BRL',
    style: 'currency',
  }).format(Math.max(0, value));

  const calculateServiceOrderTotals = () => {
    let servicesTotal = 0;
    let productsTotal = 0;

    itemRows.forEach((row) => {
      const type = row.querySelector('[data-service-order-item-type]')?.value || 'service';
      const quantity = numberValue(row.querySelector('[data-service-order-quantity]')?.value);
      const unitPrice = numberValue(row.querySelector('[data-service-order-unit-price]')?.value);
      const rowTotal = quantity * unitPrice;

      if (type === 'product') {
        productsTotal += rowTotal;
      } else {
        servicesTotal += rowTotal;
      }
    });

    const discount = numberValue(discountInput?.value);
    const total = Math.max(0, servicesTotal + productsTotal - discount);

    if (servicesTotalDisplay) servicesTotalDisplay.textContent = formatMoney(servicesTotal);
    if (productsTotalDisplay) productsTotalDisplay.textContent = formatMoney(productsTotal);
    if (discountTotalDisplay) discountTotalDisplay.textContent = formatMoney(discount);
    if (totalDisplay) totalDisplay.textContent = formatMoney(total);
  };

  const clearServiceOrderRow = (row) => {
    const type = row.querySelector('[data-service-order-item-type]');
    const service = row.querySelector('[data-service-order-service-select]');
    const product = row.querySelector('[data-service-order-product-select]');
    const description = row.querySelector('[data-service-order-description]');
    const quantity = row.querySelector('[data-service-order-quantity]');
    const unitPrice = row.querySelector('[data-service-order-unit-price]');
    const priceSelect = row.querySelector('[data-service-order-price-select]');

    if (type) type.value = 'service';
    if (service) service.value = '';
    if (product) product.value = '';
    if (description) description.value = '';
    if (quantity) quantity.value = '';
    if (unitPrice) unitPrice.value = '';
    if (priceSelect) {
      priceSelect.replaceChildren(new Option('Selecione o servico', ''));
      priceSelect.disabled = true;
    }

    calculateServiceOrderTotals();
  };

  const filterPatients = () => {
    const clinicId = currentClinicId();
    const tutorId = String(tutorSelect?.value || '');

    Array.from(patientSelect?.options || []).forEach((option) => {
      const matchesClinic = optionMatchesClinic(option, clinicId);
      const matchesTutor = !option.value
        || !tutorId
        || !option.dataset.tutorId
        || option.dataset.tutorId === tutorId;
      const visible = matchesClinic && matchesTutor;
      option.hidden = !visible;
      option.disabled = !visible;
    });

    if (patientSelect?.selectedOptions[0]?.disabled) {
      patientSelect.value = '';
    }
  };

  const filterServiceOrderCatalog = () => {
    const clinicId = currentClinicId();

    filterSelectByClinic(tutorSelect, clinicId);
    filterSelectByClinic(assignedUserSelect, clinicId);
    itemRows.forEach((row) => {
      const service = row.querySelector('[data-service-order-service-select]');
      const product = row.querySelector('[data-service-order-product-select]');
      const previousService = service?.value || '';
      const previousProduct = product?.value || '';

      filterSelectByClinic(service, clinicId);
      filterSelectByClinic(product, clinicId);

      if ((previousService && !service?.value) || (previousProduct && !product?.value)) {
        clearServiceOrderRow(row);
      }
    });
    filterPatients();
  };

  const setDefaultQuantity = (row) => {
    const quantity = row.querySelector('[data-service-order-quantity]');

    if (quantity && !(Number.parseFloat(quantity.value || '0') > 0)) {
      quantity.value = '1';
    }
  };

  const petSizePriceKey = () => {

    const size = patientSelect?.selectedOptions[0]?.dataset.size || '';

    const keys = { small: 'priceSmall', medium: 'priceMedium', large: 'priceLarge', giant: 'priceGiant' };


    return keys[size] || 'priceBase';

  };


  const populateServicePrices = (row, preserveUnitPrice = false) => {
    const serviceSelect = row.querySelector('[data-service-order-service-select]');
    const priceSelect = row.querySelector('[data-service-order-price-select]');
    const unitPrice = row.querySelector('[data-service-order-unit-price]');
    const option = serviceSelect?.selectedOptions[0];
    const labels = [
      ['priceBase', 'Base'],
      ['priceSmall', 'Porte pequeno'],
      ['priceMedium', 'Porte medio'],
      ['priceLarge', 'Porte grande'],
      ['priceGiant', 'Porte gigante'],
    ];

    if (!priceSelect) {
      return;
    }

    priceSelect.replaceChildren();

    if (!option?.value) {
      const placeholder = document.createElement('option');
      placeholder.value = '';
      placeholder.textContent = 'Selecione o servico';
      priceSelect.appendChild(placeholder);
      priceSelect.disabled = true;
      return;
    }

    const currentPrice = preserveUnitPrice ? String(unitPrice?.value || '') : '';

    labels.forEach(([key, label]) => {
      const value = option.dataset[key];

      if (value === undefined || value === '') {
        return;
      }

      const priceOption = document.createElement('option');
      priceOption.value = value;
      priceOption.dataset.key = key;
      priceOption.textContent = `${label} - ${new Intl.NumberFormat('pt-BR', {
        currency: 'BRL',
        style: 'currency',
      }).format(Number.parseFloat(value || '0') || 0)}`;
      priceSelect.appendChild(priceOption);
    });

    priceSelect.disabled = priceSelect.options.length === 0;

    const matchingOption = Array.from(priceSelect.options).find((item) => (
      Math.abs(Number.parseFloat(item.value || '0') - Number.parseFloat(currentPrice || '-1')) < 0.005
    ));

    if (matchingOption) {

      priceSelect.value = matchingOption.value;

    } else {

      const sizeKey = petSizePriceKey();

      const sizeOption = Array.from(priceSelect.options).find((item) => (

        item.dataset.key === sizeKey && Number.parseFloat(item.value || '0') > 0

      ));


      if (sizeOption) {

        priceSelect.value = sizeOption.value;

      }

    }

    if (unitPrice && (!preserveUnitPrice || !unitPrice.value)) {
      unitPrice.value = priceSelect.value || '0';
    }
  };

  itemRows.forEach((row) => {
    const typeSelect = row.querySelector('[data-service-order-item-type]');
    const serviceSelect = row.querySelector('[data-service-order-service-select]');
    const productSelect = row.querySelector('[data-service-order-product-select]');
    const priceSelect = row.querySelector('[data-service-order-price-select]');
    const description = row.querySelector('[data-service-order-description]');
    const unitPrice = row.querySelector('[data-service-order-unit-price]');

    serviceSelect?.addEventListener('change', () => {
      const option = serviceSelect.selectedOptions[0];

      if (!option?.value) {
        populateServicePrices(row);
        return;
      }

      typeSelect.value = 'service';
      productSelect.value = '';
      description.value = option.dataset.name || option.textContent.trim();
      populateServicePrices(row);
      setDefaultQuantity(row);
      calculateServiceOrderTotals();
    });

    productSelect?.addEventListener('change', () => {
      const option = productSelect.selectedOptions[0];

      if (!option?.value) {
        return;
      }

      typeSelect.value = 'product';
      serviceSelect.value = '';
      description.value = option.dataset.name || option.textContent.trim();
      unitPrice.value = option.dataset.salePrice || '0';
      populateServicePrices(row);
      setDefaultQuantity(row);
      calculateServiceOrderTotals();
    });

    priceSelect?.addEventListener('change', () => {
      if (unitPrice && priceSelect.value !== '') {
        unitPrice.value = priceSelect.value;
        calculateServiceOrderTotals();
      }
    });

    typeSelect?.addEventListener('change', calculateServiceOrderTotals);
    row.querySelector('[data-service-order-quantity]')?.addEventListener('input', calculateServiceOrderTotals);
    unitPrice?.addEventListener('input', calculateServiceOrderTotals);
    row.querySelector('[data-service-order-clear-item]')?.addEventListener('click', () => clearServiceOrderRow(row));

    populateServicePrices(row, true);
  });

  clinicSelect?.addEventListener('change', filterServiceOrderCatalog);
  tutorSelect?.addEventListener('change', filterPatients);
  patientSelect?.addEventListener('change', () => {
    const tutorId = patientSelect.selectedOptions[0]?.dataset.tutorId || '';

    if (tutorSelect && tutorId && !tutorSelect.value) {
      tutorSelect.value = tutorId;
      filterPatients();
    }
  });
  discountInput?.addEventListener('input', calculateServiceOrderTotals);

  patientSelect?.addEventListener('change', () => {
    itemRows.forEach((row) => {
      if (row.querySelector('[data-service-order-service-select]')?.value) {
        populateServicePrices(row, false);
      }
    });
    calculateServiceOrderTotals();
  });

  const packageHint = serviceOrderForm.querySelector('[data-service-order-package-hint]');
  const refreshPackageHint = () => {
    const summary = patientSelect?.selectedOptions[0]?.dataset.packageSummary || '';
    if (packageHint) {
      packageHint.textContent = summary ? `Pacote ativo — ${summary}` : '';
      packageHint.hidden = !summary;
    }
  };
  patientSelect?.addEventListener('change', refreshPackageHint);
  refreshPackageHint();

  initGroomingScheduling(serviceOrderForm, itemRows, currentClinicId);

  filterServiceOrderCatalog();
  calculateServiceOrderTotals();
}
}

function initGroomingScheduling(form, itemRows, currentClinicId) {
  const scheduledInput = form.querySelector('[data-grooming-scheduled-at]');
  const durationInput = form.querySelector('[data-grooming-duration]');
  const userSelect = form.querySelector('[data-service-order-assigned-user-select]');
  const panel = form.querySelector('[data-grooming-availability]');
  const chips = form.querySelector('[data-grooming-slots]');
  const status = form.querySelector('[data-grooming-slots-status]');
  const url = form.dataset.availabilityUrl;

  if (!scheduledInput || !panel || !chips || !url) {
    return;
  }

  const servicesDuration = () => itemRows.reduce((total, row) => {
    const type = row.querySelector('[data-service-order-item-type]')?.value || 'service';
    const option = row.querySelector('[data-service-order-service-select]')?.selectedOptions[0];
    const quantity = Number.parseFloat(row.querySelector('[data-service-order-quantity]')?.value || '1') || 1;

    if (type !== 'service' || !option?.value) {
      return total;
    }

    return total + ((Number.parseInt(option.dataset.duration || '0', 10) || 0) * Math.max(1, Math.round(quantity)));
  }, 0);

  const effectiveDuration = () => {
    const typed = Number.parseInt(durationInput?.value || '', 10);

    return typed > 0 ? typed : (servicesDuration() || 60);
  };

  const refreshDurationHint = () => {
    if (durationInput) {
      const estimate = servicesDuration();
      durationInput.placeholder = estimate > 0 ? `${estimate} (soma dos serviços)` : '60 (padrão)';
    }
  };

  let timer = null;
  let requestId = 0;

  const loadSlots = () => {
    const value = scheduledInput.value || '';
    const date = value.slice(0, 10);

    if (!date || !userSelect?.value) {
      panel.hidden = true;
      return;
    }

    const params = new URLSearchParams({
      date,
      assigned_user_id: userSelect.value,
      duration_minutes: String(effectiveDuration()),
    });

    const clinicId = currentClinicId();
    if (clinicId) params.set('clinic_id', clinicId);
    if (form.dataset.orderId) params.set('ignore_id', form.dataset.orderId);

    const current = ++requestId;
    panel.hidden = false;
    status.textContent = 'Buscando horários livres…';
    chips.replaceChildren();

    fetch(`${url}?${params.toString()}`, { headers: { Accept: 'application/json' } })
      .then((response) => (response.ok ? response.json() : Promise.reject(response)))
      .then((data) => {
        if (current !== requestId) return;
        const slots = Array.isArray(data.slots) ? data.slots : [];
        const selectedTime = value.slice(11, 16);

        slots.forEach((slot) => {
          const chip = document.createElement('button');
          chip.type = 'button';
          chip.className = `grooming-slot-chip${slot === selectedTime ? ' is-selected' : ''}`;
          chip.textContent = slot;
          chip.setAttribute('role', 'listitem');
          chip.addEventListener('click', () => {
            scheduledInput.value = `${date}T${slot}`;
            chips.querySelectorAll('.grooming-slot-chip').forEach((item) => item.classList.remove('is-selected'));
            chip.classList.add('is-selected');
          });
          chips.appendChild(chip);
        });

        status.textContent = slots.length
          ? `${slots.length} horário(s) livre(s) para ${effectiveDuration()} min. Clique para escolher.`
          : 'Nenhum horário livre neste dia para essa duração. Use "Encaixe" ou escolha outro dia/profissional.';
      })
      .catch(() => {
        if (current !== requestId) return;
        status.textContent = 'Não foi possível carregar os horários livres agora.';
      });
  };

  const scheduleLoad = () => {
    window.clearTimeout(timer);
    timer = window.setTimeout(loadSlots, 250);
  };

  scheduledInput.addEventListener('change', scheduleLoad);
  userSelect?.addEventListener('change', scheduleLoad);
  durationInput?.addEventListener('input', scheduleLoad);
  itemRows.forEach((row) => {
    ['[data-service-order-service-select]', '[data-service-order-item-type]'].forEach((selector) => {
      row.querySelector(selector)?.addEventListener('change', () => {
        refreshDurationHint();
        scheduleLoad();
      });
    });
  });

  const recurrence = form.querySelector('[data-grooming-recurrence]');
  const recurrenceCount = form.querySelector('[data-grooming-recurrence-count]');
  const toggleRecurrence = () => {
    const field = recurrenceCount?.closest('.field');
    if (field) field.hidden = !recurrence?.value;
  };
  recurrence?.addEventListener('change', toggleRecurrence);
  toggleRecurrence();

  refreshDurationHint();
  loadSlots();
}


export function initPatientSizeHint() {
  const weight = document.querySelector('[data-patient-weight]');
  const size = document.querySelector('[data-patient-size]');
  const hint = document.querySelector('[data-patient-size-hint]');

  if (!weight || !size || !hint) {
    return;
  }

  const defaultHint = hint.textContent;
  const labels = { small: 'Pequeno', medium: 'Médio', large: 'Grande', giant: 'Gigante' };
  const suggest = (kg) => {
    if (!(kg > 0)) return null;
    if (kg <= 10) return 'small';
    if (kg <= 25) return 'medium';
    if (kg <= 45) return 'large';
    return 'giant';
  };

  const update = () => {
    const suggestion = suggest(Number.parseFloat(String(weight.value || '').replace(',', '.')));

    hint.textContent = !size.value && suggestion
      ? `Pelo peso, o porte será ${labels[suggestion]}. Escolha outro se preferir.`
      : defaultHint;
  };

  weight.addEventListener('input', update);
  size.addEventListener('change', update);
  update();
}
