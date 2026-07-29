"use client";

import { create } from "zustand";
import { persist } from "zustand/middleware";

export interface AuthUser {
  id: number;
  name: string;
  email: string;
  roles?: string[];
  permissions?: string[];
  business_id?: number | null;
  branch_id?: number | null;
}

interface AuthState {
  token: string | null;
  user: AuthUser | null;
  /** False until persisted state has been read back from localStorage. */
  hydrated: boolean;
  setAuth: (token: string, user: AuthUser) => void;
  setUser: (user: AuthUser) => void;
  setHydrated: () => void;
  logout: () => void;
  can: (permission: string) => boolean;
}

export const useAuth = create<AuthState>()(
  persist(
    (set, get): AuthState => ({
      token: null,
      user: null,
      hydrated: false,
      setAuth: (token, user) => set({ token, user }),
      setUser: (user) => set({ user }),
      setHydrated: () => set({ hydrated: true }),
      logout: () => set({ token: null, user: null }),
      can: (permission) => {
        const user = get().user;
        if (!user) return false;
        if (user.roles?.includes("owner")) return true;
        return user.permissions?.includes(permission) ?? false;
      },
    }),
    {
      name: "smartpos-auth",
      // Only credentials are persisted; `hydrated` is runtime-only.
      partialize: (state: AuthState) => ({ token: state.token, user: state.user }),
      // Runs after rehydration finishes, including when nothing was stored.
      // `state` is undefined if rehydration threw, so fall back to the live
      // store — otherwise guarded routes would spin forever.
      onRehydrateStorage: () => (state) =>
        (state ?? useAuth.getState()).setHydrated(),
    },
  ),
);
