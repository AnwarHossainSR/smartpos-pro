# SmartPOS Pro — Progress Tracker

Single source of truth for **what's done vs. not**. Task IDs map to
[`REQUIREMENTS.md`](REQUIREMENTS.md). The `/implement-task <id>` skill updates this file
when it finishes a task.

**Status legend:** ✅ done · 🚧 in progress · ⬜ todo

_Last updated: 2026-08-01_

## Summary

| Phase | Done | In progress | Todo | Total |
| ----- | ---- | ----------- | ---- | ----- |
| 1 · Backend foundation & modules | 27 | 0 | 0 | 27 |
| 2 · Backend, remaining | 10 | 0 | 0 | 10 |
| 3 · Frontend | 23 | 0 | 0 | 23 |
| 4 · Quality & delivery | 4 | 0 | 0 | 4 |
| 5 · Design system refresh + i18n & currency | 10 | 0 | 0 | 10 |
| 6 · Printable documents | 1 | 0 | 0 | 1 |
| 7 · Security & money integrity | 13 | 0 | 0 | 13 |
| 8 · Product depth | 1 | 0 | 10 | 11 |
| 9 · Gap fixes + missing endpoints | 5 | 0 | 0 | 5 |
| 10 · Further gaps identified, not yet built | 1 | 0 | 5 | 6 |
| 11 · Audit pass (correctness, load safety, contract drift) | 6 | 0 | 2 | 8 |
| **Total** | **101** | **0** | **17** | **118** |

## 2026-08-01 — backend refactor, tax engine, and frontend overhaul

A separate, larger initiative than the task-by-task work above: reusable domain
primitives (enums, `Money`, a declarative query pipeline, typed DTOs), a full
jurisdiction-aware tax engine wired through checkout/returns/purchases/quotations
with an admin API and reports, POS backend depth (held/resume/void, `pos/bootstrap`),
and a first pass at the frontend (shared list architecture, tax admin + report UI,
POS tax display, SEO baseline). Plan, decisions and per-step status:
[`REFACTOR_TAX_PLAN.md`](REFACTOR_TAX_PLAN.md). `php artisan test`: 239 passing
(was 146 at the start). Frontend feature-folder migration beyond tax/POS, list
virtualization, and per-auth-page metadata remain — see that doc's status table.

## Phase 1 — Backend foundation & modules

| ID | Task | Status | PR | Notes |
| -- | ---- | ------ | -- | ----- |
| T1 | Project scaffold & tooling | ✅ | #1 | Laravel 13, Docker, CI, Pint, PHPStan |
| T2 | Multi-tenant foundation | ✅ | #1 | scope + trait + middleware; isolation test |
| T3 | Core DB schema & migrations | ✅ | #1 | 29 migrations total across PRs |
| T4 | Auth (Sanctum) + registration | ✅ | #1 | TenantProvisioner |
| T5 | RBAC (roles & permissions) | ✅ | #1 | PermissionCatalog + `permission:` mw |
| T6 | Categories CRUD | ✅ | #1 | |
| T7 | Brands CRUD | ✅ | #2 | |
| T8 | Units CRUD | ✅ | #2 | |
| T9 | Products CRUD (+ opening stock) | ✅ | #1 | |
| T10 | Inventory ledger & adjustments | ✅ | #1 | StockLedgerService, row locks |
| T11 | Customers CRUD | ✅ | #1 | |
| T12 | Suppliers CRUD | ✅ | #2 | |
| T13 | POS checkout + idempotency | ✅ | #1 | SaleService |
| T14 | Sales listing & detail | ✅ | #1 | |
| T15 | Sale returns & refunds | ✅ | #2 | SaleReturnService |
| T16 | Purchases + goods receipt | ✅ | #2 | PurchaseService |
| T17 | Expenses + categories | ✅ | #2 | |
| T18 | Reports center | ✅ | #2 | 6 report endpoints |
| T19 | Accounting (double-entry journals) | ✅ | #3 | JournalService |
| T20 | Cash register sessions | ✅ | #3 | reconciliation |
| T21 | User management | ✅ | #4 | |
| T22 | Roles & permissions management | ✅ | #4 | |
| T23 | Notifications API | ✅ | #4 | |
| T24 | Business settings + onboarding | ✅ | #4 | |
| T25 | Loyalty points | ✅ | #5 | earn/redeem, earn-on-sale hook |
| T26 | Coupons / promotions | ✅ | #5 | validate endpoint |
| T27 | Subscription plans & limits | ✅ | #5 | PlanLimitService |

## Phase 2 — Backend, remaining

| ID | Task | Status | PR | Notes |
| -- | ---- | ------ | -- | ----- |
| T28 | Stock transfers | ✅ | #7 | StockTransferService: request/approve/receive/cancel; 2 ledger movements |
| T29 | Variants/batches/serials/expiry endpoints | ✅ | #7 | product_variants, stock_batches, stock_serials tables + controller |
| T30 | Quotations, drafts & held-sale conversion | ✅ | #7 | QuotationService: create, update status, convert to sale |
| T31 | Super-admin: tenant management | ✅ | #7 | SuperAdminController: list/show/suspend/activate/delete tenants |
| T32 | Super-admin: billing & revenue metrics | ✅ | #7 | /super/metrics/revenue: MRR, plan distribution, new tenants |
| T33 | Super-admin: impersonation | ✅ | #7 | /super/tenants/{id}/impersonate → scoped 24h token |
| T34 | Real-time broadcasting (Reverb) | ✅ | #7 | SaleCompleted + InventoryLow events, private channels, Echo docs |
| T35 | Notification channels (email/SMS/WhatsApp) | ✅ | #7 | DailyReportMail + queued SendDailyReportJob; mailable markdown template |
| T36 | Scheduled & exportable reports | ✅ | #7 | scheduler: daily report emails + trial expiry; console.php schedules |
| T37 | OpenAPI docs + Postman | ✅ | #7 | docs/API.md extended with T28-T36 endpoints, auth flows, Echo setup |

## Phase 3 — Frontend

| ID | Task | Status | PR | Notes |
| -- | ---- | ------ | -- | ----- |
| T38 | FE scaffold: design tokens + UI primitives | ✅ | #6 | Next 16, Tailwind v4 |
| T39 | FE: API client, auth store, query provider | ✅ | #6 | |
| T40 | FE: Auth pages (login/register) | ✅ | #6 | wired to API |
| T41 | FE: App shell (sidebar/header/guard) | ✅ | #6 | |
| T42 | FE: Dashboard (KPIs + chart) | ✅ | #6 | Recharts |
| T43 | FE: Products list + create/edit | ✅ | #6 | RHF+Zod, opening stock |
| T44 | FE: POS terminal | ✅ | #6 | cart, payment modal, receipt, idempotency |
| T45 | FE: Inventory overview | ✅ | #6 | balance table, adjust drawer, low-stock filter |
| T46 | FE: Sales list + detail + returns | ✅ | #6 | SaleDetailModal, ReturnModal |
| T47 | FE: Customers UI | ✅ | #6 | CRUD with group/credit limit |
| T48 | FE: Suppliers UI | ✅ | #6 | CRUD with company/tax/opening balance |
| T49 | FE: Purchases UI | ✅ | #6 | useFieldArray for items + payments |
| T50 | FE: Expenses UI | ✅ | #6 | category, amount, date, payment method |
| T51 | FE: Reports UI | ✅ | #6 | 6 report types, Recharts charts |
| T52 | FE: Accounting UI | ✅ | #6 | accounts/journals/trial-balance tabs |
| T53 | FE: Cash register UI | ✅ | #6 | open/close/movements flow |
| T54 | FE: Team & roles UI | ✅ | #6 | invite/edit drawer, role toggle chips |
| T55 | FE: Settings & onboarding UI | ✅ | #6 | business settings + account card |
| T56 | FE: Loyalty & coupons UI | ✅ | #6 | coupon list/create, loyalty points tab |
| T57 | FE: Subscription/billing page | ✅ | #6 | plan card + usage progress bars |
| T58 | FE: Notifications + command palette | ✅ | #6 | bell icon, ⌘K palette with keyboard nav |
| T59 | FE: Theme toggle & responsive polish | ✅ | #6 | light/dark/system toggle, mobile sidebar |
| T60 | FE: PWA + offline POS | ✅ | #6 | manifest, SW, IDB sync, offline banner |

## Phase 4 — Quality & delivery

| ID | Task | Status | PR | Notes |
| -- | ---- | ------ | -- | ----- |
| T61 | E2E tests (Playwright): POS checkout | ✅ | #6 | playwright.config.ts + e2e/pos-checkout.spec.ts |
| T62 | Frontend component/unit tests | ✅ | #6 | Vitest + Testing Library; 26 tests across utils/stores/components |
| T63 | Richer demo seed data | ✅ | #6 | RichDemoSeeder: branch2, returns, journals, coupons, loyalty |
| T64 | Deployment guides & infra hardening | ✅ | #6 | docs/DEPLOYMENT.md: nginx, PHP-FPM, PM2, supervisor, security headers |

## Phase 5 — Design system refresh + i18n & currency

Plan: [`REDESIGN_I18N_PLAN.md`](REDESIGN_I18N_PLAN.md).

| ID | Task | Status | PR | Notes |
| -- | ---- | ------ | -- | ----- |
| T65 | Token architecture + 6 palettes (light+dark) | ✅ | | Semantic tokens in globals.css, data-theme driven, AA-checked |
| T66 | Theme store + palette switcher UI | ✅ | | palette in theme store; header swatch + Settings→Appearance grid; no-flash inline script in layout head |
| T67 | Sidebar + shell restyle | ✅ | | sidebar on --sidebar-* tokens; fixed 2 latent primary-fg contrast bugs (logo badges) + danger button AA |
| T68 | Table + list-page pattern upgrade | ✅ | | DataTable (zebra/sticky/bulk-select) + ListToolbar (search/export/add), applied to Products; CSV export is real, import deferred |
| T69 | i18n foundation | ✅ | | I18nProvider + t()/plural(), en.json source of truth; wired into shell, auth pages, settings, Products (flagship); other module pages still hardcoded English — tracked as follow-up |
| T70 | Locale switcher UI + per-script fonts | ✅ | | LocalePicker in header + Settings→Localization; Noto Sans Bengali/JP/Arabic via next/font, applied per [data-lang] |
| T71 | RTL support | ✅ | | dir=rtl driven by locale (I18nProvider); 106 physical→logical class swaps (ml/pl/left/right/text-left/right) across 20 files; pagination chevrons flip via rtl:scale-x-[-1] |
| T72 | Translation catalogs (de/bn/ar/ja) | ✅ | | Full human translations, 121/121 keys parity with en.json verified by script |
| T73 | Currency/locale-aware formatting | ✅ | | formatCurrency/Number/Date/DateTime read locale+tenant-currency stores; business store added; audited/fixed 3 raw `$` spots + 8 raw toLocaleDateString/String spots app-wide |
| T74 | QA pass | ✅ | | lint 0 errors (fixed 3 pre-existing react-hooks/set-state-in-effect issues), build clean, 32/32 unit tests, Playwright visual smoke test across all 6 palettes × light/dark × en/ar/bn/ja on login/register/landing |

## Phase 6 — Printable documents

| ID | Task | Status | PR | Notes |
| -- | ---- | ------ | -- | ----- |
| T75 | Invoice & cash-register payment-slip PDFs | ✅ | | barryvdh/laravel-dompdf; `pdf.invoice`/`pdf.cash-slip` Blade templates; `GET sales/{sale}/invoice`, `GET cash-register-sessions/{session}/slip` (`?download=1`); wired into POS receipt, sales list, cash-register page. Also fixed the cash-register frontend, which was calling endpoints/fields that didn't match the backend at all (`opening_balance` vs `opening_cash`, wrong close/movement URLs) — added the missing `GET cash-registers/{id}/current-session` endpoint the frontend already expected. Verified end-to-end with Playwright against the running app (8 new/updated backend feature tests, all green). |

## Phase 7 — Security & money integrity

Plan: [`NEXT_PHASE_PLAN.md`](NEXT_PHASE_PLAN.md) — each task lists the review finding it
closes. Suggested order: T76 → T77 → T78 → T83 → T81 → T82 → T86 → T80 → T79 → T84 → T85 →
T87 → T88.

| ID | Task | Status | PR | Notes |
| -- | ---- | ------ | -- | ----- |
| T76 | Server-side pricing + permission-gated overrides | ✅ | | `SaleService` prices every line from the catalog; client `unit_price`/`discount_amount` only honoured with `pos.price_override`/`pos.discount` (403 otherwise); sale-level discount capped at subtotal (422); honoured overrides write an `audit_logs` row via new `AuditLogService`; POS cart shows price/discount inputs only when permitted |
| T77 | Coupon enforcement inside the sale transaction | ✅ | | `SaleService` re-validates `coupon_code` inside the checkout transaction (active/date/min-spend/limit), row-locks the coupon and increments `used_count`, derives `coupon_discount` server-side; new `coupon_id`/`coupon_discount` columns on `sales`; `POST coupons/validate` unchanged (pre-check only) |
| T78 | Audit trail — writer, API, UI | ✅ | | New `Auditable` trait auto-logs created/updated/deleted on `Sale`, `SaleReturn`, `Purchase`, `Expense`, `JournalEntry`, `User`, `Role`, `Business`, `Coupon`; explicit `auth.login`/`auth.login_failed` events; `GET /v1/audit-logs` (`audit.view`) with actor/action/model/date filters + pagination; Settings → Audit Log page (`DataTable`/`ListToolbar`, detail dialog), fully translated (en/de/bn/ja/ar) |
| T79 | Impersonation hardening | ✅ | | `reason` required (min 10 chars, 422 otherwise); token TTL 24h → 30min with a single `impersonate` ability; new `impersonations` table records who/why/when; `POST super/impersonation/{id}/revoke` ends a live session from the super-admin's own session (the pre-existing `stop` endpoint — for use from inside the impersonated session — was actually unreachable since it sat behind the `super_admin` gate; moved out of it); `GET super/impersonations` lists active sessions; owner gets an in-app notification + email; start/stop both audited |
| T80 | Token lifecycle: expiry, abilities, sessions | ✅ | | `sanctum.expiration` set (14d default, env-overridable) + daily `sanctum:prune-expired`; tokens issued with abilities scoped to the user's permissions (not `*`, except owner/super-admin); `GET/DELETE auth/sessions`, `POST auth/sessions/revoke-all` (device name/IP/last-used); frontend token moved out of localStorage into memory + a `Secure`/`SameSite=Strict` cookie, Settings → Sessions & devices card with revoke buttons — verified end-to-end with Playwright against the running app |
| T81 | Tenant suspension enforcement + token revocation | ✅ | | `EnsureTenant` 403s (`code: tenant_suspended`) once a tenant leaves `trialing`/`active`; login refuses the same; `suspendTenant()` deletes every live token for the tenant's users and audits the action; frontend shows a blocking "account suspended" screen on that code |
| T82 | API rate limiting | ✅ | | `throttleApi()` wired in `bootstrap/app.php`; named limiters in `AppServiceProvider` — global 120/min per-token, `reports` 20/min on report/PDF routes, `login` 5/min per-email + 20/min per-IP on login/register; 429s carry `Retry-After`; frontend `api()` toasts on 429 |
| T83 | Role-escalation guard in user management | ✅ | | `UserController::update` blocks self-role changes, blocks granting/revoking `owner` unless caller is owner/super-admin, and blocks editing users outside a branch-scoped caller's assigned branches; `RoleController::update` blocks editing the `owner` role's permissions the same way; role-assignment changes are audited |
| T84 | Password reset + email verification | ✅ | | `POST auth/forgot-password`/`reset-password` (signed-token round trip, 60min single-use, throttled, revokes existing sessions on reset); `User implements MustVerifyEmail`, `GET auth/email/verify/{id}/{hash}` (signed) + `POST auth/email/resend`; billing-sensitive `subscription/*` routes gated behind `verified`; invited team members get a "set your password" email instead of an undelivered random password; frontend forgot/reset pages + a dismissible "verify your email" banner — verified end-to-end with Playwright against the running app (log mailer, real reset link, full round trip) |
| T85 | Two-factor auth (TOTP) + login lockout | ✅ | | In-house RFC 6238 TOTP (no external dependency, verified against the RFC's test vectors) — `POST auth/2fa/enable`/`confirm`/`disable`/`recovery-codes/regenerate`; login returns `requires_2fa`+`challenge_token` for enrolled accounts, completed via `POST auth/2fa/challenge` (code or single-use recovery code); progressive per-account lockout (1/2/4/…/60min) after 5 failed attempts across login and the 2FA challenge; tenant-level `settings.require_2fa` surfaces `requires_two_factor_setup`; frontend: QR enrollment + recovery codes in Settings → Security, login challenge screen — verified end-to-end with Playwright against the running app (real QR scan simulated via a JS TOTP generator, wrong-code rejection, correct-code login, recovery-code login) |
| T86 | Query & config hardening | ✅ | | `sort`/`direction` whitelisted (`ValidatesSort` trait) on Products/Customers/Suppliers (422 on unknown); `is_super_admin`/`tenant_id` dropped from `User::$fillable`; `config/cors.php` restricted to `FRONTEND_URL`; idempotency keys scoped to tenant+key+method+path with a payload hash (409 on reuse with a different body) + hourly 24h prune; baseline security headers middleware on every API response |
| T87 | Tenant scope fail-closed + job tenant binding | ✅ | | `TenantScope` now throws when no tenant is bound instead of silently running unscoped; two explicit escape hatches — `Model::withoutTenancy()` (query-level) and the new `TenantContext::runUnscoped()` (for framework internals like the password-reset broker); audited and fixed every public/super-admin call site that relied on the old silent-skip behavior (login, 2FA challenge, email verification, revenue metrics, impersonation, etc.); `SendDailyReportJob` already bound its tenant correctly. Full suite (94 tests) + seeders + a live server smoke test all green after the change. |
| T88 | CI security gates | ✅ | #9 | `composer audit` and `bun audit --audit-level=high` (curated ignore-list for unfixable transitive dev-tooling CVEs) now fail the build; PHPStan gate is no longer advisory — a curated `phpstan-baseline.neon` (283 pre-existing larastan false-positives, mostly `Model` vs `User` narrowing across `withoutTenancy()` chains) absorbs the historical noise so `vendor/bin/phpstan analyse` fails CI on any *new* error; new `web` CI job runs `bun run lint`/`build`/`test` on every PR; new `secret-scan` job runs gitleaks; `.github/dependabot.yml` covers composer + bun + github-actions weekly |

## Phase 8 — Product depth

Detail in [`NEXT_PHASE_PLAN.md`](NEXT_PHASE_PLAN.md). T89–T91 unlock revenue; T92–T99 are
vertical depth — pick a lane rather than building all of them.

| ID | Task | Status | PR | Notes |
| -- | ---- | ------ | -- | ----- |
| T89 | Manager approval workflow at POS | ⬜ | | Manager PIN for the actions gated in T76 |
| T90 | Security settings page | ⬜ | | One surface for T80/T84/T85 |
| T91 | Subscription billing gateway | ⬜ | | Plans/Subscriptions exist but nothing can charge a card |
| T92 | Stocktake / cycle counting | ⬜ | | Shrinkage is currently invisible |
| T93 | Reorder suggestions → draft POs | ⬜ | | All inputs already exist; loop not closed |
| T94 | Accounts payable & receivable aging | ⬜ | | Supplier bills + payments missing |
| T95 | Hardware integration | ⬜ | | ESC/POS, cash drawer, scale, label printing |
| T96 | Tax compliance profiles | ✅ | | Full jurisdiction engine — see [`REFACTOR_TAX_PLAN.md`](REFACTOR_TAX_PLAN.md): country profiles (BD_VAT/IN_GST/EU_VAT/US_SALES_TAX/GENERIC), multi-component tax groups, zones, per-line/component/invoice rounding, tax-inclusive pricing, exemptions, `taxes` permission module, admin + report UI |
| T97 | Restaurant mode | ⬜ | | Tables, KOT, modifiers, split bills |
| T98 | Offline sync conflict resolution | ⬜ | | Server side of T60's offline queue |
| T99 | AI assist | ⬜ | | Forecasting, NL reports, receipt OCR |

## Phase 9 — Gap fixes + missing endpoints

A pass over the T28–T33 modules (which shipped with zero feature tests) plus the
functional dead-ends noted in a follow-up review: the immutable stock ledger was
write-only via the API, and a due/partial sale could never be settled.

| ID | Task | Status | Notes |
| -- | ---- | ------ | ----- |
| T100 | Branch & warehouse management | ✅ | Full CRUD (`BranchController`, extended `WarehouseController`); previously branches had zero endpoints and warehouses were list-only. New `branches`/`warehouses` permission modules; delete guards refuse the default branch/warehouse or one with dependent warehouses/users/sales/stock. |
| T101 | Credit-sale settlement + barcode lookup | ✅ | `POST sales/{sale}/payments` (row-locked, `sales.update`, idempotency-aware) records a payment against a due/partial sale and decrements the customer's receivable balance; `GET products/barcode/{barcode}` for POS scanning. |
| T102 | Stock ledger history endpoint | ✅ | `GET inventory/movements` — the `stock_movements` ledger was previously readable only as current balances (`GET inventory/balances`), never as history. |
| T103 | T28–T30 hardening | ✅ | Fixed a real bug: `inventory.adjust` was required by 6 routes (stock transfers, batch/serial create) but didn't exist in `PermissionCatalog`, so no role could ever be granted it. Fixed a second bug: `GET products/{id}/variants` 500'd on every call (eager-loaded a non-existent `attributes.attribute` relation). Replaced inline, tenant-unscoped `exists:table,id` validation in `StockTransferController`, `ProductVariantController` and `QuotationController` with proper `FormRequest`s scoped by `tenant_id` (a cross-tenant reference could previously pass validation). Added the missing feature tests (`StockTransferTest`, `ProductVariantTest`, `QuotationTest`) that would have caught all of this. |
| T104 | Rich demo data for the above | ✅ | `RichDemoSeeder` now also seeds: a stock transfer between the two demo branches (request → approve → receive), 3 product variants + a stock batch + 3 serials, a quotation converted to a sale, and a partially-paid credit sale settled via `collectPayment()`. |

## Phase 10 — Further gaps identified, not yet built

Found during the Phase 9 pass; deliberately left as backlog rather than half-implemented.
Pick a lane the same way as Phase 8.

| ID | Task | Status | Notes |
| -- | ---- | ------ | ----- |
| T105 | Purchases: edit / cancel / return | ⬜ | Only `index`/`show`/`store` exist; `purchases.update`/`.delete`/`.approve` permissions exist with no routes behind them. A wrong goods receipt is currently permanent. |
| T106 | Sale void / cancel | ✅ | `POST sales/{sale}/void` reverses stock and cancels a completed sale; does not auto-refund cash (that's still `POST sales/{sale}/returns`). See [`REFACTOR_TAX_PLAN.md`](REFACTOR_TAX_PLAN.md). |
| T107 | Bulk product import (CSV/XLSX) | ⬜ | Export is client-side CSV only; import was explicitly deferred in T68. Usually the top onboarding blocker for a shop with a large catalog. |
| T108 | Server-side export endpoints | ⬜ | Eight modules declare `*.export` permissions with no backing endpoint. |
| T109 | Financial statements | ⬜ | Accounting has trial balance + general ledger only — no P&L or balance sheet. |
| T110 | Public API keys / webhooks | ⬜ | No way to integrate an external storefront or push to external accounting; the one SaaS-tier gap not already covered by Phase 8. |

## Phase 11 — Audit pass (correctness, load safety, contract drift)

A full-repo audit of the running app rather than a module review. Findings were ranked by
blast radius; T111–T116 are done, T117–T118 are the two that are feature-sized.

| ID | Task | Status | Notes |
| -- | ---- | ------ | ----- |
| T111 | Idempotency mutex | ✅ | The key row only replayed once an outcome was recorded, so a retry arriving **while the first request was still running** — the usual case, since clients retry on their own timeout — fell through and ran the checkout twice. The row is now claimed before the handler runs: an in-flight duplicate gets `409` + `Retry-After`, a claim abandoned by a dead worker is reclaimable after 60s via a conditional update, a `5xx` or thrown exception releases it immediately, and losing the insert race is caught and treated as the other request's claim. |
| T112 | Variant/product integrity | ✅ | `items.*.variant_id` was validated only as "exists within this tenant", never as belonging to the line's `product_id` — so a sale could snapshot product A while the ledger decremented a variant of product B, corrupting stock for two products at once. New `App\Rules\VariantMatchesProduct` (resolves the sibling `product_id` from the attribute under validation), applied to sales, purchases, stock transfers, batches and serials. |
| T113 | Page-size & range ceilings | ✅ | All 25 list endpoints took `per_page` straight off the query string, as did the report `limit`s — `?per_page=1000000` was a one-request DoS. `Controller::perPage()`/`limit()` clamp to 200; reports reject a range over 366 days or an inverted one; the general ledger, previously an unbounded `->get()` of an account's whole history, is capped. |
| T114 | Sargable date filters + `sold_at` index | ✅ | 13 `whereDate()` filters wrapped the column in `date()`, making them non-sargable so no index could be used. Replaced with half-open bounds via `Controller::filterByDateRange()` (malformed dates now `422` instead of a `500` from Postgres). Added `(tenant_id, sold_at)` and `(tenant_id, status, sold_at)` — every sales list, KPI and report filters `sold_at` but only `created_at` was indexed. |
| T115 | Query collapse + strict Eloquent | ✅ | `salesSummary` cloned its query for each of 7 figures (7 scans of the same rows) and the dashboard did 8 plus a correlated subquery per balance row; both are now single-pass `selectRaw` via `toBase()`, the subquery a join. `Model::shouldBeStrict()` on outside production — it immediately caught `TenantProvisioner` passing a non-fillable `tenant_id` to `User::create()`, where the value was silently dropped and came from ambient context instead. |
| T116 | Accounting module contract drift | ✅ | The whole Accounting page was written against an API that doesn't exist: `POST /journal-entries`, `GET /journal-entries` and `GET /accounts/trial-balance` all 404'd, the post body sent `posted_at`/`direction` where the API takes `entry_date`/`type`, and the list read `entry_number`/`posted_at`/`code` where the resources return `reference`/`entry_date`/`account_number`. Frontend realigned; backend gained the one thing genuinely missing — `trial-balance` now returns each balance in its normal-side column with `total_debit`/`total_credit` (a single `balance` figure isn't a trial balance), and `AccountResource` exposes the derived `normal_side`. `JournalService::isDebitNormal()` is now the single source of that rule. |
| T117 | Post journals from trading activity | ⬜ | **Largest remaining gap.** `JournalService` is called only by `JournalController` — no sale, purchase, expense, return or cash-register movement posts an entry, so the trial balance and general ledger only ever reflect manually-typed journals and never the business's actual trading. `JournalService::post()` already accepts `source_type`/`source_id` for exactly this. Prerequisite for T109. |
| T118 | Per-tenant invoice numbering | ⬜ | `invoice_number` is derived from the **global** `sales.id` (`INV-000042`), so each tenant's sequence has gaps that leak other tenants' volume, and it costs a second write per sale (insert with a `PENDING-` placeholder, then update). Most jurisdictions require a gapless per-business sequence. |
