import { useEffect, useMemo } from "react";
import { useLocaleStore } from "@/stores/locale";
import { LOCALE_DIR, type Locale } from "@/i18n/config";
import { I18nContext, type I18nContextValue, type Vars } from "@/i18n/context";
import en from "@/i18n/messages/en.json";
import de from "@/i18n/messages/de.json";
import bn from "@/i18n/messages/bn.json";
import ar from "@/i18n/messages/ar.json";
import ja from "@/i18n/messages/ja.json";

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
