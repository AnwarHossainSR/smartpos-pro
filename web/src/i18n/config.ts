export const LOCALES = ["en", "de", "bn", "ar", "ja"] as const;
export type Locale = (typeof LOCALES)[number];

export const DEFAULT_LOCALE: Locale = "en";

export const LOCALE_DIR: Record<Locale, "ltr" | "rtl"> = {
  en: "ltr",
  de: "ltr",
  bn: "ltr",
  ar: "rtl",
  ja: "ltr",
};

export const LOCALE_LABELS: Record<Locale, string> = {
  en: "English",
  de: "Deutsch",
  bn: "বাংলা",
  ar: "العربية",
  ja: "日本語",
};

/** Sensible default when a tenant first picks this UI language — the tenant's own currency
 *  setting (Settings -> Localization) always takes precedence once set. */
export const LOCALE_CURRENCY: Record<Locale, string> = {
  en: "USD",
  de: "EUR",
  bn: "BDT",
  ar: "SAR",
  ja: "JPY",
};

export function isLocale(value: string): value is Locale {
  return (LOCALES as readonly string[]).includes(value);
}
