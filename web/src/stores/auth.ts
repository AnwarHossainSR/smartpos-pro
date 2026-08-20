"use client";

import { create } from "zustand";
import { persist } from "zustand/middleware";
import type { ServerTheme } from "@/stores/theme";
import { splitSlug, type PermissionSlug } from "@/lib/permissions";

export interface AuthUser {
  id: string;
  name: string;
  email: string;
  email_verified_at?: string | null;
  two_factor_enabled?: boolean;
  /** Whether a supervisor approval PIN is set. The PIN itself is never returned. */
  has_approval_pin?: boolean;
  /** Platform staff. Implicitly holds every permission (mirrors HasRoles::hasPermission). */
  is_super_admin?: boolean;
  roles?: string[];
  permissions?: string[];
  business_id?: string | null;
  branch_id?: string | null;
  /** Per-user UI preference synced with the account; `null` until the user picks one. */
  theme?: ServerTheme | null;
}

interface AuthState {
  token: string | null;
  user: AuthUser | null;
  /** False until persisted state has been read back from localStorage / the token cookie. */
  hydrated: boolean;
  setAuth: (token: string, user: AuthUser) => void;
  setUser: (user: AuthUser) => void;
  setHydrated: () => void;
  logout: () => void;
  can: (permission: PermissionSlug) => boolean;
  /** True when the user holds at least one of `permissions`; `[]` means "no grant required". */
  canAny: (permissions: readonly PermissionSlug[]) => boolean;
  /** True only when the user holds every one of `permissions`. */
  canAll: (permissions: readonly PermissionSlug[]) => boolean;
}

/**
 * Pure permission check, exported so route guards and tests can reason about a user object
 * without going through the store. Mirrors `App\Support\Concerns\HasRoles::hasPermission()`:
 *
 *  - platform super admins hold everything;
 *  - the tenant `owner` role holds everything within its tenant;
 *  - otherwise the slug must be in the granted list.
 *
 * A bare `*` (and a `<module>.*` wildcard) is honoured too. The catalog's `admin` role is
 * declared with `'permissions' => '*'` server-side, and while the seeder expands that to
 * concrete slugs today, accepting the wildcard means a future API that ships it literally
 * doesn't lock every admin out of their own UI.
 */
export function hasPermission(user: AuthUser | null, permission: string): boolean {
  if (!user) return false;
  if (user.is_super_admin) return true;
  if (user.roles?.includes("owner")) return true;

  const granted = user.permissions;
  if (!granted?.length) return false;
  if (granted.includes("*")) return true;
  if (granted.includes(permission)) return true;

  const parts = splitSlug(permission);
  return parts ? granted.includes(`${parts.module}.*`) : false;
}

const TOKEN_COOKIE = "smartpos_token";
const TOKEN_COOKIE_MAX_AGE = 60 * 60 * 24 * 14; // 14 days — matches config/sanctum.php default

/**
 * The bearer token lives only in memory (this store) plus a same-site cookie used to
 * survive a page reload — it is never written to localStorage, so an XSS payload that
 * reads localStorage (a common exfiltration target) doesn't get it for free. The cookie
 * isn't httpOnly (the SPA has to read it back into memory itself, and it's on a
 * different origin than the API so the browser never attaches it to API requests
 * automatically), but Secure + SameSite=Strict still block it being sent cross-site.
 */
function readTokenCookie(): string | null {
  if (typeof document === "undefined") return null;
  const match = document.cookie.match(new RegExp(`(?:^|; )${TOKEN_COOKIE}=([^;]*)`));
  return match ? decodeURIComponent(match[1]) : null;
}

function writeTokenCookie(token: string | null) {
  if (typeof document === "undefined") return;
  const secure = typeof location !== "undefined" && location.protocol === "https:" ? "; Secure" : "";
  if (token) {
    document.cookie = `${TOKEN_COOKIE}=${encodeURIComponent(token)}; Path=/; SameSite=Strict; Max-Age=${TOKEN_COOKIE_MAX_AGE}${secure}`;
  } else {
    document.cookie = `${TOKEN_COOKIE}=; Path=/; SameSite=Strict; Max-Age=0${secure}`;
  }
}

export const useAuth = create<AuthState>()(
  persist(
    (set, get): AuthState => ({
      token: null,
      user: null,
      hydrated: false,
      setAuth: (token, user) => {
        writeTokenCookie(token);
        set({ token, user });
      },
      setUser: (user) => set({ user }),
      setHydrated: () => set({ token: readTokenCookie(), hydrated: true }),
      logout: () => {
        writeTokenCookie(null);
        set({ token: null, user: null });
      },
      can: (permission) => hasPermission(get().user, permission),
      canAny: (permissions) =>
        permissions.length === 0 ||
        permissions.some((permission) => hasPermission(get().user, permission)),
      canAll: (permissions) =>
        permissions.every((permission) => hasPermission(get().user, permission)),
    }),
    {
      name: "smartpos-auth",
      // The token never touches localStorage — only `user` (for a flash-free reload);
      // it's re-read from the cookie in setHydrated() once rehydration finishes.
      partialize: (state: AuthState) => ({ user: state.user }),
      // Runs after rehydration finishes, including when nothing was stored.
      // `state` is undefined if rehydration threw, so fall back to the live
      // store — otherwise guarded routes would spin forever.
      onRehydrateStorage: () => (state) =>
        (state ?? useAuth.getState()).setHydrated(),
    },
  ),
);
