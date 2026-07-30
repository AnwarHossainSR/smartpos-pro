"use client";

import { create } from "zustand";
import { persist } from "zustand/middleware";

export interface AuthUser {
  id: number;
  name: string;
  email: string;
  email_verified_at?: string | null;
  roles?: string[];
  permissions?: string[];
  business_id?: number | null;
  branch_id?: number | null;
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
  can: (permission: string) => boolean;
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
      can: (permission) => {
        const user = get().user;
        if (!user) return false;
        if (user.roles?.includes("owner")) return true;
        return user.permissions?.includes(permission) ?? false;
      },
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
