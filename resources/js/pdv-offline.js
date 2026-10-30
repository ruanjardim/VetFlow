const DATABASE_NAME = 'vetflow-pdv-offline-v1';
const DATABASE_VERSION = 1;
const META_STORE = 'meta';
const SECURE_STORE = 'secure_records';
const KEY_ID = 'encryption-key';
const DEVICE_ID = 'device-id';
const textEncoder = new TextEncoder();
const textDecoder = new TextDecoder();

const requestResult = (request) => new Promise((resolve, reject) => {
  request.addEventListener('success', () => resolve(request.result), { once: true });
  request.addEventListener('error', () => reject(request.error || new Error('Falha no armazenamento local.')), { once: true });
});

const transactionDone = (transaction) => new Promise((resolve, reject) => {
  transaction.addEventListener('complete', resolve, { once: true });
  transaction.addEventListener('abort', () => reject(transaction.error || new Error('Gravação local cancelada.')), { once: true });
  transaction.addEventListener('error', () => reject(transaction.error || new Error('Falha na gravação local.')), { once: true });
});

const openDatabase = () => new Promise((resolve, reject) => {
  const request = window.indexedDB.open(DATABASE_NAME, DATABASE_VERSION);

  request.addEventListener('upgradeneeded', () => {
    const database = request.result;
    if (!database.objectStoreNames.contains(META_STORE)) {
      database.createObjectStore(META_STORE, { keyPath: 'key' });
    }
    if (!database.objectStoreNames.contains(SECURE_STORE)) {
      const store = database.createObjectStore(SECURE_STORE, { keyPath: 'id' });
      store.createIndex('kind', 'kind', { unique: false });
      store.createIndex('clinic', 'clinicId', { unique: false });
    }
  }, { once: true });
  request.addEventListener('success', () => resolve(request.result), { once: true });
  request.addEventListener('error', () => reject(request.error || new Error('O navegador não abriu a base offline.')), { once: true });
  request.addEventListener('blocked', () => reject(new Error('Feche outras abas antigas do PDV e tente novamente.')), { once: true });
});

const normalizeSearch = (value) => String(value || '')
  .normalize('NFD')
  .replace(/[\u0300-\u036f]/g, '')
  .toLocaleLowerCase('pt-BR')
  .trim();

export class PdvOfflineStore {
  static supported() {
    return Boolean(window.indexedDB && window.crypto?.subtle && window.crypto?.randomUUID);
  }

  static async open() {
    if (!this.supported()) {
      throw new Error('Este navegador não oferece armazenamento criptografado para vendas offline.');
    }

    const database = await openDatabase();
    const store = new PdvOfflineStore(database);
    await store.initialize();

    return store;
  }

  constructor(database) {
    this.database = database;
    this.key = null;
    this.deviceId = null;
  }

  async initialize() {
    const readTransaction = this.database.transaction(META_STORE, 'readonly');
    const readDone = transactionDone(readTransaction);
    const readMeta = readTransaction.objectStore(META_STORE);
    const keyRequest = readMeta.get(KEY_ID);
    const deviceRequest = readMeta.get(DEVICE_ID);
    const [existingKey, existingDevice] = await Promise.all([
      requestResult(keyRequest),
      requestResult(deviceRequest),
    ]);
    await readDone;

    this.key = existingKey?.value || await window.crypto.subtle.generateKey(
      { name: 'AES-GCM', length: 256 },
      false,
      ['encrypt', 'decrypt']
    );
    this.deviceId = existingDevice?.value || window.crypto.randomUUID();

    if (!existingKey || !existingDevice) {
      const writeTransaction = this.database.transaction(META_STORE, 'readwrite');
      const writeDone = transactionDone(writeTransaction);
      const writeMeta = writeTransaction.objectStore(META_STORE);
      if (!existingKey) writeMeta.put({ key: KEY_ID, value: this.key });
      if (!existingDevice) writeMeta.put({ key: DEVICE_ID, value: this.deviceId });
      await writeDone;
    }
  }

  async saveCatalog(clinicId, catalog) {
    return this.putSecure({
      id: `catalog:${clinicId}`,
      kind: 'catalog',
      clinicId: String(clinicId),
      state: catalog.complete ? 'ready' : 'incomplete',
      createdAt: catalog.generated_at || new Date().toISOString(),
    }, catalog);
  }

  async catalog(clinicId) {
    return this.getSecure(`catalog:${clinicId}`);
  }

  async queueSale(sale) {
    await this.putSecure({
      id: `sale:${sale.id}`,
      kind: 'sale',
      clinicId: String(sale.clinicId),
      state: 'pending',
      createdAt: sale.capturedAt,
    }, sale);

    return sale;
  }

  async queuedSales() {
    const transaction = this.database.transaction(SECURE_STORE, 'readonly');
    const request = transaction.objectStore(SECURE_STORE).index('kind').getAll('sale');
    const records = await requestResult(request);

    return Promise.all(records
      .sort((left, right) => String(left.createdAt).localeCompare(String(right.createdAt)))
      .map(async (record) => ({
        ...await this.decrypt(record),
        localState: record.state,
        localDetail: record.detail || '',
      })));
  }

  async setSaleState(id, state, detail = '') {
    const transaction = this.database.transaction(SECURE_STORE, 'readwrite');
    const done = transactionDone(transaction);
    const store = transaction.objectStore(SECURE_STORE);
    const request = store.get(`sale:${id}`);

    request.addEventListener('success', () => {
      const record = request.result;
      if (!record) return;

      record.state = state;
      record.detail = String(detail || '').slice(0, 500);
      record.updatedAt = new Date().toISOString();
      store.put(record);
    }, { once: true });

    await done;
  }

  async removeSale(id) {
    const transaction = this.database.transaction(SECURE_STORE, 'readwrite');
    transaction.objectStore(SECURE_STORE).delete(`sale:${id}`);
    await transactionDone(transaction);
  }

  async putSecure(metadata, value) {
    const encrypted = await this.encrypt(value);
    const transaction = this.database.transaction(SECURE_STORE, 'readwrite');
    transaction.objectStore(SECURE_STORE).put({ ...metadata, ...encrypted });
    await transactionDone(transaction);
  }

  async getSecure(id) {
    const transaction = this.database.transaction(SECURE_STORE, 'readonly');
    const record = await requestResult(transaction.objectStore(SECURE_STORE).get(id));

    return record ? this.decrypt(record) : null;
  }

  async encrypt(value) {
    const iv = window.crypto.getRandomValues(new Uint8Array(12));
    const plaintext = textEncoder.encode(JSON.stringify(value));
    const ciphertext = await window.crypto.subtle.encrypt({ name: 'AES-GCM', iv }, this.key, plaintext);

    return { iv, ciphertext };
  }

  async decrypt(record) {
    const plaintext = await window.crypto.subtle.decrypt(
      { name: 'AES-GCM', iv: record.iv },
      this.key,
      record.ciphertext
    );

    return JSON.parse(textDecoder.decode(plaintext));
  }
}

export const searchOfflineCatalog = (catalog, term, limit = 12) => {
  const query = normalizeSearch(term);
  if (!catalog?.complete || !query) return [];

  return (catalog.items || [])
    .map((item) => {
      const codes = [item.gtin, item.barcode, item.sku].filter(Boolean).map(normalizeSearch);
      const description = normalizeSearch(item.description);
      const exactCode = codes.includes(query);
      const startsWith = codes.some((code) => code.startsWith(query)) || description.startsWith(query);
      const contains = codes.some((code) => code.includes(query)) || description.includes(query);

      return { item, rank: exactCode ? 0 : startsWith ? 1 : contains ? 2 : 3 };
    })
    .filter(({ rank }) => rank < 3)
    .sort((left, right) => left.rank - right.rank || String(left.item.description).localeCompare(String(right.item.description), 'pt-BR'))
    .slice(0, limit)
    .map(({ item }) => ({ ...item, offline_snapshot: true }));
};

export const reserveOfflineStock = (catalog, entries) => {
  if (!catalog?.items) return catalog;

  const quantities = new Map();
  entries.forEach(([name, value]) => {
    const match = name.match(/^items\[(\d+)]\[(product_id|quantity)]$/);
    if (!match) return;
    const current = quantities.get(match[1]) || {};
    current[match[2]] = value;
    quantities.set(match[1], current);
  });

  quantities.forEach(({ product_id: productId, quantity }) => {
    if (!productId) return;
    const item = catalog.items.find((candidate) => String(candidate.product_id || '') === String(productId));
    if (item && item.stock_quantity !== null) {
      item.stock_quantity = Math.max(0, Number(item.stock_quantity || 0) - Number(quantity || 0));
    }
  });

  return catalog;
};
