import { useSyncExternalStore } from "react";
import { API_URL, parseResponse } from "@/lib/api";

const PORTAL_TOKEN_KEY = "portal_token";

/**
 * The customer portal authenticates with its own magic-link token in localStorage — separate
 * from the staff bearer token in `src/stores/auth.ts`, and never sent to the staff API. That
 * token, and sending an expired one back to `/portal` rather than logging the staff session
 * out, is the *only* thing that differs from `api()`: the response itself is parsed by the
 * shared `parseResponse()`, so the portal raises the same `ApiError` (with `.status` and
 * field errors) that every other screen already knows how to render.
 */
export async function portalFetch<T>(path: string, init: RequestInit = {}): Promise<T> {
  const token = typeof window !== "undefined" ? localStorage.getItem(PORTAL_TOKEN_KEY) : null;

  const res = await fetch(`${API_URL}${path}`, {
    ...init,
    headers: {
      Accept: "application/json",
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...init.headers,
    },
  });

  if (res.status === 401) window.location.href = "/portal";

  return (await parseResponse(res)) as T;
}

export function setPortalToken(token: string) {
  localStorage.setItem(PORTAL_TOKEN_KEY, token);
}

export function clearPortalToken() {
  localStorage.removeItem(PORTAL_TOKEN_KEY);
}

const subscribe = () => () => {
  /* no-op */
};
const getSnapshot = () => localStorage.getItem(PORTAL_TOKEN_KEY);
const getServerSnapshot = () => null;

/**
 * The portal token, read the way React wants a browser-only value read: `null` on the server
 * and during hydration, then the real value. Reading it in an effect and calling `setState`
 * would work too, but that is the cascading-render pattern the React compiler rule flags —
 * and it renders one extra frame of "not ready" on every mount.
 */
export function usePortalToken(): string | null {
  return useSyncExternalStore(subscribe, getSnapshot, getServerSnapshot);
}
