const CACHE_NAME = 'vetflow-pdv-contingency-v1';
const OFFLINE_SHELL_PATH = '/sales/pdv/offline-shell';

const cacheableAsset = (value) => {
  try {
    const url = new URL(value, self.location.origin);
    return url.origin === self.location.origin
      && (url.pathname.startsWith('/build/assets/') || url.pathname === '/favicon.svg');
  } catch {
    return false;
  }
};

const shellAssetUrls = (html) => Array.from(html.matchAll(/\b(?:src|href)=["']([^"']+)["']/gi))
  .map((match) => new URL(match[1], self.location.origin).toString())
  .filter(cacheableAsset);

const prepareOfflineShell = async (shellUrl) => {
  const url = new URL(shellUrl || OFFLINE_SHELL_PATH, self.location.origin);
  if (url.origin !== self.location.origin || url.pathname !== OFFLINE_SHELL_PATH) {
    throw new Error('Invalid offline shell URL.');
  }

  const response = await fetch(url.toString(), {
    credentials: 'same-origin',
    cache: 'no-store',
    headers: { Accept: 'text/html' },
  });
  if (!response.ok || response.headers.get('X-VetFlow-PDV-Shell') !== '1') {
    throw new Error('Offline shell was not authorized.');
  }

  const cache = await caches.open(CACHE_NAME);
  const html = await response.clone().text();
  const assetUrls = shellAssetUrls(html);
  await Promise.all(assetUrls.map(async (assetUrl) => {
    const assetResponse = await fetch(assetUrl, { cache: 'no-store' });
    if (!assetResponse.ok) throw new Error('Offline asset was not available.');
    await cache.put(assetUrl, assetResponse);
  }));
  await cache.put(OFFLINE_SHELL_PATH, response);

  const keep = new Set([new URL(OFFLINE_SHELL_PATH, self.location.origin).toString(), ...assetUrls]);
  const cachedRequests = await cache.keys();
  await Promise.all(cachedRequests
    .filter((request) => request.url.includes('/build/assets/') && !keep.has(request.url))
    .map((request) => cache.delete(request)));
};

const canUseOfflineShell = (url) => {
  if (url.pathname !== '/sales/create') return false;
  const unsupported = ['mode', 'service_order_id', 'pet_package_id', 'quote_id'];
  return unsupported.every((parameter) => !url.searchParams.has(parameter));
};

const counterNavigation = async (request) => {
  try {
    const response = await fetch(request);
    if (response.status < 500) return response;
  } catch {
    // Fall through to the prepared shell.
  }
  const cached = await caches.match(OFFLINE_SHELL_PATH);
  return cached || new Response('O PDV offline ainda não foi preparado neste dispositivo.', {
    status: 503,
    headers: { 'Content-Type': 'text/plain; charset=utf-8' },
  });
};

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    const names = await caches.keys();
    await Promise.all(names
      .filter((name) => name.startsWith('vetflow-pdv-contingency-') && name !== CACHE_NAME)
      .map((name) => caches.delete(name)));
    await self.clients.claim();
  })());
});

self.addEventListener('message', (event) => {
  if (event.data?.type !== 'PREPARE_PDV_OFFLINE') return;
  event.waitUntil(prepareOfflineShell(event.data.shellUrl)
    .then(() => event.ports[0]?.postMessage({ ok: true }))
    .catch(() => event.ports[0]?.postMessage({ ok: false })));
});

self.addEventListener('fetch', (event) => {
  const { request } = event;
  if (request.method !== 'GET') return;
  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;

  if (request.mode === 'navigate' && canUseOfflineShell(url)) {
    event.respondWith(counterNavigation(request));
    return;
  }

  if (request.mode === 'navigate' && url.pathname === OFFLINE_SHELL_PATH) {
    event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_SHELL_PATH)));
    return;
  }

  if (url.pathname.startsWith('/build/assets/')) {
    event.respondWith(caches.match(request).then((cached) => cached || fetch(request)));
  }
});
