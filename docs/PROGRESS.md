# SmartPOS Pro — Progress Tracker

Single source of truth for **what's done vs. not**. Task IDs map to
[`REQUIREMENTS.md`](REQUIREMENTS.md). The `/implement-task <id>` skill updates this file
when it finishes a task.

**Status legend:** ✅ done · 🚧 in progress · ⬜ todo

_Last updated: 2026-07-25_

## Summary

| Phase | Done | In progress | Todo | Total |
| ----- | ---- | ----------- | ---- | ----- |
| 1 · Backend foundation & modules | 27 | 0 | 0 | 27 |
| 2 · Backend, remaining | 0 | 0 | 10 | 10 |
| 3 · Frontend | 5 | 0 | 18 | 23 |
| 4 · Quality & delivery | 0 | 0 | 4 | 4 |
| **Total** | **32** | **0** | **32** | **64** |

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
| T28 | Stock transfers | ⬜ | | |
| T29 | Variants/batches/serials/expiry endpoints | ⬜ | | |
| T30 | Quotations, drafts & held-sale conversion | ⬜ | | |
| T31 | Super-admin: tenant management | ⬜ | | |
| T32 | Super-admin: billing & revenue metrics | ⬜ | | |
| T33 | Super-admin: impersonation | ⬜ | | |
| T34 | Real-time broadcasting (Reverb) | ⬜ | | |
| T35 | Notification channels (email/SMS/WhatsApp) | ⬜ | | |
| T36 | Scheduled & exportable reports | ⬜ | | |
| T37 | OpenAPI docs + Postman | ⬜ | | |

## Phase 3 — Frontend

| ID | Task | Status | PR | Notes |
| -- | ---- | ------ | -- | ----- |
| T38 | FE scaffold: design tokens + UI primitives | ✅ | this PR | Next 16, Tailwind v4 |
| T39 | FE: API client, auth store, query provider | ✅ | this PR | |
| T40 | FE: Auth pages (login/register) | ✅ | this PR | wired to API |
| T41 | FE: App shell (sidebar/header/guard) | ✅ | this PR | |
| T42 | FE: Dashboard (KPIs + chart) | ✅ | this PR | Recharts |
| T43 | FE: Products list + create/edit | ⬜ | | |
| T44 | FE: POS terminal | ⬜ | | |
| T45 | FE: Inventory overview | ⬜ | | |
| T46 | FE: Sales list + detail + returns | ⬜ | | |
| T47 | FE: Customers UI | ⬜ | | |
| T48 | FE: Suppliers UI | ⬜ | | |
| T49 | FE: Purchases UI | ⬜ | | |
| T50 | FE: Expenses UI | ⬜ | | |
| T51 | FE: Reports UI | ⬜ | | |
| T52 | FE: Accounting UI | ⬜ | | |
| T53 | FE: Cash register UI | ⬜ | | |
| T54 | FE: Team & roles UI | ⬜ | | |
| T55 | FE: Settings & onboarding UI | ⬜ | | |
| T56 | FE: Loyalty & coupons UI | ⬜ | | |
| T57 | FE: Subscription/billing page | ⬜ | | |
| T58 | FE: Notifications + command palette | ⬜ | | |
| T59 | FE: Theme toggle & responsive polish | ⬜ | | |
| T60 | FE: PWA + offline POS | ⬜ | | |

## Phase 4 — Quality & delivery

| ID | Task | Status | PR | Notes |
| -- | ---- | ------ | -- | ----- |
| T61 | E2E tests (Playwright): POS checkout | ⬜ | | |
| T62 | Frontend component/unit tests | ⬜ | | |
| T63 | Richer demo seed data | ⬜ | | |
| T64 | Deployment guides & infra hardening | ⬜ | | |
