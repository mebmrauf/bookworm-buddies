// Only provides the offline page. Pages are never stored, because they can hold private details
// that must not outlive a sign-out. Assets already cache in the browser for a year.
const CACHE = 'bookworm-offline-v1';
const OFFLINE_URL = '/offline.html';

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE).then((cache) => cache.add(OFFLINE_URL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    const names = await caches.keys();
    await Promise.all(names.filter((name) => name !== CACHE).map((name) => caches.delete(name)));
    // Avoids a slowdown: the page request starts while the worker is still waking up.
    await self.registration.navigationPreload?.enable();
    await self.clients.claim();
  })());
});

self.addEventListener('fetch', (event) => {
  if (event.request.mode !== 'navigate') return;
  event.respondWith((async () => {
    try {
      return (await event.preloadResponse) || (await fetch(event.request));
    } catch (error) {
      return caches.match(OFFLINE_URL);
    }
  })());
});
