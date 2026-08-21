import { useAuth } from "@/stores/auth";
import { toast } from "@/components/ui/toast";

export const API_URL =
  import.meta.env.VITE_API_URL ?? "http://localhost:8080/api/v1";

export class ApiError extends Error {
  constructor(
    public status: number,
    message: string,
    public errors?: Record<string, string[]>,
    public payload?: unknown,
  ) {
    super(message);
    this.name = "ApiError";
  }
}

/** Extracts a human message from an unknown thrown value — used by every mutation's onError. */
export function errorMessage(e: unknown, fallback = "Something went wrong"): string {
  if (e instanceof ApiError) return e.message;
  if (e instanceof Error && e.message) return e.message;
  return fallback;
}

/**
 * Auth endpoints answer 401 for "wrong password" / "bad reset token", which is not the same
 * thing as "your session expired". Logging out on those would wipe a session the user is
 * still holding (e.g. a failed re-auth prompt inside the app), so they are exempt.
 */
const NO_LOGOUT_ON_401 = [
  "/auth/login",
  "/auth/register",
  "/auth/forgot-password",
  "/auth/reset-password",
  "/auth/two-factor",
];

interface RequestOptions extends Omit<RequestInit, "body"> {
  body?: unknown;
  /** Send an Idempotency-Key header (used for POS checkout retries). */
  idempotencyKey?: string;
}

function authHeader(): Record<string, string> {
  const token = useAuth.getState().token;
  return token ? { Authorization: `Bearer ${token}` } : {};
}

/**
 * Response → data or `ApiError`, with no session side effects.
 *
 * Split out of `handle()` so a caller that authenticates differently can reuse the exact
 * same 429 toast and `{ message, errors }` Laravel envelope handling while owning its own
 * 401 policy: the customer portal (`src/lib/portal.ts`) carries a magic-link token and
 * redirects to `/portal` instead of logging the staff session out. It used to re-implement
 * all of this and quietly lost `.status` and field-level `.errors` in the process.
 */
export async function parseResponse(res: Response, expectBlob = false): Promise<unknown> {
  if (res.status === 429) {
    const retryAfter = res.headers.get("Retry-After");
    toast(
      retryAfter
        ? `Too many requests. Try again in ${retryAfter}s.`
        : "Too many requests. Please slow down.",
      "error",
    );
  }

  if (expectBlob && res.ok) return res.blob();

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

  return data;
}

/**
 * The one place a staff-API response is turned into either data or an ApiError. Every
 * exported helper below funnels through it, so an expired session logs out identically for
 * JSON requests, uploads and blob downloads.
 */
async function handle(res: Response, path: string, expectBlob = false): Promise<unknown> {
  if (res.status === 401 && !NO_LOGOUT_ON_401.some((p) => path.startsWith(p))) {
    useAuth.getState().logout();
  }

  return parseResponse(res, expectBlob);
}

/** Thin fetch wrapper: injects the bearer token, JSON-encodes, and normalises errors. */
export async function api<T = unknown>(
  path: string,
  { body, idempotencyKey, headers, ...init }: RequestOptions = {},
): Promise<T> {
  const res = await fetch(`${API_URL}${path}`, {
    ...init,
    headers: {
      Accept: "application/json",
      ...(body !== undefined ? { "Content-Type": "application/json" } : {}),
      ...authHeader(),
      ...(idempotencyKey ? { "Idempotency-Key": idempotencyKey } : {}),
      ...headers,
    },
    body: body !== undefined ? JSON.stringify(body) : undefined,
  });

  return (await handle(res, path)) as T;
}

/** Fetches a binary response (e.g. a generated PDF) with the same auth header as `api()`. */
export async function apiBlob(path: string, init: RequestInit = {}): Promise<Blob> {
  const res = await fetch(`${API_URL}${path}`, {
    ...init,
    headers: { ...authHeader(), ...init.headers },
  });

  return (await handle(res, path, true)) as Blob;
}

/** Upload a file via multipart/form-data (no Content-Type header — let fetch set the boundary). */
export async function apiUpload<T = unknown>(
  path: string,
  formData: FormData,
  method: string = "POST",
): Promise<T> {
  const res = await fetch(`${API_URL}${path}`, {
    method,
    headers: { Accept: "application/json", ...authHeader() },
    body: formData,
  });

  return (await handle(res, path)) as T;
}

export interface Paginated<T> {
  data: T[];
  meta?: { current_page: number; last_page: number; total: number };
  links?: unknown;
}

/** The `{ data: T }` envelope every single-record endpoint returns. */
export interface Envelope<T> {
  data: T;
}
