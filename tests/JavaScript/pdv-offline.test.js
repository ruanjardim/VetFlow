import assert from 'node:assert/strict';
import test from 'node:test';

import {
  reserveOfflineStock,
  searchOfflineCatalog,
  shouldUseOfflineCatalog,
} from '../../resources/js/pdv-offline.js';

const catalog = {
  complete: true,
  items: [
    {
      type: 'product',
      product_id: 6,
      description: 'Shampoo Fictício Homologação 06BAAEC',
      sku: 'HOMO-06BAAEC',
      unit_price: 25,
      stock_quantity: 4,
    },
  ],
};

test('offline and unavailable states bypass the server search', () => {
  assert.equal(shouldUseOfflineCatalog('offline'), true);
  assert.equal(shouldUseOfflineCatalog('unavailable'), true);
  assert.equal(shouldUseOfflineCatalog('online'), false);
  assert.equal(shouldUseOfflineCatalog('checking'), false);
});

test('the encrypted snapshot search finds the homologation product by name or sku', () => {
  assert.equal(searchOfflineCatalog(catalog, 'shampoo ficticio')[0]?.product_id, 6);
  assert.equal(searchOfflineCatalog(catalog, 'HOMO-06BAAEC')[0]?.product_id, 6);
  assert.deepEqual(searchOfflineCatalog(catalog, 'produto ausente'), []);
});

test('consecutive offline sales reserve stock in the local snapshot', () => {
  const snapshot = structuredClone(catalog);
  const firstSale = [
    ['items[0][product_id]', '6'],
    ['items[0][quantity]', '1.5'],
  ];
  const secondSale = [
    ['items[0][product_id]', '6'],
    ['items[0][quantity]', '1'],
  ];

  reserveOfflineStock(snapshot, firstSale);
  reserveOfflineStock(snapshot, secondSale);

  assert.equal(snapshot.items[0].stock_quantity, 1.5);
});
