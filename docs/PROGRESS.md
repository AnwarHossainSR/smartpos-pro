# SmartPOS Pro — Progress Tracker

Single source of truth for **what's done vs. not**. Task IDs map to
[`REQUIREMENTS.md`](REQUIREMENTS.md). The `/implement-task <id>` skill updates this file
when it finishes a task.

**Status legend:** ✅ done · 🚧 in progress · ⬜ todo

_Last updated: 2026-07-27_

## Summary

| Phase | Done | In progress | Todo | Total |
| ----- | ---- | ----------- | ---- | ----- |
| 1 · Backend foundation & modules | 27 | 0 | 0 | 27 |
| 2 · Backend, remaining | 10 | 0 | 0 | 10 |
| 3 · Frontend | 23 | 0 | 0 | 23 |
| 4 · Quality & delivery | 4 | 0 | 0 | 4 |
| **Total** | **64** | **0** | **0** | **64** |

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
