# SmartPOS Pro — Requirements & Task Breakdown

The full product vision lives in [`PROJECT_SPEC.md`](PROJECT_SPEC.md). This file breaks
that vision into **small, module-wise tasks** with stable numeric IDs. Live status for
each task is tracked in [`PROGRESS.md`](PROGRESS.md).

**How to use this with the skill:** run `/implement-task <id>` (e.g. `/implement-task 43`)
and the skill reads the task below, implements it following the repo conventions in
[`../CLAUDE.md`](../CLAUDE.md), runs the checks, ticks it off in `PROGRESS.md`, commits to
the dev branch, and opens/updates the PR.

Each task lists a **Scope** (what to build) and **Done when** (acceptance). Keep tasks
small — one task = one coherent, reviewable slice.

---

## Phase 1 — Backend foundation & modules  (`api`)

### T1 · Project scaffold & tooling
- **Scope:** Laravel 13 app, Docker Compose (app/nginx/postgres/redis/queue), Pint, PHPStan, GitHub Actions CI, `.env.example`.
- **Done when:** `composer test` and `vendor/bin/pint --test` run; `docker compose up` boots the API.

### T2 · Multi-tenant foundation
- **Scope:** `TenantContext`, `TenantScope`, `BelongsToTenant`, `ResolveTenant`/`EnsureTenant` middleware.
- **Done when:** every tenant-owned model auto-scopes; cross-tenant read returns 404 (test).

### T3 · Core database schema & migrations
- **Scope:** normalized migrations for all core entities with indexes, FKs, soft deletes, audit fields.
- **Done when:** `php artisan migrate:fresh` runs clean on Postgres and SQLite.

### T4 · Auth (Sanctum) + registration
- **Scope:** register (provisions tenant+business+branch+warehouse+owner), login, me, logout, tokens.
- **Done when:** register/login return a token; auth tests pass.

### T5 · RBAC (roles & permissions)
- **Scope:** `PermissionCatalog`, tenant-scoped roles, `permission:` route middleware.
- **Done when:** a cashier is 403'd from a delete route (test).

### T6 · Categories CRUD
### T7 · Brands CRUD
### T8 · Units CRUD
### T9 · Products CRUD (+ opening stock)
- **Done when:** create with `opening_stock` writes a stock movement; search/filter/paginate works.

### T10 · Inventory: balances, stock ledger & adjustments
- **Scope:** `StockLedgerService` (row-locked, immutable ledger), balances endpoint, manual adjustment.
- **Done when:** adjustments append movements; oversell prevented.

### T11 · Customers CRUD
### T12 · Suppliers CRUD

### T13 · POS checkout (SaleService + idempotency)
- **Scope:** transaction-safe sale (header/items/stock/payments), `Idempotency-Key`.
- **Done when:** checkout decrements stock; insufficient stock rolls back; retry replays (tests).

### T14 · Sales listing & detail
### T15 · Sale returns & refunds
- **Scope:** `SaleReturnService` restocks via ledger, caps at sold−returned, reduces customer due.
- **Done when:** return restocks; over-return rejected (test).

### T16 · Purchases + goods receipt
- **Scope:** `PurchaseService` receives stock via ledger, refreshes cost, updates supplier due.
### T17 · Expenses + categories
### T18 · Reports center
- **Scope:** sales-summary, sales-by-product, profit, inventory-valuation, low-stock, dues.
### T19 · Accounting (accounts + double-entry journals)
- **Scope:** `JournalService` (balanced entries), general ledger, trial balance.
### T20 · Cash register sessions
- **Scope:** open/cash-in/out/close with expected-vs-actual reconciliation.
### T21 · User management
### T22 · Roles & permissions management
### T23 · Notifications API
### T24 · Business settings + onboarding
### T25 · Loyalty points (earn/redeem)
### T26 · Coupons / promotions
### T27 · Subscription plans & limit enforcement

## Phase 2 — Backend, remaining  (`api`)

### T28 · Stock transfers between warehouses/branches
- **Scope:** transfer request/approve/receive; two ledger movements (out+in); in-transit state.
### T29 · Product variants, batches, serials & expiry endpoints
### T30 · Quotations, drafts & held-sale conversion endpoints
### T31 · Super-admin: tenant management API (list, suspend, usage)
### T32 · Super-admin: subscription/billing & revenue metrics
### T33 · Super-admin: tenant impersonation (scoped tokens)
### T34 · Real-time broadcasting (Laravel Reverb) for inventory & sales
### T35 · Notification channels: email/SMS/WhatsApp + domain events
### T36 · Scheduled & exportable reports (PDF/Excel/CSV, emailed)
### T37 · OpenAPI documentation + Postman collection

## Phase 3 — Frontend  (`web`, Next.js 16 + React 19)

### T38 · FE scaffold: design tokens + UI primitives
- **Scope:** Tailwind v4 tokens (light/dark), Button/Card/Input/Badge/Skeleton/Spinner.
### T39 · FE: API client, auth store, query provider
### T40 · FE: Auth pages (login/register)
### T41 · FE: App shell (sidebar, header, auth guard)
### T42 · FE: Dashboard (KPIs + sales chart)
### T43 · FE: Products list + create/edit drawer
- **Scope:** data table (search/filter/sort/paginate), product form (RHF+Zod), delete confirm.
- **Done when:** list reflects API; create/edit/delete round-trips; empty & loading states.
### T44 · FE: POS terminal (grid, cart, checkout)
- **Scope:** product search/grid, cart with qty/discount, customer select, payment modal, checkout with `Idempotency-Key`, receipt preview.
- **Done when:** a sale posts and stock/list update.
### T45 · FE: Inventory overview + adjustment modal
### T46 · FE: Sales list + detail + return flow
### T47 · FE: Customers module UI
### T48 · FE: Suppliers module UI
### T49 · FE: Purchases module UI (create + receive)
### T50 · FE: Expenses module UI
### T51 · FE: Reports dashboard UI (filters + charts + export)
### T52 · FE: Accounting UI (accounts, journals, trial balance)
### T53 · FE: Cash register UI (open/close/reconcile)
### T54 · FE: Team & roles UI (invite, role editor)
### T55 · FE: Settings & onboarding checklist UI
### T56 · FE: Loyalty & coupons UI
### T57 · FE: Subscription / billing page (plans + usage)
### T58 · FE: Notifications panel + command palette (⌘K)
### T59 · FE: Theme toggle & responsive polish
### T60 · FE: PWA + offline POS (service worker, IndexedDB queue, background sync)

## Phase 4 — Quality & delivery  (`qa`)

### T61 · E2E tests (Playwright): POS checkout happy path
### T62 · Frontend component/unit tests
### T63 · Richer demo seed data (multi-branch, returns, journals)
### T64 · Deployment guides & infra hardening (AWS, Cloudflare, Sentry, PostHog)
