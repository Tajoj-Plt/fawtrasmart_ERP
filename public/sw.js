const CACHE_NAME = 'pos-cache-v1';
const urlsToCache = [
  '/ar/POS/sales-start',
  // Add other critical assets here if offline support is needed, but for now we just want installability.
];

self.addEventListener('install', event => {
  self.skipWaiting();
});

self.addEventListener('activate', event => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', event => {
  // Simple fetch-through strategy. 
  // We are not implementing full offline support yet to avoid breaking dynamic POS functionality.
  event.respondWith(fetch(event.request));
});
