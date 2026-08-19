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
### T64 · Deployment guides & infra hardening (AWS, Cloudflare, PostHog)

## Phase 5 — Design system refresh + i18n & currency  (`web`)

Full plan: [`REDESIGN_I18N_PLAN.md`](REDESIGN_I18N_PLAN.md).

### T65 · Token architecture + finalize 6 palettes (light+dark)
- **Scope:** semantic token set (`--bg/--surface/--sidebar-*/--primary/...`) driven by
  `data-theme` attribute in `globals.css`; 6 curated palettes (Amber Retail, Indigo, Emerald,
  Ocean, Violet, Rose), each light + dark.
- **Done when:** palettes render; text/UI contrast passes WCAG AA; no visual break on
  existing pages.
### T66 · Theme store + palette switcher UI, no-flash hydration
- **Scope:** `useThemeStore` gains `palette`; swatch picker in Settings → Appearance + header
  quick toggle; inline hydration script so `data-theme` is set before first paint.
- **Done when:** switch persists across reload; no SSR/CSR flash.
### T67 · Sidebar + shell restyle to dark `--sidebar-*` tokens
- **Scope:** sidebar and app shell re-themed to the new dark-sidebar tokens independent of
  page light/dark mode.
- **Done when:** matches new look; keyboard/active nav states intact.
### T68 · Table + list-page pattern upgrade
- **Scope:** reusable `DataTable` (zebra rows, sticky header, hover, right-aligned numeric
  cols, bulk-select checkbox column, row actions) + toolbar (search + filters +
  import/export buttons); applied to Products first.
- **Done when:** `DataTable` component exists and Products list uses it end to end.
### T69 · i18n foundation
- **Scope:** `src/i18n/` (`config.ts`, `provider.tsx`, `messages/en.json`), `t(key, vars?)`
  with interpolation, locale store persisted to `localStorage` + cookie, `<html lang/dir>`
  driven by locale.
- **Done when:** English strings route through `t()`; switching locale re-renders live.
### T70 · Locale switcher UI + per-script fonts
- **Scope:** locale picker in header + Settings → Localization; `next/font/google` fonts for
  Bangla/Japanese/Arabic scripts applied via `:lang()`/`data-lang`.
- **Done when:** correct font per active language; picker works in both locations.
### T71 · RTL support
- **Scope:** audit hardcoded directional Tailwind classes → logical utilities
  (`ms-*/me-*/start/end/text-start`); `dir="rtl"` for Arabic; direction-aware icon flips.
- **Done when:** `ar` mirrors correctly across app pages.
### T72 · Translation catalogs
- **Scope:** `de.json`, `bn.json`, `ar.json`, `ja.json` mirroring `en.json` keys for all
  shipped UI strings.
- **Done when:** no missing-key fallback gaps in shipped screens.
### T73 · Currency/locale-aware formatting
- **Scope:** tenant `currency` + `locale` settings (Settings → Localization), locale-aware
  `formatCurrency`/`formatNumber`/`formatDate`/`formatDateTime` helpers; audit POS, receipts,
  reports, dashboards for raw `$`/`toFixed` money rendering.
- **Done when:** all money rendering uses the helpers; tenant currency respected end-to-end.
### T74 · QA pass
- **Scope:** `npm run lint` + `npm run build` clean; smoke-check each locale (incl. RTL +
  dark mode); update `docs/PROGRESS.md`/`docs/API.md` as needed.
- **Done when:** lint/build clean; docs updated.

## Phase 6 — Printable documents  (`api` + `web`)

### T75 · Invoice & cash-register payment-slip PDFs
- **Scope:** `barryvdh/laravel-dompdf`; Blade templates for a sale invoice and a cash-register
  session slip; `GET sales/{sale}/invoice` and `GET cash-register-sessions/{session}/slip`
  (`?download=1` forces attachment); wired into the POS receipt modal, the sales list invoice
  view, and the cash-register page. Also fixes a pre-existing bug where the cash-register
  frontend called endpoints/fields (`opening_balance`, `.../sessions/{id}/close`, …) that didn't
  match the backend contract (`opening_cash`, `/cash-register-sessions/{id}/close`, …), and adds
  the missing `GET cash-registers/{cashRegister}/current-session` endpoint the frontend already
  expected.
- **Done when:** invoice/slip PDFs render and download from all three surfaces; cash register
  open/movement/close flow works end-to-end against real endpoints.

## Phase 7 — Security & money integrity  (`api` + `web`)

Plan & rationale (with the review finding each task closes):
[`NEXT_PHASE_PLAN.md`](NEXT_PHASE_PLAN.md). Suggested order:
T76 → T77 → T78 → T83 → T81 → T82 → T86 → T80 → T79 → T84 → T85 → T87 → T88.

### T76 · Server-side pricing + permission-gated overrides at checkout
- **Scope:** price every sale line from the product record in `SaleService::buildLines()`;
  honour a client `unit_price`/line discount only when the cashier holds the new
  `sales.override_price` / `sales.discount` permission (add to `PermissionCatalog`, granted
  to owner/manager, not cashier); validate sale-level `discount_amount` ≤ subtotal so
  `total` can never go negative; audit every accepted override; POS shows the price field
  read-only without the permission.
- **Done when:** a cashier posting `unit_price: 0` gets 403 and the sale is priced from the
  catalog; a manager override succeeds and is audited; discount > subtotal is rejected;
  feature tests cover all three.

### T77 · Coupon enforcement inside the sale transaction
- **Scope:** `POST /v1/sales` accepts `coupon_code`; re-evaluate the coupon inside the
  existing DB transaction (active, date window, min spend, `usage_limit`), derive the
  discount server-side, increment `used_count` under a row lock, store `coupon_id` +
  `coupon_discount` on the sale. `coupons/validate` becomes a UI pre-check only.
- **Done when:** a coupon at its `usage_limit` is rejected at checkout; two concurrent
  checkouts on a limit-1 coupon yield exactly one redemption (test); the discount is
  server-derived.

### T78 · Audit trail — writer, API, and UI
- **Scope:** `AuditLogService` + an `Auditable` observer over money/access-sensitive models
  (`Sale`, `SaleReturn`, `Purchase`, `Expense`, `JournalEntry`, stock adjustments, `User`,
  `Role`, business settings, `Coupon`) capturing old/new values, IP and user agent; explicit
  `log()` calls for non-CRUD events (override, void, refund, impersonation, login, failed
  login); `GET /v1/audit-logs` behind `permission:audit.view` with filters; Settings → Audit
  log page on `DataTable` + `ListToolbar`.
- **Done when:** a sale, a role change and a price override each produce a queryable row;
  the page filters and paginates; rows are immutable (no update/delete route).

### T79 · Impersonation hardening
- **Scope:** require a `reason` (min 10 chars); drop the token TTL 24h → 30 min with an
  `impersonate` ability only; record issued tokens so a super-admin can revoke a live
  session (`POST super/impersonation/{id}/revoke`) — today `stopImpersonation` only works
  from inside the impersonated session; audit start and stop; notify the tenant owner in-app
  and by email; expose an active-impersonations list.
- **Done when:** impersonating without a reason 422s; a super-admin can revoke a live
  impersonation; the tenant owner is notified; start/stop are both audited.

### T80 · Token lifecycle: expiry, abilities, device & session management
- **Scope:** set `sanctum.expiration` (env-overridable) + scheduled pruning; issue tokens
  with permission-derived abilities instead of `*`; add `GET /v1/auth/sessions`,
  `DELETE /v1/auth/sessions/{id}`, `POST /v1/auth/sessions/revoke-all` storing device name,
  IP and last-used; move the bearer token out of `localStorage` (in-memory store mirrored to
  a `Secure`/`SameSite=Strict` cookie); Settings → Security device list.
- **Done when:** an expired token 401s; revoking a device kills only that token;
  `localStorage` holds no bearer token; the session list shows real rows.

### T81 · Tenant suspension enforcement + token revocation
- **Scope:** `EnsureTenant` and `AuthController::login` reject non-`active` tenants with a
  `tenant_suspended` code; `suspendTenant()` deletes the tenant's `personal_access_tokens`
  and audits the action; frontend shows a blocking suspended screen.
- **Done when:** suspending breaks live tokens and blocks new logins; reactivating restores
  access; a feature test covers both directions.

### T82 · API rate limiting
- **Scope:** call `$middleware->throttleApi()` in `bootstrap/app.php` plus named limiters in
  `AppServiceProvider` — a global per-token limit, a tighter one for report/PDF endpoints,
  and per-email *as well as* per-IP limits on login/register; return `Retry-After`; surface
  429 as a toast in `src/lib/api.ts`.
- **Done when:** exceeding a limit returns 429 with `Retry-After`; reports have their own
  lower ceiling; login lockout is per-account as well as per-IP.

### T83 · Role-escalation guard in user management
- **Scope:** `UserController::update` refuses self-role edits, refuses granting or revoking
  `owner` unless the caller is the owner or super-admin, and refuses cross-branch edits
  without tenant-wide scope; the same guards on editing the `owner` role in
  `RoleController`; audit every role change.
- **Done when:** a manager with `users.update` cannot assign themselves `owner` (403,
  tested); ownership transfer is an explicit owner-only action.

### T84 · Password reset + email verification
- **Scope:** `auth/forgot-password`, `auth/reset-password` (signed, single-use, 60-minute,
  throttled), `auth/email/verify/{id}/{hash}`, `auth/email/resend`; `User implements
  MustVerifyEmail`; invited team members get a set-password link instead of the current
  undelivered `Str::random(16)` password; frontend forgot/reset pages + verify banner.
- **Done when:** the reset round trip works against the log mailer; an unverified owner is
  blocked from billing-sensitive actions; invited users can set their own password.

### T85 · Two-factor auth (TOTP) + login lockout
- **Scope:** TOTP enrolment (QR + secret), single-use recovery codes, a
  `two_factor_confirmed_at` column, a login challenge step, an optional tenant-wide
  "require 2FA" setting, and progressive per-account lockout after repeated failures; UI in
  Settings → Security plus a post-login challenge screen.
- **Done when:** enrol, challenge and recovery all work end to end; lockout releases after
  the cooldown; recovery codes are single-use.

### T86 · Query & config hardening
- **Scope:** whitelist `sort`/`direction` in `ProductController`, `CustomerController` and
  `SupplierController`; drop `is_super_admin` and `tenant_id` from `User::$fillable`; add
  `config/cors.php` restricted to `FRONTEND_URL`; bind idempotency keys to method + path
  (plus a payload hash) and prune records older than 24h; move the `docs/DEPLOYMENT.md`
  security headers into middleware.
- **Done when:** `?sort=password` 422s instead of 500ing; one key cannot cross-replay
  between routes; CORS rejects an unknown origin; old idempotency rows are pruned.

### T87 · Tenant scope fail-closed + job tenant binding
- **Scope:** `TenantScope` throws when no tenant is bound unless the caller opted out via
  `withoutTenancy()`; audit every job, command and listener that touches a tenant model and
  wrap it in `TenantContext::runAs()`; add a test that a job for tenant A cannot read
  tenant B.
- **Done when:** an unscoped query outside `withoutTenancy()` fails loudly in tests;
  `php artisan test` stays green; scheduled jobs still run.

### T88 · CI security gates
- **Scope:** add `composer audit` and `npm audit --audit-level=high` steps; remove
  `continue-on-error` from PHPStan once the baseline is curated; add a `web/` job running
  `npm run lint`, `npm run build` and `vitest run`; add Dependabot for composer, npm and
  actions.
- **Done when:** CI fails on a known-vulnerable dependency and on a PHPStan regression;
  frontend lint/build/test run on every PR.

## Phase 8 — Product depth  (`api` + `web`)

Detail and rationale in [`NEXT_PHASE_PLAN.md`](NEXT_PHASE_PLAN.md). T89–T91 unlock revenue;
T92–T99 are vertical depth — pick a lane rather than building all of them.

### T89 · Manager approval workflow at POS
- **Scope:** manager PIN / override token at the terminal for the actions gated in T76 —
  price change, discount above a tenant-configured threshold, void, no-receipt return —
  authorised in-lane without logging the cashier out.
- **Done when:** a gated action opens an approval modal; a valid manager PIN completes it;
  the audit row names both users.

### T90 · Security settings page
- **Scope:** one Settings → Security surface combining T80/T84/T85: active devices, 2FA
  enrolment, password change, login history from the audit log, and the tenant-wide
  "require 2FA" toggle for owners.
- **Done when:** every security control lives in one place and reflects live state.

### T91 · Subscription billing gateway
- **Scope:** a `PaymentGateway` contract with Stripe plus a local rail (SSLCommerz/bKash):
  checkout, signature-verified idempotent webhooks, trials, proration on upgrade/downgrade,
  dunning and auto-suspend on failure (reuses T81), invoice PDFs (reuses T75's dompdf setup).
- **Done when:** a tenant can subscribe, upgrade and cancel; a failed renewal suspends after
  the dunning window; webhooks are replay-safe.

### T92 · Stocktake / cycle counting
- **Scope:** count sheets (full or by category/location), blind-count mode, a
  counted-vs-system variance report, and a single approval that posts adjustments through
  `StockLedgerService`.
- **Done when:** a count can be started, entered on mobile, reviewed and posted as one
  audited adjustment batch.

### T93 · Reorder suggestions → draft purchase orders
- **Scope:** reorder point and lead time per product/supplier; a suggestion engine over
  sales velocity and current balances; one click turns suggestions into draft purchase
  orders grouped by supplier.
- **Done when:** the suggestions page ranks what to reorder and generates draft purchases.

### T94 · Accounts payable & receivable aging
- **Scope:** supplier bills, partial payments against purchases, AP/AR aging buckets
  (0–30/31–60/61–90/90+), customer statements and payment reminders; payments post through
  `JournalService`.
- **Done when:** aging reports reconcile to the journals.

### T95 · Hardware integration
- **Scope:** ESC/POS receipt printing (network + WebUSB), cash-drawer kick, barcode-scanner
  keyboard-wedge tuning, weighing-scale input for by-weight items, and barcode/shelf-label
  printing.
- **Done when:** a receipt prints and the drawer opens from POS on supported hardware;
  labels produce a scannable barcode.

### T96 · Tax compliance profiles
- **Scope:** per-country tax profiles (rates, rounding rules, tax-inclusive defaults,
  required invoice fields), VAT/GST return summaries, and e-invoicing hooks — NBR for
  Bangladesh, GST for India, EU VAT.
- **Done when:** a tenant picks a country profile and invoices/returns carry the required
  fields and totals.

### T97 · Restaurant mode
- **Scope:** table map, kitchen ticket routing, item modifiers and combos, course timing,
  split/merge bills, tips — gated behind `business.type`.
- **Done when:** a table order flows from seating to KOT to split payment.

### T98 · Offline sync conflict resolution
- **Scope:** per-terminal sequence numbers, stock re-validation on replay, an
  operator-facing conflict queue, and collision-free offline invoice numbering on top of
  T60's IndexedDB queue.
- **Done when:** two terminals selling the last unit offline produce one sale and one
  flagged conflict, never negative stock.

### T99 · AI assist
- **Scope:** demand forecasting feeding T93's reorder points, natural-language queries over
  the existing report endpoints, and receipt/bill OCR for expense entry.
- **Done when:** each surface returns useful output on the demo dataset with a clear
  confidence display.

## Phase 9 — Gap fixes + missing endpoints  (`api`)

Found by re-reading T28–T33 against their own routes/permissions/tests (which didn't exist).

### T100 · Branch & warehouse management
- **Scope:** `BranchController` (index/show/store/update/destroy) — previously there was no
  way to create a branch outside registration. Extend `WarehouseController` with
  store/update/destroy (it was index-only). New `branches`/`warehouses` permission modules.
  Destroy refuses the default branch/warehouse, or one with dependent warehouses, assigned
  users, or sales history.
- **Done when:** an owner can create/rename/retire a branch or warehouse via the API; a
  cashier can't; deleting the default or a still-referenced one 422s.

### T101 · Credit-sale settlement + barcode lookup
- **Scope:** `POST sales/{sale}/payments` (`sales.update`, idempotency-aware) records a
  payment against a due/partial sale inside a row-locked transaction, updates
  `paid_amount`/`due_amount`/`payment_status`, and decrements the customer's `balance`.
  `GET products/barcode/{barcode}` for POS scanning.
- **Done when:** a partial sale can be paid down to `paid` in one or more calls; overpaying
  the outstanding balance 422s; a barcode resolves to its product or 404s.

### T102 · Stock ledger history endpoint
- **Scope:** `GET inventory/movements` (`inventory.view`), filterable by
  product/warehouse/variant/type/direction/date, paginated. The `stock_movements` ledger —
  the architecture's centerpiece — was previously readable only as current balances.
- **Done when:** every movement type (opening, sale, adjustment, transfer) is visible with
  its running `quantity_after`.

### T103 · T28–T30 hardening
- **Scope:** fix two live bugs found while writing the tests these tasks shipped without:
  (a) `inventory.adjust` was required by 6 routes but absent from `PermissionCatalog`, so no
  role could ever hold it; (b) `GET products/{id}/variants` 500'd on every call (eager-loaded
  a relation, `attributes.attribute`, that doesn't exist on `ProductVariant`). Replace the
  inline `$request->validate(['...id' => 'exists:table,id'])` calls in
  `StockTransferController`, `ProductVariantController` and `QuotationController` — which
  don't scope by tenant, unlike every `FormRequest` elsewhere in the codebase — with proper
  `FormRequest`s using `Rule::exists(...)->where('tenant_id', ...)`.
- **Done when:** `php artisan test` covers stock transfers, variants/batches/serials and
  quotations end to end, including a cross-tenant-reference rejection test for each.

### T104 · Rich demo data for the above
- **Scope:** extend `RichDemoSeeder`: a stock transfer between the two demo branches
  (request → approve → receive), 3 product variants + a stock batch + 3 serials, a
  quotation converted to a sale, and a partially-paid credit sale settled via
  `collectPayment()`.
- **Done when:** `php artisan migrate:fresh --seed` produces at least one record for every
  T100–T103 feature, verifiable via tinker.

## Phase 10 — Further gaps identified, not yet built  (`api` + `web`)

Found during the Phase 9 pass. Left as backlog rather than half-implemented — pick a lane
the same way as Phase 8.

### T105 · Purchases: edit / cancel / return
- **Scope:** `purchases.update`/`.delete`/`.approve` permissions already exist with no
  routes behind them; only `index`/`show`/`store` exist. Add edit (before goods receipt),
  cancel, and a purchase return / debit note.
- **Done when:** a wrong goods receipt can be corrected without going straight to the
  database.

### T106 · Sale void / cancel
- **Scope:** `sales.delete` permission exists, no route. Returns are the only correction
  path today; a cashier's mis-rung sale can't be voided.
- **Done when:** a completed sale can be voided (stock and payments reversed through the
  same services returns already use) with an audited reason.

### T107 · Bulk product import (CSV/XLSX)
- **Scope:** import was explicitly deferred in T68 (export is client-side CSV only).
  Usually the top onboarding blocker for a shop with a large existing catalog.
- **Done when:** a CSV/XLSX of products can be uploaded, previewed, and committed with
  row-level error reporting.

### T108 · Server-side export endpoints
- **Scope:** eight modules (`products`, `inventory`, `sales`, `purchases`, `customers`,
  `suppliers`, `reports`, `accounting`) declare `*.export` permissions with no backing
  endpoint — export today is client-side only.
- **Done when:** each module's list can be exported server-side (CSV, and PDF where a
  template already exists).

### T109 · Financial statements
- **Scope:** accounting currently has chart of accounts, journals, general ledger and trial
  balance only — no P&L (income statement) or balance sheet.
- **Done when:** both statements are generated from the existing journal data for a given
  period.

### T110 · Public API keys / webhooks
- **Scope:** tenant-scoped API keys (separate from user Sanctum tokens) and outbound
  webhooks (sale completed, stock low, etc.) — no way today to integrate an external
  storefront or push to external accounting. The one SaaS-tier gap not already covered by
  Phase 8.
- **Done when:** a tenant can mint/revoke an API key scoped to a permission subset, and
  register a webhook URL that receives signed event payloads with retries.
