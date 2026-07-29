"use client";

import { create } from "zustand";

/** Tenant currency, refreshed from `/settings/business` by AppLayout. Not persisted — it's
 *  server-owned data, not a client preference, so each session re-fetches it. */
interface BusinessState {
  currency: string;
  setCurrency: (currency: string) => void;
}

export const useBusinessStore = create<BusinessState>((set) => ({
  currency: "USD",
  setCurrency: (currency) => set({ currency }),
}));
