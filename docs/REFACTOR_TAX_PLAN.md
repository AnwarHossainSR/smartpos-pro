# SmartPOS Pro — Refactor, Tax Engine & Frontend Overhaul

Plan of record for the work requested on 2026-07-31: restructure the backend around
reusable primitives and explicit domain patterns, restructure the frontend around feature
modules, add a full jurisdiction-aware tax engine end to end, deepen POS, and raise
performance, SEO and code quality across both codebases.

**Baseline at plan time** — commit `6e44c22`, branch `dev`. `php artisan test`: 146 passed,
543 assertions. Roadmap T1–T104 shipped; T105–T110 backlog.

## Status

| Step | State | Commit | Notes |
| ---- | ----- | ------ | ----- |
| R1 Enums + Money | ✅ | `6fec1ae` | 22 backed enums; `Money` value object; ledger direction derived from movement type |
| R2 Query pipeline | ✅ | `a93df5c` | All ~20 list endpoints; **breaking:** `?direction=` → `?sort=-column` |
| R3 DTOs + pricing split | ✅ | `48017d7` | Money paths typed; `SaleLineBuilder` / `CouponRedeemer` extracted |
| R6 Tax schema | ✅ | `fe2e52f` | 9 tables, back-fill migration, 5 country profiles |
| R7 Tax engine | ✅ | `fe2e52f` | Resolver / calculator / rounder; 16 unit tests |
| R8 Tax in checkout | ✅ | `65e387b` | **Breaking:** `products.tax_rate` no longer read; 9 e2e tests |
| R8b Tax in returns / purchases / quotations | ⬜ | | Returns must reverse snapshotted components, not recompute |
| R9 Tax API + reports | ⬜ | | CRUD for rates/groups/zones; `reports/tax-summary`, `reports/tax-return` |
| R10 Compliant documents | ⬜ | | Invoice/receipt tax breakdown + profile-required fields |
| R11 POS backend depth | ⬜ | | Held sales, void, `pos/bootstrap` |
| R12–R17 Frontend | ⬜ | | Feature folders, POS rewrite, tax UI, performance, SEO |
| R4 Response/error contract | ⬜ | | Deferred behind the tax work |
| R5 Event listeners | ⬜ | | Deferred; do before R8b's journal posting |
| R18–R19 Gates + docs | ⬜ | | |

Test suite: **225 passing** (baseline was 146). `vendor/bin/pint --test` clean.

### Resuming

Next step is **R8b**: `SaleReturnService` still computes tax by pro-rating
`sale_items.tax_amount`. It must instead read the line's `sale_item_taxes` rows
and reverse them proportionally into `sale_return_item_taxes` — a rate change
between sale and return must not change the refund. `PurchaseService` similarly
still takes a client-supplied `tax_amount` per line rather than resolving input
tax through the engine.

## Decisions taken

| Decision | Choice | Consequence |
| -------- | ------ | ----------- |
| Tax depth | **Full jurisdiction engine** (roadmap T96) | Country profiles, tax zones resolved from branch/customer, per-component breakdown, VAT/GST return summaries |
| API compatibility | **Breaking changes allowed in `/v1`** | No `/v2`; there are no external consumers. Tests and frontend are updated in the same step as each break, never deferred |
| Frontend scope | **Full feature-folder migration** | All 19 pages move to `src/features/<module>/`; shared list/query infrastructure replaces per-page duplication |

Because breaking changes are permitted, the 146-test suite is no longer a pure regression
net. Every step therefore carries its own tests, and the suite must be green at the end of
each step — not at the end of the plan.

---

## Stage 1 — Backend foundations

Reusable primitives first, so every later step consumes them instead of re-inventing.

### R1 · Domain enums and money
Replace magic strings with backed enums under `App\Domain\Enums`: `SaleStatus`,
`PaymentStatus`, `PaymentMethod`, `StockDirection`, `StockMovementType`, `AccountType`,
`JournalLineType`, `TaxType`, `TaxRounding`, `DiscountType`, `TransferStatus`,
`QuotationStatus`. Promote `App\Support\Money` from a stub to a real value object
(integer minor units internally, explicit rounding, no float arithmetic in money paths).
Cast enums on models; keep the wire format as the same lowercase strings.

**Done when:** no money- or status-bearing string literal remains in services; `Money` has
unit tests covering rounding and allocation; suite green.

### R2 · Reusable query pipeline
`App\Support\Query\{QueryPipeline, AllowedFilter, AllowedSort, AllowedInclude}`. Each list
endpoint declares its filters/sorts/includes declaratively; the pipeline applies search,
filters, sorting, includes and pagination with the existing `MAX_PAGE_SIZE` clamp and
sort allow-listing. Removes the repeated `->when(...)` chains from ~15 controllers and the
`ValidatesSort` trait.

**Done when:** every `index()` is a declaration plus one pipeline call; unknown filter/sort
422s; existing list tests pass unchanged.

### R3 · DTO layer
`App\Support\Data\Data` base (hand-rolled — no new dependency) plus per-module DTOs
(`CreateSaleData`, `SaleLineData`, `CreatePurchaseData`, …). `FormRequest`s build DTOs;
services take DTOs, never `array $payload`. Kills the large `@param array{...}` docblocks
and makes service inputs statically checkable.

**Done when:** no service method takes an untyped array; PHPStan level rises without new
baseline entries.

### R4 · Response & error contract
`App\Support\Http\ApiResponse` for the success envelope; a single exception renderer
mapping domain exceptions to stable machine-readable `code` values
(`insufficient_stock`, `tenant_suspended`, `coupon_exhausted`, `tax_profile_missing`, …).
`App\Exceptions\Domain\*` hierarchy replaces ad-hoc `ValidationException::withMessages` in
services where the failure is a domain rule rather than input validation.

**Done when:** every 4xx carries a `code`; the frontend switches on `code`, not on message
text.

### R5 · Event-driven cross-cutting concerns
`SaleService` currently also awards loyalty, writes audit rows and (indirectly) drives
accounting. Move these behind `SaleCompleted` / `SaleReturned` / `PurchaseReceived`
listeners: `AwardLoyaltyPoints`, `PostSaleToJournal`, `RecordSaleAudit`,
`CheckLowStockThresholds`. Listeners run inside the same transaction where correctness
demands it (journal posting) and queued where it does not (notifications).

**Done when:** `SaleService::create()` orchestrates pricing, stock and payment only;
each listener has its own test; a failed listener cannot silently swallow a sale.

---

## Stage 2 — Tax engine (backend)

Today tax is two columns on `products` (`tax_rate`, `tax_inclusive`) computed inline in
`SaleService::buildLines()`. There is no tax entity, no per-component breakdown, no
exemption handling, and no tax reporting — a GST invoice cannot show a CGST/SGST split and
a VAT return cannot be produced.

### R6 · Schema and models
- `tax_rates` — name, code, rate, `type` (percentage/fixed), `is_compound`, `is_active`
- `tax_groups` + `tax_group_rate` pivot with `priority` — multi-component tax (CGST+SGST,
  VAT+cess)
- `tax_zones` — a named jurisdiction with a matching rule (country/state/postcode) and its
  default tax group; resolved from the branch (origin) or customer (destination) per the
  profile's sourcing rule
- `tax_profiles` — country profile: sourcing rule, rounding mode and precision, price
  display default, required invoice fields, tax-number label and validation pattern.
  Seeded catalogue for BD (NBR/VAT), IN (GST), EU (VAT), plus a generic profile
- `products.tax_group_id`, `categories.tax_group_id` (fallback), keeping `tax_rate` as a
  read-only legacy mirror during migration
- `sale_item_taxes`, `sale_return_item_taxes`, `purchase_item_taxes` — per-line, per-
  component breakdown (`base_amount`, `rate`, `amount`, `is_compound`)
- `customers.tax_exempt`, `customers.tax_number`; `businesses.tax_profile_id`,
  `businesses.tax_number`, `businesses.prices_include_tax`
- Data migration: every existing product's `tax_rate` becomes a single-rate tax group so
  historical behaviour is preserved exactly

### R7 · `TaxEngine`
`App\Domain\Tax\TaxEngine` with a clean seam:
- **Resolution** — `TaxResolver` picks the effective tax group for a line
  (product → category → zone default), honouring customer exemption and zone rules
- **Calculation** — `TaxCalculator` computes inclusive/exclusive amounts, applies
  components in `priority` order, compounds where flagged, and applies the profile's
  rounding strategy (per line / per tax component / per invoice; half-up or bankers')
- **Result** — an immutable `TaxBreakdown` (per component and total) the sale, return,
  purchase, quotation and invoice all consume

Pure, dependency-free, exhaustively unit tested: inclusive vs exclusive, compound stacking,
line discounts, zero-rate, exempt, fixed-amount tax, and rounding-mode edge cases where
naive per-line rounding drifts from the invoice total.

### R8 · Wire into the money paths
`SaleService`, `SaleReturnService`, `PurchaseService`, `QuotationService` all call the
engine instead of computing tax inline. Returns reverse the exact snapshotted components,
not a recomputed rate. `PostSaleToJournal` (R5) posts output tax to a tax-payable account
and purchase input tax to tax-receivable, so the tax return reconciles to the ledger.

### R9 · Tax API
New `taxes` permission module. CRUD for `tax-rates`, `tax-groups`, `tax-zones`; read-only
`tax-profiles` catalogue; `GET /v1/reports/tax-summary` (output vs input tax by component
and period) and `GET /v1/reports/tax-return` (profile-shaped VAT/GST return).

### R10 · Compliant documents
Invoice PDF gains the tax breakdown table, both parties' tax numbers, and the profile's
required fields. Receipt shows the per-component split.

---

## Stage 3 — POS depth

### R11 · Backend
Held/parked sales (`POST /v1/sales/hold`, resume, list), sale void with stock reversal and
audit (roadmap T106), and `GET /v1/pos/bootstrap` — one request returning register,
warehouse, tax profile, payment methods and the active session, replacing the three
round-trips the terminal makes today.

---

## Stage 4 — Frontend

### R12 · Feature architecture
`src/features/<module>/{api,hooks,components,types}`. Shared infrastructure:
`lib/query-keys.ts` (typed key factory), zod-validated API responses, and a
`useResourceList` hook that owns search + filter + sort + pagination + URL-state sync —
the pattern currently copy-pasted across 19 pages.

### R13 · Page migration
Every page moves onto the feature modules and the shared hook, splitting the oversized
ones (`pos` 515 lines, `products` 372, `cash-register` 293) into composed components.

### R14 · POS terminal rewrite
Keyboard-first operation (F-key shortcuts, scanner focus trap), barcode scan via the
existing `products/barcode/{barcode}` endpoint, held carts, split tender (the backend has
always accepted multiple payments; the UI sends one), coupon entry, live per-component tax
breakdown, and inline customer create.

### R15 · Tax UI
Settings → Tax: profile selection, rates, groups, zones, tax numbers, price-display mode.
Reports → Tax: summary and return views.

### R16 · Performance
Dynamic import for `recharts` (the heaviest dependency, currently in the main graph),
`optimizePackageImports` for `lucide-react`, route segment config, list virtualization on
the POS product grid, memoized cart maths, prefetch on nav intent, and a bundle budget
check in CI.

### R17 · SEO
Per-route `metadata` and `generateMetadata`, `sitemap.ts`, `robots.ts`, canonical URLs,
`Organization` + `SoftwareApplication` JSON-LD on the landing page, OG image route, and
`noindex` on every authenticated route.

---

## Stage 5 — Quality gates

### R18 · Tests
Backend feature tests for every new endpoint and unit tests for the tax engine; frontend
vitest for the shared hooks and tax maths; Playwright covering the full POS checkout with
tax, a return, and the tax report.

### R19 · Docs and cleanup
`docs/API.md`, `docs/ARCHITECTURE.md` and `docs/PROGRESS.md` updated; dead code removed
(unused permissions with no route, unreferenced components); PHPStan raised a level with a
curated baseline.

---

## Order of execution

```
R1 → R2 → R3 → R4 → R5      Stage 1  foundations
R6 → R7 → R8 → R9 → R10     Stage 2  tax
R11                          Stage 3  POS backend
R12 → R13 → R14 → R15        Stage 4  frontend structure + features
R16 → R17                    Stage 4  performance + SEO
R18 → R19                    Stage 5  gates
```

Each step is one focused commit referencing its `R` id. `php artisan test`,
`vendor/bin/pint --test`, `npm run lint` and `npm run build` must all be green before the
next step begins.
