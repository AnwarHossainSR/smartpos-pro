import { createContext, useContext, useEffect, useMemo } from "react";
import { useLocaleStore } from "@/stores/locale";
import { LOCALE_DIR, type Locale } from "@/i18n/config";
import en from "@/i18n/messages/en.json";
import de from "@/i18n/messages/de.json";
import bn from "@/i18n/messages/bn.json";
import ar from "@/i18n/messages/ar.json";
import ja from "@/i18n/messages/ja.json";

type Vars = Record<string, string | number>;

const CATALOGS: Record<Locale, unknown> = { en, de, bn, ar, ja };

function resolve(messages: unknown, key: string): string | undefined {
  const value = key.split(".").reduce<unknown>((acc, part) => {
    if (acc && typeof acc === "object" && part in (acc as object)) {
      return (acc as Record<string, unknown>)[part];
    }
    return undefined;
  }, messages);
  return typeof value === "string" ? value : undefined;
}

function interpolate(template: string, vars?: Vars): string {
  if (!vars) return template;
  return template.replace(/\{(\w+)\}/g, (match, name: string) =>
    vars[name] !== undefined ? String(vars[name]) : match,
  );
}

interface I18nContextValue {
  locale: Locale;
  dir: "ltr" | "rtl";
  /** Looks up a namespaced key (e.g. "products.title"); falls back to English, then the key itself. */
  t: (key: string, vars?: Vars) => string;
  /** Picks `${key}.one` / `${key}.other` by count, then interpolates {count} and any extra vars. */
  plural: (count: number, key: string, vars?: Vars) => string;
}

const I18nContext = createContext<I18nContextValue | null>(null);

export function I18nProvider({ children }: { children: React.ReactNode }) {
  const locale = useLocaleStore((s) => s.locale);
  const dir = LOCALE_DIR[locale];

  useEffect(() => {
    const root = document.documentElement;
    root.setAttribute("lang", locale);
    root.setAttribute("dir", dir);
    root.setAttribute("data-lang", locale);
  }, [locale, dir]);

  const value = useMemo<I18nContextValue>(() => {
    const messages = CATALOGS[locale] ?? en;

    function t(key: string, vars?: Vars): string {
      const raw = resolve(messages, key) ?? resolve(en, key) ?? key;
      return interpolate(raw, vars);
    }

    function plural(count: number, key: string, vars?: Vars): string {
      const form = `${key}.${count === 1 ? "one" : "other"}`;
      return t(form, { count, ...vars });
    }

    return { locale, dir, t, plural };
  }, [locale, dir]);

  return <I18nContext.Provider value={value}>{children}</I18nContext.Provider>;
}

export function useI18n(): I18nContextValue {
  const ctx = useContext(I18nContext);
  if (!ctx) throw new Error("useI18n must be used within <I18nProvider>");
  return ctx;
}

export function useTranslation() {
  return useI18n().t;
}
