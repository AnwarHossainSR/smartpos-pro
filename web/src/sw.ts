/// <reference lib="webworker" />
import { precacheAndRoute } from "workbox-precaching";

// `self` is typed as a Window in a plain .ts file; the SW runs in a worker scope.
declare const self: ServiceWorkerGlobalScope;

precacheAndRoute(self.__WB_MANIFEST);

const CACHE_NAME = "smartpos-v1";
const SHELL = ["/", "/dashboard", "/pos", "/offline.html"];

self.addEventListener("install", (event: ExtendableEvent) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) =>
      cache.addAll(SHELL).catch(() => {})
    )
  );
  self.skipWaiting();
});

self.addEventListener("activate", (event: ExtendableEvent) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k)))
    )
  );
  self.clients.claim();
});

self.addEventListener("fetch", (event: FetchEvent) => {
  const { request } = event;
  if (request.method !== "GET") return;

  const url = new URL(request.url);

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
        // `caches.match` can miss, and respondWith() rejects on undefined — always
        // resolve to a real Response so a failed navigation shows the offline page
        // rather than the browser's own network error.
        .catch(async () =>
          cached ??
          (await caches.match("/offline.html")) ??
          new Response("Offline", { status: 504, statusText: "Gateway Timeout" }),
        );

      if (request.mode === "navigate") return networkFetch;

      return cached || networkFetch;
    })
  );
});

interface SyncEvent extends ExtendableEvent {
  readonly tag: string;
}

interface PendingSale {
  _id: number;
  token?: string;
  idempotencyKey: string;
  payload: unknown;
}

const API_BASE = import.meta.env.VITE_API_URL ?? "/api/v1";

const SYNC_TAG = "pos-sale-sync";
const IDB_DB = "smartpos-offline";
const IDB_STORE = "pending-sales";

// Background Sync is not in TypeScript's ServiceWorkerGlobalScopeEventMap yet.
self.addEventListener("sync", ((event: SyncEvent) => {
  if (event.tag === SYNC_TAG) {
    event.waitUntil(syncPendingSales());
  }
}) as EventListener);

async function openDB(): Promise<IDBDatabase> {
  return new Promise<IDBDatabase>((resolve, reject) => {
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

  const sales = await new Promise<PendingSale[]>((resolve) => {
    const tx = db.transaction(IDB_STORE, "readonly");
    const req = tx.objectStore(IDB_STORE).getAll();
    req.onsuccess = () => resolve((req.result as PendingSale[]) || []);
    req.onerror = () => resolve([]);
  });

  for (const sale of sales) {
    try {
      // Same base the app uses. Hardcoding "/api/v1" here only worked because the dev
      // server proxies it; in production the API is a different origin, so a replayed
      // offline sale would have POSTed to the static host and silently failed.
      const res = await fetch(`${API_BASE}/sales`, {
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
