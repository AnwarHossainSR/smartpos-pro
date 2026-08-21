import { createContext, useContext } from "react";
import type { Locale } from "@/i18n/config";

export type Vars = Record<string, string | number>;

export interface I18nContextValue {
  locale: Locale;
  dir: "ltr" | "rtl";
  /** Looks up a namespaced key (e.g. "products.title"); falls back to English, then the key itself. */
  t: (key: string, vars?: Vars) => string;
  /** Picks `${key}.one` / `${key}.other` by count, then interpolates {count} and any extra vars. */
  plural: (count: number, key: string, vars?: Vars) => string;
}

/**
 * The context object lives in this component-free module on purpose. When it sat in
 * `provider.tsx` next to the `<I18nProvider>` component, React Fast Refresh could not
 * accept that file (a module exporting both components and non-components bails out), so
 * an HMR update re-executed it and minted a *new* context object — while the mounted
 * provider still held the old one. Every consumer then read `null` and threw
 * "useI18n must be used within <I18nProvider>" on a perfectly correct tree.
 */
export const I18nContext = createContext<I18nContextValue | null>(null);

export function useI18n(): I18nContextValue {
  const ctx = useContext(I18nContext);
  if (!ctx) throw new Error("useI18n must be used within <I18nProvider>");
  return ctx;
}

export function useTranslation() {
  return useI18n().t;
}
