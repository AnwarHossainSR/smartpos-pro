import { create } from "zustand";

/** Media slot returned by `GET /settings/business`. */
export interface MediaSlot {
  path: string | null;
  url: string | null;
  urls: Record<string, string> | null;
}

/** Tenant currency, logo and name — refreshed from `/settings/business` by AppLayout.
 *  Not persisted — it's server-owned data, not a client preference, so each session re-fetches. */
interface BusinessState {
  currency: string;
  logoUrl: string | null;
  businessName: string | null;
  setCurrency: (currency: string) => void;
  setLogoUrl: (url: string | null) => void;
  setBusinessName: (name: string | null) => void;
}

export const useBusinessStore = create<BusinessState>((set) => ({
  currency: "USD",
  logoUrl: null,
  businessName: null,
  setCurrency: (currency) => set({ currency }),
  setLogoUrl: (logoUrl) => set({ logoUrl }),
  setBusinessName: (businessName) => set({ businessName }),
}));
