# SmartPOS Pro — Progress Tracker

Single source of truth for **what's done vs. not**. Task IDs map to
[`REQUIREMENTS.md`](REQUIREMENTS.md). The `/implement-task <id>` skill updates this file
when it finishes a task.

**Status legend:** ✅ done · 🚧 in progress · ⬜ todo

_Last updated: 2026-07-29_

## Summary

| Phase | Done | In progress | Todo | Total |
| ----- | ---- | ----------- | ---- | ----- |
| 1 · Backend foundation & modules | 27 | 0 | 0 | 27 |
| 2 · Backend, remaining | 10 | 0 | 0 | 10 |
| 3 · Frontend | 23 | 0 | 0 | 23 |
| 4 · Quality & delivery | 4 | 0 | 0 | 4 |
| 5 · Design system refresh + i18n & currency | 10 | 0 | 0 | 10 |
| 6 · Printable documents | 1 | 0 | 0 | 1 |
| 7 · Security & money integrity | 7 | 0 | 6 | 13 |
| 8 · Product depth | 0 | 0 | 11 | 11 |
| **Total** | **82** | **0** | **17** | **99** |

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
| T79 | Impersonation hardening | ⬜ | | **High** — 24h full-owner token, no reason, no log, no revoke |
| T80 | Token lifecycle: expiry, abilities, sessions | ⬜ | | **High** — `sanctum.expiration = null`, `*` abilities, token in localStorage |
| T81 | Tenant suspension enforcement + token revocation | ✅ | | `EnsureTenant` 403s (`code: tenant_suspended`) once a tenant leaves `trialing`/`active`; login refuses the same; `suspendTenant()` deletes every live token for the tenant's users and audits the action; frontend shows a blocking "account suspended" screen on that code |
| T82 | API rate limiting | ✅ | | `throttleApi()` wired in `bootstrap/app.php`; named limiters in `AppServiceProvider` — global 120/min per-token, `reports` 20/min on report/PDF routes, `login` 5/min per-email + 20/min per-IP on login/register; 429s carry `Retry-After`; frontend `api()` toasts on 429 |
| T83 | Role-escalation guard in user management | ✅ | | `UserController::update` blocks self-role changes, blocks granting/revoking `owner` unless caller is owner/super-admin, and blocks editing users outside a branch-scoped caller's assigned branches; `RoleController::update` blocks editing the `owner` role's permissions the same way; role-assignment changes are audited |
| T84 | Password reset + email verification | ⬜ | | **High** — neither exists; invited users get an undelivered password |
| T85 | Two-factor auth (TOTP) + login lockout | ⬜ | | **High** — no 2FA, no per-account lockout |
| T86 | Query & config hardening | ✅ | | `sort`/`direction` whitelisted (`ValidatesSort` trait) on Products/Customers/Suppliers (422 on unknown); `is_super_admin`/`tenant_id` dropped from `User::$fillable`; `config/cors.php` restricted to `FRONTEND_URL`; idempotency keys scoped to tenant+key+method+path with a payload hash (409 on reuse with a different body) + hourly 24h prune; baseline security headers middleware on every API response |
| T87 | Tenant scope fail-closed + job tenant binding | ⬜ | | Medium — `TenantScope` no-ops when no tenant is bound |
| T88 | CI security gates | ⬜ | | Medium — no dependency audit; PHPStan advisory; `web/` never built in CI |

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
| T96 | Tax compliance profiles | ⬜ | | NBR / GST / EU VAT |
| T97 | Restaurant mode | ⬜ | | Tables, KOT, modifiers, split bills |
| T98 | Offline sync conflict resolution | ⬜ | | Server side of T60's offline queue |
| T99 | AI assist | ⬜ | | Forecasting, NL reports, receipt OCR |
