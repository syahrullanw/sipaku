const PRECACHE_VERSION = "siakad-v1";
const PRECACHE_CACHE = `${PRECACHE_VERSION}-precache`;
const RUNTIME_CACHE = `${PRECACHE_VERSION}-runtime`;
const OFFLINE_FALLBACK_URL = "/";

const PRECACHE_URLS = [
  "/",
  "/manifest.webmanifest",
  "/icons/icon-192.png",
  "/icons/icon-512.png",
  "/icons/apple-touch-icon.png",
  "/css/admin.css"
];

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(PRECACHE_CACHE).then(async (cache) => {
      await Promise.all(
        PRECACHE_URLS.map(async (url) => {
          try {
            await cache.add(url);
          } catch (error) {
            console.warn(`[ServiceWorker] Precache skipped for ${url}:`, error);
          }
        })
      );
    })
  );
  self.skipWaiting();
});

self.addEventListener("activate", (event) => {
  const expectedCaches = [PRECACHE_CACHE, RUNTIME_CACHE];
  event.waitUntil(
    caches
      .keys()
      .then((cacheNames) =>
        Promise.all(
          cacheNames
          .filter((cacheName) => !expectedCaches.includes(cacheName))
          .map((cacheName) => caches.delete(cacheName))
        )
      )
      .then(() => self.clients.claim())
  );
});

self.addEventListener("fetch", (event) => {
  if (event.request.method !== "GET") {
    return;
  }

  const url = new URL(event.request.url);

  if (event.request.mode === "navigate") {
    event.respondWith(
      fetch(event.request).catch(async () => {
        const fallbackResponse = await caches.match(OFFLINE_FALLBACK_URL, { ignoreSearch: true });
        if (fallbackResponse) {
          return fallbackResponse;
        }
        const precached = await caches.match(url.pathname, { ignoreSearch: true });
        return precached ?? Response.error();
      })
    );
    return;
  }

  if (url.origin === self.location.origin && PRECACHE_URLS.includes(url.pathname)) {
    event.respondWith(
      caches.match(event.request, { ignoreSearch: true }).then((cacheHit) => {
        if (cacheHit) {
          return cacheHit;
        }
        return fetch(event.request).catch(async () => {
          const fallback = await caches.match(OFFLINE_FALLBACK_URL, { ignoreSearch: true });
          if (fallback) {
            return fallback;
          }
          return Response.error();
        });
      })
    );
    return;
  }

  event.respondWith(
    caches.open(RUNTIME_CACHE).then((cache) =>
      fetch(event.request)
        .then((response) => {
          if (
            response &&
            response.status === 200 &&
            response.type === "basic" &&
            url.origin === self.location.origin
          ) {
            cache.put(event.request, response.clone());
          }
          return response;
        })
        .catch(async () => {
          const cached = await caches.match(event.request, { ignoreSearch: true });
          if (cached) {
            return cached;
          }
          if (url.origin === self.location.origin) {
            const fallback = await caches.match(OFFLINE_FALLBACK_URL, { ignoreSearch: true });
            if (fallback) {
              return fallback;
            }
          }
          return Response.error();
        })
    )
  );
});

self.addEventListener("message", (event) => {
  if (event.data && event.data.type === "SKIP_WAITING") {
    self.skipWaiting();
  }
});
