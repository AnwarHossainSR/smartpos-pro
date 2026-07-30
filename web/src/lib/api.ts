import { useAuth } from "@/stores/auth";
import { toast } from "@/components/ui/toast";

export const API_URL =
  process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8080/api/v1";

export class ApiError extends Error {
  constructor(
    public status: number,
    message: string,
    public errors?: Record<string, string[]>,
    public payload?: unknown,
  ) {
    super(message);
  }
}

interface RequestOptions extends Omit<RequestInit, "body"> {
  body?: unknown;
  /** Send an Idempotency-Key header (used for POS checkout retries). */
  idempotencyKey?: string;
}

/** Thin fetch wrapper: injects the bearer token, JSON-encodes, and normalises errors. */
export async function api<T = unknown>(
  path: string,
  { body, idempotencyKey, headers, ...init }: RequestOptions = {},
): Promise<T> {
  const token = useAuth.getState().token;

  const res = await fetch(`${API_URL}${path}`, {
    ...init,
    headers: {
      Accept: "application/json",
      ...(body ? { "Content-Type": "application/json" } : {}),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(idempotencyKey ? { "Idempotency-Key": idempotencyKey } : {}),
      ...headers,
    },
    body: body ? JSON.stringify(body) : undefined,
  });

  if (res.status === 401) {
    useAuth.getState().logout();
  }

  if (res.status === 429) {
    const retryAfter = res.headers.get("Retry-After");
    toast(
      retryAfter ? `Too many requests. Try again in ${retryAfter}s.` : "Too many requests. Please slow down.",
      "error",
    );
  }

  const data = res.status === 204 ? null : await res.json().catch(() => null);

  if (!res.ok) {
    const payload = data as { message?: string; errors?: Record<string, string[]> } | null;
    throw new ApiError(
      res.status,
      payload?.message ?? `Request failed (${res.status})`,
      payload?.errors,
      data,
    );
  }

  return data as T;
}

/** Fetches a binary response (e.g. a generated PDF) with the same auth header as `api()`. */
export async function apiBlob(path: string): Promise<Blob> {
  const token = useAuth.getState().token;

  const res = await fetch(`${API_URL}${path}`, {
    headers: token ? { Authorization: `Bearer ${token}` } : {},
  });

  if (res.status === 401) {
    useAuth.getState().logout();
  }

  if (!res.ok) {
    throw new ApiError(res.status, `Request failed (${res.status})`);
  }

  return res.blob();
}

export interface Paginated<T> {
  data: T[];
  meta?: { current_page: number; last_page: number; total: number };
  links?: unknown;
}
