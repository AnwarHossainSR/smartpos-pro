# SmartPOS Pro — Design System Refresh + i18n & Currency Plan

_Epic owner: frontend. Status: planning. Created 2026-07-29._

Reference inspiration: [DreamsPOS retail-pos](https://dreamspos.dreamstechnologies.com/retail-pos/html/product-list.html)
(dark navy sidebar + warm accent, dense data tables, clear card surfaces). We are **not**
cloning it — we take the *feel* (clean surfaces, confident accent, data-dense tables) and
build a **themeable, multi-language, multi-currency** design system on top of our existing
Tailwind v4 token setup.

## Goals

1. **Swappable color palettes** — ship 5–6 curated themes; user/tenant switches live with no
   reload. Every palette works in light **and** dark mode and passes WCAG AA on text.
2. **Multi-language UI** — German, English, Bangla, Arabic, Japanese. Full **RTL** for Arabic.
3. **Currency** — independent tenant setting (BDT/USD/EUR/JPY/SAR/…), locale-aware number &
   date formatting, decoupled from UI language.
4. **No regressions** — `npm run lint` clean, `npm run build` green, existing pages keep their
   loading/empty/error states.

## Decisions (locked)

| Topic | Decision |
| ----- | -------- |
| Palettes | 5–6 presets + runtime switcher (not a single fixed theme) |
| Languages | `de`, `en`, `bn`, `ar` (RTL), `ja`. English = fallback |
| Currency | Tenant/business setting, **independent** of language |
| Locale routing | **No `/[locale]` URL segment** — locale is a user/tenant preference stored in settings + persisted client-side. Keeps the authed dashboard router simple. |
| i18n library | Verify against `web/node_modules/next/dist/docs/` first (Next 16 has breaking changes per `web/AGENTS.md`). Default choice: lightweight message catalog + React context/provider; adopt `next-intl` only if docs show it's the sanctioned path. |

---

## 1 · Design system / theming

### Token architecture
Today `globals.css` hardcodes one indigo palette across `@theme`, `:root`, and `.dark`. We
restructure into **semantic tokens** driven by a `data-theme` attribute so palettes are pure CSS:

```
html[data-theme="sunset"]        → light values
html[data-theme="sunset"].dark   → dark values
```

Semantic token set (names stay stable; only values change per palette):
`--bg, --surface, --surface-2, --surface-3, --border, --fg, --muted,
--primary, --primary-hover, --primary-fg, --accent, --sidebar-bg, --sidebar-fg,
--sidebar-active, --success, --warning, --danger, --ring, --radius`.

Note the **new** sidebar tokens — the DreamsPOS look comes from a dark sidebar independent of
page background, which our current single-surface sidebar can't express.

### Proposed palettes (final hex tuned during T65)
| # | Name | Primary | Sidebar | Vibe |
| - | ---- | ------- | ------- | ---- |
| 1 | **Amber Retail** | `#FF9F43` amber | navy `#212B36` | DreamsPOS-inspired default |
| 2 | **Indigo** | `#4F46E5` | slate `#1E293B` | our current look, cleaned up |
| 3 | **Emerald** | `#10B981` | forest `#14251F` | fresh / grocery |
| 4 | **Ocean** | `#0EA5E9` | deep blue `#0B2A45` | calm / pharmacy |
| 5 | **Violet** | `#7C3AED` | plum `#241B3A` | premium / boutique |
| 6 | **Rose** | `#F43F5E` | charcoal `#241B22` | fashion / cafe |

Each ships light + dark. A11y: run every `--fg`/`--primary-fg` pair through a contrast check
during T65; adjust lightness, keep the hue.

### Switcher
- `useTheme()` store (extend existing `web/src/stores/theme.ts`) gains `palette` alongside the
  existing light/dark mode. Persist to `localStorage`; hydrate before paint to avoid flash.
- UI: a palette picker (swatch grid) in **Settings → Appearance** and a quick toggle in the
  header. Applies `data-theme` on `<html>`.

### Component polish (match the reference's density/clarity)
- Sidebar → dark surface using `--sidebar-*` tokens, active item = accent.
- Tables (`ui/table.tsx`) → sticky header, zebra rows, hover, right-aligned numeric cols,
  row actions (view/edit/delete), bulk-select checkbox column.
- List page pattern → toolbar with search + dropdown filters + Import/Export (PDF/Excel) +
  "Add" button, consistent across products/inventory/customers/etc.
- Cards, buttons, badges, inputs → re-map to new tokens; confirm focus `--ring` visible.

---

## 2 · Internationalization (i18n)

### Structure
```
web/src/i18n/
  config.ts            # locales, dir map, default, currency defaults
  provider.tsx         # <I18nProvider> — context + t() + formatters
  messages/
    en.json  de.json  bn.json  ar.json  ja.json
```
- **Namespaced keys**: `common.save`, `nav.products`, `products.table.sku`, … English authored
  first as the source of truth; other locales mirror the same keys (untranslated → fall back to
  `en`).
- `t(key, vars?)` with simple `{var}` interpolation + plural helper.
- Locale persisted in the auth/user store and mirrored to `localStorage`; SSR reads a cookie so
  first paint uses the right `lang`/`dir`.

### RTL (Arabic)
- `<html lang={locale} dir={dir}>` set from a `LOCALE_DIR` map (`ar → rtl`, else `ltr`).
- Audit hardcoded directional classes → replace `ml-*/mr-*/left/right/text-left` with logical
  utilities (`ms-*/me-*/start/end/text-start`) so layouts mirror automatically.
- Icons that imply direction (chevrons, arrows) flip under RTL.

### Fonts (per-script coverage)
- Latin (en/de): keep **Geist**.
- Bangla: **Noto Sans Bengali**. Japanese: **Noto Sans JP**. Arabic: **Noto Sans Arabic / IBM Plex Arabic**.
- Load via `next/font/google` with `subsets`, expose as `--font-sans` overrides scoped by
  `:lang()` or `data-lang`, so each script renders in a font that actually covers its glyphs.

### Coverage
- Translate: nav, header, buttons, form labels, validation messages, toasts, empty/error states,
  table headers, settings. Backend enum/label strings that surface in UI → map client-side.
- ESLint guard (nice-to-have): flag bare string literals in JSX to catch missed translations.

---

## 3 · Currency & locale formatting

- **Tenant setting**: `currency` (ISO 4217) + `locale` for formatting live in business settings
  (backend `settings` + a `GET /v1/settings` field; already have a settings page). Add a
  currency picker in **Settings → Localization**.
- Replace `formatCurrency(value, currency="USD")` hardcoded `en-US` with a locale-aware version:
  `new Intl.NumberFormat(activeLocale, { style:"currency", currency: tenantCurrency })`.
- `formatNumber`, `formatDate`, `formatDateTime` all take the active locale.
- POS, receipts, reports, dashboards use these helpers everywhere (audit for raw `$`/`toFixed`).
- **Backend**: money stays stored as integer minor units (no change); currency code is display
  metadata. Confirm no hardcoded `$`/`USD` in receipt/report generation; add `currency` to
  business settings migration if missing.

---

## 4 · Task breakdown (new Phase 5)

Add these to `REQUIREMENTS.md` / `PROGRESS.md` when we start. Ordered for step-by-step delivery;
each is independently shippable and testable.

| ID | Task | Deps | DoD |
| -- | ---- | ---- | --- |
| **T65** | Token architecture + finalize 6 palettes (light+dark), a11y-checked, in `globals.css` via `data-theme` | — | Palettes render; contrast AA; no visual break on existing pages |
| **T66** | Theme store + palette switcher UI (Settings→Appearance + header), no-flash hydration | T65 | Switch persists across reload; SSR no flash |
| **T67** | Sidebar + shell restyle to dark `--sidebar-*` tokens | T65 | Matches new look; keyboard/active states intact |
| **T68** | Table + list-page pattern upgrade (zebra, sticky, bulk-select, row actions, toolbar filters, import/export buttons) | T65 | Reusable `DataTable`; applied to Products first |
| **T69** | i18n foundation: `i18n/` provider, `t()`, `en.json`, `<html lang/dir>`, locale store + cookie | — | English routes through `t()`; switching locale re-renders |
| **T70** | Locale switcher UI + per-script fonts (Bangla/JP/Arabic) | T69 | Font per language correct; picker in header + settings |
| **T71** | RTL support: logical-property audit + Arabic mirroring + icon flips | T69 | `ar` mirrors correctly on all app pages |
| **T72** | Translate catalogs: `de.json`, `bn.json`, `ar.json`, `ja.json` for all shipped UI strings | T69 | No missing-key fallbacks in shipped screens |
| **T73** | Currency/locale settings: tenant currency+locale, locale-aware `format*` helpers, audit raw money rendering (POS/receipts/reports) | T69 | All money uses helper; tenant currency respected end-to-end |
| **T74** | QA pass: lint/build green, e2e smoke per locale (incl. RTL + dark), screenshots, docs update | T65–T73 | `npm run lint`+`build` clean; PROGRESS/API docs updated |

**Suggested delivery order:** T65 → T66 → T67 → T68 (design track), then T69 → T70 → T71 →
T72 → T73 (i18n track), then T74. Design and i18n tracks can run in parallel after T65/T69.

## 5 · Risks / open items
- **Next 16 i18n API**: confirm the sanctioned pattern in `web/node_modules/next/dist/docs/`
  before writing T69 — may change provider vs. middleware choice.
- **Translation quality**: initial non-English catalogs may need native-speaker review;
  English fallback prevents breakage meanwhile.
- **Bundle size**: 3 extra Noto font subsets — load only the active language's font.
- **Backend currency**: verify business settings already store a currency; small migration if not.
