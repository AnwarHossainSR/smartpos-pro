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
  setAuth: (token: string, user: AuthUser) => void;
  setUser: (user: AuthUser) => void;
  logout: () => void;
  can: (permission: string) => boolean;
}

export const useAuth = create<AuthState>()(
  persist(
    (set, get) => ({
      token: null,
      user: null,
      setAuth: (token, user) => set({ token, user }),
      setUser: (user) => set({ user }),
      logout: () => set({ token: null, user: null }),
      can: (permission) => {
        const user = get().user;
        if (!user) return false;
        if (user.roles?.includes("owner")) return true;
        return user.permissions?.includes(permission) ?? false;
      },
    }),
    { name: "smartpos-auth" },
  ),
);
