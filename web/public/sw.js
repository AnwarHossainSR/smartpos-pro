const CACHE_NAME = "smartpos-v1";
const SHELL = ["/", "/dashboard", "/pos", "/offline.html"];

// ----- Install: cache shell -----
self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) =>
      cache.addAll(SHELL).catch(() => {})
    )
  );
  self.skipWaiting();
});

// ----- Activate: prune old caches -----
self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k)))
    )
  );
  self.clients.claim();
});

// ----- Fetch: network-first for API, cache-first for static -----
self.addEventListener("fetch", (event) => {
  const { request } = event;
  if (request.method !== "GET") return;

  const url = new URL(request.url);

  // Always network for API calls
  if (url.pathname.startsWith("/api/")) return;

  event.respondWith(
    caches.match(request).then((cached) => {
      const networkFetch = fetch(request)
        .then((response) => {
          if (response.ok) {
            const clone = response.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
          }
          return response;
        })
        .catch(() => cached || caches.match("/offline.html"));

      // For navigation, go network first (so we get fresh Next.js pages)
      if (request.mode === "navigate") return networkFetch;

      // For assets, return cached immediately then revalidate
      return cached || networkFetch;
    })
  );
});

// ----- Background sync: offline POS sales -----
const SYNC_TAG = "pos-sale-sync";
const IDB_DB = "smartpos-offline";
const IDB_STORE = "pending-sales";

self.addEventListener("sync", (event) => {
  if (event.tag === SYNC_TAG) {
    event.waitUntil(syncPendingSales());
  }
});

async function openDB() {
  return new Promise((resolve, reject) => {
    const req = indexedDB.open(IDB_DB, 1);
    req.onupgradeneeded = () => {
      req.result.createObjectStore(IDB_STORE, { autoIncrement: true, keyPath: "_id" });
    };
    req.onsuccess = () => resolve(req.result);
    req.onerror = reject;
  });
}

async function syncPendingSales() {
  const db = await openDB();

  const sales = await new Promise((resolve) => {
    const tx = db.transaction(IDB_STORE, "readonly");
    const req = tx.objectStore(IDB_STORE).getAll();
    req.onsuccess = () => resolve(req.result || []);
    req.onerror = () => resolve([]);
  });

  for (const sale of sales) {
    try {
      const res = await fetch("/api/v1/sales", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Authorization": sale.token ? `Bearer ${sale.token}` : "",
          "Idempotency-Key": sale.idempotencyKey,
        },
        body: JSON.stringify(sale.payload),
      });

      if (res.ok || res.status === 409) {
        const tx2 = db.transaction(IDB_STORE, "readwrite");
        tx2.objectStore(IDB_STORE).delete(sale._id);
      }
    } catch {
      // Will retry on next sync event
    }
  }
}
