// Only provides the page shown when the site can't be reached. Pages are never stored, because they can hold private details
// that must not outlive a sign-out. Assets already cache in the browser for a year.
const CACHE = 'bookworm-offline-v3';
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
      const response = (await event.preloadResponse) || (await fetch(event.request));
      // The host answers with its own error page while the server restarts, and that says nothing useful.
      return response.status === 502 || response.status === 503 || response.status === 504 ? await caches.match(OFFLINE_URL) : response;
    } catch (error) {
      return caches.match(OFFLINE_URL);
    }
  })());
});
