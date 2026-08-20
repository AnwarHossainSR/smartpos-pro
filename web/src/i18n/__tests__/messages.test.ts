import { describe, expect, it } from "vitest";
import { LOCALES, type Locale } from "../config";
import ar from "../messages/ar.json";
import bn from "../messages/bn.json";
import de from "../messages/de.json";
import en from "../messages/en.json";
import ja from "../messages/ja.json";

const CATALOGS: Record<Locale, unknown> = { en, de, bn, ar, ja };

function flatten(value: unknown, prefix = ""): string[] {
  if (!value || typeof value !== "object" || Array.isArray(value)) return [prefix];
  return Object.entries(value as Record<string, unknown>).flatMap(([key, child]) =>
    flatten(child, prefix ? `${prefix}.${key}` : key),
  );
}

const enKeys = flatten(en).sort();

describe("i18n catalogs", () => {
  it.each(LOCALES.filter((locale) => locale !== "en"))(
    "%s has exactly the same keys as en",
    (locale) => {
      const keys = flatten(CATALOGS[locale]).sort();
      const missing = enKeys.filter((key) => !keys.includes(key));
      const extra = keys.filter((key) => !enKeys.includes(key));

      expect({ missing, extra }).toEqual({ missing: [], extra: [] });
    },
  );

  it.each(LOCALES)("%s has no key containing a literal dot", (locale) => {
    // `t()` splits lookups on ".", so a key like "settings.printer" stored as one literal
    // property is unreachable — every t("settings.printer.x") call renders the raw key.
    const literalDotted: string[] = [];
    const walk = (value: unknown, path: string[]) => {
      if (!value || typeof value !== "object" || Array.isArray(value)) return;
      for (const [key, child] of Object.entries(value as Record<string, unknown>)) {
        if (key.includes(".")) literalDotted.push([...path, key].join(" › "));
        walk(child, [...path, key]);
      }
    };
    walk(CATALOGS[locale], []);

    expect(literalDotted).toEqual([]);
  });

  it.each(LOCALES.filter((locale) => locale !== "en"))(
    "%s interpolates the same placeholders as en",
    (locale) => {
      const catalog = CATALOGS[locale] as Record<string, unknown>;
      const read = (source: unknown, key: string): string | undefined => {
        const value = key
          .split(".")
          .reduce<unknown>(
            (acc, part) =>
              acc && typeof acc === "object" ? (acc as Record<string, unknown>)[part] : undefined,
            source,
          );
        return typeof value === "string" ? value : undefined;
      };
      const placeholders = (text: string) =>
        [...text.matchAll(/\{(\w+)\}/g)].map((m) => m[1]).sort();

      const mismatches: string[] = [];
      for (const key of enKeys) {
        const source = read(en, key);
        const target = read(catalog, key);
        if (source === undefined || target === undefined) continue;
        if (placeholders(source).join(",") !== placeholders(target).join(",")) {
          mismatches.push(key);
        }
      }

      expect(mismatches).toEqual([]);
    },
  );
});
