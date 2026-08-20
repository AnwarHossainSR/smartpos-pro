import { execFileSync } from "node:child_process";
import { readFileSync } from "node:fs";
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

function resolveKey(source: unknown, key: string): string | undefined {
  const value = key
    .split(".")
    .reduce<unknown>(
      (acc, part) =>
        acc && typeof acc === "object" ? (acc as Record<string, unknown>)[part] : undefined,
      source,
    );
  return typeof value === "string" ? value : undefined;
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
        if (key.includes(".")) literalDotted.push([...path, key].join(" > "));
        walk(child, [...path, key]);
      }
    };
    walk(CATALOGS[locale], []);

    expect(literalDotted).toEqual([]);
  });

  it.each(LOCALES.filter((locale) => locale !== "en"))(
    "%s interpolates the same placeholders as en",
    (locale) => {
      const placeholders = (text: string) =>
        [...text.matchAll(/\{(\w+)\}/g)].map((match) => match[1]).sort();

      const mismatches: string[] = [];
      for (const key of enKeys) {
        const source = resolveKey(en, key);
        const target = resolveKey(CATALOGS[locale], key);
        if (source === undefined || target === undefined) continue;
        if (placeholders(source).join(",") !== placeholders(target).join(",")) {
          mismatches.push(key);
        }
      }

      expect(mismatches).toEqual([]);
    },
  );
});

/**
 * `t()` falls back to echoing the key when nothing resolves, so a key that exists in no
 * catalog does not throw — it silently renders "pos.checkout" on the checkout button. That
 * has already happened three times here, so it is a test rather than a code-review note.
 */
describe("t() call sites", () => {
  it("only reference keys that exist in en.json", () => {
    const files = execFileSync("git", ["ls-files", "src/**/*.ts", "src/**/*.tsx"], {
      encoding: "utf8",
    })
      .trim()
      .split("\n")
      .filter((file) => file && !file.includes("__tests__"));

    // Static `t("some.key")` lookups, plus the `labelKey: "…"` indirection the POS shortcuts
    // overlay uses. Template-literal keys are dynamic and skipped on purpose.
    const CALL_SITE = /\bt\(\s*"([a-zA-Z][\w.-]*)"/g;
    const LABEL_KEY = /labelKey:\s*"([\w.-]+)"/g;

    const unresolved = new Set<string>();
    for (const file of files) {
      const source = readFileSync(file, "utf8");
      for (const [, key] of [...source.matchAll(CALL_SITE), ...source.matchAll(LABEL_KEY)]) {
        if (resolveKey(en, key) === undefined) unresolved.add(`${key} (${file})`);
      }
    }

    expect([...unresolved].sort()).toEqual([]);
  });
});
