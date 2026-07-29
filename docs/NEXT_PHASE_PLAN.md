# SmartPOS Pro — Next Implementation Plan (Phase 7 & 8)

Source of this plan: a security + feature review of the repo at commit `9f4144d` (all 75
roadmap tasks ✅). Tasks below continue the numbering in
[`REQUIREMENTS.md`](REQUIREMENTS.md) and are tracked in [`PROGRESS.md`](PROGRESS.md).
Run any of them with `/implement-task <id>`.

- **Phase 7 — Security & money integrity (T76–T88).** Closes the holes found in the
  review. Ship before onboarding a paying tenant.
- **Phase 8 — Product depth (T89–T99).** What turns the app from feature-complete into
  sellable.

---

## Why Phase 7 comes first

The architecture is sound — global tenant scope, a single stock choke point with
`SELECT … FOR UPDATE`, transactional checkout, idempotency keys. The gaps are in
**authorization and money integrity**, not plumbing.

| # | Finding | Severity | Where | Task |
| - | ------- | -------- | ----- | ---- |
| 1 | Client-supplied `unit_price` and uncapped discounts accepted at checkout | **Critical** | [`SaleService.php:174`](../app/Services/Sales/SaleService.php#L174), [`StoreSaleRequest.php:25-33`](../app/Http/Requests/Sales/StoreSaleRequest.php#L25-L33) | T76 |
| 2 | Coupons never consumed at checkout; `used_count` never incremented anywhere | **Critical** | [`CouponController.php:49`](../app/Http/Controllers/Api/V1/CouponController.php#L49) | T77 |
| 3 | `users.update` holder can grant themselves the `owner` role | **Critical** | [`UserController.php:54-84`](../app/Http/Controllers/Api/V1/UserController.php#L54-L84), [`HasRoles.php:49`](../app/Support/Concerns/HasRoles.php#L49) | T83 |
| 4 | Tokens never expire, no abilities, persisted to `localStorage` | **High** | [`config/sanctum.php:53`](../config/sanctum.php#L53), [`auth.ts:45-48`](../web/src/stores/auth.ts#L45-L48) | T80 |
| 5 | Suspended tenants keep full API access; tokens not revoked on suspend | **High** | [`AuthController.php:43`](../app/Http/Controllers/Api/V1/AuthController.php#L43) | T81 |
| 6 | `audit_logs` table exists with **zero writes** in the codebase | **High** | [migration](../database/migrations/2026_07_25_000123_create_support_tables.php) | T78 |
| 7 | Impersonation: 24h full-owner token, no reason, no log, no tenant notice | **High** | [`SuperAdminController.php:113-134`](../app/Http/Controllers/Api/V1/SuperAdminController.php#L113-L134) | T79 |
| 8 | No rate limiting on any authenticated endpoint (`throttleApi()` never called) | **High** | [`bootstrap/app.php:20-28`](../bootstrap/app.php#L20-L28) | T82 |
| 9 | No password reset, no email verification, no 2FA, no lockout | **High** | `routes/api.php` | T84, T85 |
| 10 | `TenantScope` fails open when no tenant is bound | **Medium** | [`TenantScope.php:21`](../app/Support/Scopes/TenantScope.php#L21) | T87 |
| 11 | `is_super_admin` / `tenant_id` mass-assignable on `User` | **Medium** | [`User.php:21-24`](../app/Models/User.php#L21-L24) | T86 |
| 12 | Unvalidated `sort`/`direction` query params (500s + ordering oracle) | **Medium** | [`ProductController.php:33`](../app/Http/Controllers/Api/V1/ProductController.php#L33) and 2 others | T86 |
| 13 | Idempotency key not bound to endpoint; replays across routes; no TTL | **Medium** | [`Idempotency.php:29-37`](../app/Http/Middleware/Idempotency.php#L29-L37) | T86 |
| 14 | No `config/cors.php` → framework default `allowed_origins: ['*']` | **Medium** | — | T86 |
| 15 | CI has no dependency audit; PHPStan advisory; `web/` never linted/built | **Medium** | [`ci.yml`](../.github/workflows/ci.yml) | T88 |

### Suggested order

```
T76 → T77 → T78 → T83 → T81 → T82 → T86 → T80 → T79 → T84 → T85 → T87 → T88
└── money integrity ──┘   └── access control ──┘   └── auth surface ──┘  └ infra ┘
```

T76–T78 share the checkout code path and the audit writer — do them back to back.
T88 last, so the new gates run against already-clean code.

---

# Phase 7 — Security & money integrity  (`api` + `web`)

### T76 · Server-side pricing + permission-gated overrides at checkout
- **Scope:** `SaleService::buildLines()` prices every line from the product record. A
  client-supplied `unit_price` or line `discount_amount` is only honoured when the cashier
  holds a new `sales.override_price` / `sales.discount` permission (add both to
  `PermissionCatalog`, granted to owner/manager roles by default, **not** cashier).
  Validate sale-level `discount_amount` ≤ subtotal so `total` can never go negative. Every
  accepted override writes an `audit_logs` row (see T78 — land the audit writer first if
  ordering allows, otherwise stub the call and wire it in T78). POS UI: show the price field
  as read-only unless `can('sales.override_price')`, and surface the 422 on over-discount.
- **Done when:** a cashier posting `unit_price: 0` gets a 403 and the sale is priced from
  the catalog; a manager's override succeeds and produces an audit row; discount > subtotal
  is rejected; feature tests cover all three.

### T77 · Coupon enforcement inside the sale transaction
- **Scope:** `POST /v1/sales` accepts `coupon_code`; `SaleService` re-evaluates the coupon
  inside the existing DB transaction (active, date window, min-spend, `usage_limit`), derives
  the discount server-side, increments `used_count` under a row lock, and records the coupon
  on the sale. `POST coupons/validate` stays as the pre-check for UI only. Add a
  `coupon_id` + `coupon_discount` column to `sales`.
- **Done when:** a coupon at its `usage_limit` is rejected at checkout (not just at
  validate); two concurrent checkouts on a limit-1 coupon result in exactly one redemption
  (test); the discount in the response comes from the server, not the request.

### T78 · Audit trail — writer, API, and UI
- **Scope:** `AuditLogService` + an `Auditable` trait/observer recording `created/updated/
  deleted` on money- and access-sensitive models (`Sale`, `SaleReturn`, `Purchase`,
  `Expense`, `JournalEntry`, `StockMovement` adjustments, `User`, `Role`, `Business`
  settings, `Coupon`), capturing `old_values`/`new_values`/`ip_address`/`user_agent`.
  Explicit `log()` calls for non-CRUD events (price override, discount, void, refund,
  impersonation, login, failed login). `GET /v1/audit-logs` guarded by
  `permission:audit.view` with filters (actor, action, model, date range) + pagination.
  Frontend: Settings → Audit log page using `DataTable` + `ListToolbar`.
- **Done when:** a sale, a role change, and a price override each produce a queryable audit
  row; the log page filters and paginates; rows are immutable (no update/delete endpoint).

### T79 · Impersonation hardening
- **Scope:** `POST super/tenants/{tenant}/impersonate` requires a `reason` (min 10 chars);
  token TTL drops 24h → 30 min with a `impersonate` ability only; the issued token is
  recorded so `POST super/impersonation/{id}/revoke` works **from the super-admin session**
  (today `stopImpersonation` only works from inside the impersonated session); an audit row
  is written on start and stop; the tenant owner gets an in-app notification + email. Add an
  active-impersonations list to the super-admin API.
- **Done when:** impersonating without a reason 422s; the super-admin can revoke a live
  impersonation; the tenant owner sees a notification; start/stop both audited.

### T80 · Token lifecycle: expiry, abilities, device & session management
- **Scope:** set `sanctum.expiration` (default 60×24×14, env-overridable) and prune expired
  tokens on a schedule. Issue tokens with abilities derived from the user's permissions
  instead of `*`. Add `GET /v1/auth/sessions`, `DELETE /v1/auth/sessions/{id}`, and
  `POST /v1/auth/sessions/revoke-all`, storing device name / IP / last-used. Frontend: move
  the token out of `localStorage` — keep it in memory in the zustand store and mirror it to a
  `Secure`/`SameSite=Strict` cookie the app reads on boot; add a Settings → Security tab
  listing active devices with revoke buttons.
- **Done when:** an expired token 401s; revoking a device invalidates only that token;
  `localStorage` no longer holds a bearer token; the session list shows real device rows.

### T81 · Tenant suspension enforcement + token revocation
- **Scope:** `EnsureTenant` rejects requests whose tenant is not `active` with a 403 and a
  machine-readable code (`tenant_suspended`); `AuthController::login` refuses the same.
  `suspendTenant()` deletes the tenant's `personal_access_tokens` and audits the action.
  Frontend: a blocking "account suspended" screen on that error code.
- **Done when:** suspending a tenant immediately breaks its live tokens and blocks new
  logins; reactivating restores access; feature test covers both directions.

### T82 · API rate limiting
- **Scope:** `$middleware->throttleApi()` in `bootstrap/app.php` plus named limiters in
  `AppServiceProvider`: a global per-token limit, a tighter one for report/PDF endpoints
  (expensive), and a per-email + per-IP limiter on login/register (today it is IP-only, so
  distributed credential stuffing is unaffected). Return `Retry-After` and surface 429s as a
  toast in the frontend API client.
- **Done when:** exceeding the limit returns 429 with `Retry-After`; report endpoints have
  their own lower ceiling; login lockout is per-account as well as per-IP.

### T83 · Role-escalation guard in user management
- **Scope:** `UserController::update` refuses to modify the caller's own roles, refuses to
  grant or revoke `owner` unless the caller is the owner (or super-admin), and refuses to
  edit a user in another branch when the caller lacks tenant-wide scope. Same guards on
  `RoleController::update` for editing the `owner` role's permission set. Audit every role
  change (T78).
- **Done when:** a manager with `users.update` cannot assign themselves `owner` (403,
  tested); ownership transfer is an explicit owner-only action.

### T84 · Password reset + email verification
- **Scope:** `POST auth/forgot-password`, `POST auth/reset-password` (signed, single-use,
  60-min tokens, throttled), `POST auth/email/verify/{id}/{hash}`,
  `POST auth/email/resend`. `User implements MustVerifyEmail`. Invited team members
  (`status: invited`) get a set-password link instead of the current `Str::random(16)`
  password that is never delivered anywhere. Frontend: forgot/reset pages + a
  "verify your email" banner.
- **Done when:** the full reset round trip works against the log mailer; an unverified owner
  is blocked from billing-sensitive actions; invited users can set their own password.

### T85 · Two-factor auth (TOTP) + login lockout
- **Scope:** TOTP enrolment (QR + secret), recovery codes, a `two_factor_confirmed_at`
  column, a challenge step in the login flow, and an optional tenant-level "require 2FA for
  all users" setting. Progressive lockout after N failed logins per account. Frontend: 2FA
  setup in Settings → Security (shares the page from T80) and a challenge screen after login.
- **Done when:** enrolling, challenging, and recovering all work end to end; lockout
  releases after the cooldown; recovery codes are single-use.

### T86 · Query & config hardening (grab bag)
- **Scope:** (a) whitelist `sort`/`direction` in `ProductController`, `CustomerController`,
  `SupplierController` — reject anything not in an allow-list; (b) drop `is_super_admin` and
  `tenant_id` from `User::$fillable` and set them explicitly where needed; (c) add
  `config/cors.php` restricted to `FRONTEND_URL`; (d) bind idempotency keys to
  `method + path` (and a payload hash) so a key can't replay across endpoints, add a
  scheduled prune of records older than 24h; (e) add the security headers from
  `docs/DEPLOYMENT.md` as middleware rather than nginx-only config.
- **Done when:** `?sort=password` 422s instead of 500ing; the same idempotency key on two
  different routes does not cross-replay; CORS rejects an unknown origin; old idempotency
  rows are pruned.

### T87 · Tenant scope fail-closed + job tenant binding
- **Scope:** `TenantScope` throws when no tenant is bound unless the query opted out via
  `withoutTenancy()` (make the escape hatch explicit rather than implicit). Audit every
  queued job, console command, and event listener that touches a tenant model and wrap it in
  `TenantContext::runAs()`. Add a test that a job dispatched for tenant A cannot read tenant
  B's rows.
- **Done when:** an unscoped query outside `withoutTenancy()` fails loudly in tests;
  `php artisan test` stays green; scheduled jobs still run.

### T88 · CI security gates
- **Scope:** add `composer audit` and `npm audit --audit-level=high` steps; remove
  `continue-on-error` from PHPStan (curate the baseline first); add a `web/` job running
  `npm run lint`, `npm run build`, and `vitest run`; add Dependabot for composer + npm +
  actions. Optionally a secret-scanning step.
- **Done when:** CI fails on a known-vulnerable dependency and on a PHPStan regression;
  frontend lint/build/test run on every PR.

---

# Phase 8 — Product depth  (`api` + `web`)

Ordered by "what a buyer asks for on the first call". T89–T91 are the ones that unlock
revenue; the rest are vertical depth — pick a lane rather than building all of them.

### T89 · Manager approval workflow at POS
- **Scope:** manager PIN / override token flow at the terminal for the actions gated in
  T76 — price change, discount above a tenant-configured threshold, void, no-receipt
  return. A manager authorises in-lane without logging the cashier out; the override is
  audited with both user ids.
- **Done when:** a cashier hitting a gated action gets an approval modal; a valid manager
  PIN completes the action; the audit row names both parties.

### T90 · Security settings page
- **Scope:** one Settings → Security surface pulling together T80/T84/T85: active devices,
  2FA enrolment, password change, login history from the audit log, and (for owners) the
  tenant-wide "require 2FA" toggle.
- **Done when:** every security control lives in one place and reflects live state.

### T91 · Subscription billing gateway
- **Scope:** `Plan` and `Subscription` models exist but nothing can charge a card. Add
  Stripe (international) + a local rail (SSLCommerz / bKash) behind a `PaymentGateway`
  contract: checkout, webhooks (idempotent, signature-verified), trials, proration on
  upgrade/downgrade, dunning + auto-suspend on failure (reuses T81), and invoice PDFs
  (reuses the T75 dompdf setup).
- **Done when:** a tenant can subscribe, upgrade, and cancel; a failed renewal suspends the
  tenant after the dunning window; webhooks are replay-safe.

### T92 · Stocktake / cycle counting
- **Scope:** count sheets (full or by category/location), blind-count mode, counted-vs-system
  variance report, and a single approval that posts adjustments through `StockLedgerService`.
  Shrinkage is currently invisible — there is an adjust endpoint but no reconciliation.
- **Done when:** a count can be started, entered on mobile, reviewed, and posted as one
  audited adjustment batch.

### T93 · Reorder suggestions → draft purchase orders
- **Scope:** reorder point + lead time per product/supplier; a suggestion engine over sales
  velocity and current balances; one click turns suggestions into draft POs grouped by
  supplier. All the inputs (min stock, movement history, suppliers) already exist.
- **Done when:** the suggestions page ranks what to reorder and generates draft purchases.

### T94 · Accounts payable & receivable aging
- **Scope:** supplier bills, partial payments against purchases, AP/AR aging buckets
  (0–30/31–60/61–90/90+), customer statements, and payment reminders. Purchases and customer
  dues exist; the payment side does not.
- **Done when:** aging reports reconcile to the journals; a supplier payment posts through
  `JournalService`.

### T95 · Hardware integration
- **Scope:** ESC/POS receipt printing (network + WebUSB), cash-drawer kick, barcode scanner
  keyboard-wedge tuning, weighing-scale input for by-weight items, and barcode/shelf-label
  printing. Usually the deal-breaker in real store deployments.
- **Done when:** a receipt prints and the drawer opens from the POS terminal on supported
  hardware; label printing produces a scannable barcode.

### T96 · Tax compliance profiles
- **Scope:** per-country tax profiles (rates, rounding rules, tax-inclusive defaults, invoice
  field requirements), VAT/GST return summaries, and e-invoicing hooks — NBR for Bangladesh
  given the primary market, GST for India, EU VAT.
- **Done when:** a tenant picks a country profile and invoices/returns carry the required
  fields and totals.

### T97 · Restaurant mode
- **Scope:** table map, order → kitchen ticket routing, item modifiers and combos, course
  timing, split/merge bills, tips. Gated behind the existing `business.type`.
- **Done when:** a table order flows from seating to KOT to split payment.

### T98 · Offline sync conflict resolution
- **Scope:** T60 shipped an IndexedDB queue and service worker; the server side needs
  deterministic conflict handling when several terminals reconnect at once — sequence
  numbers per terminal, stock re-validation on replay, a conflict queue an operator can
  resolve, and offline invoice numbering that can't collide.
- **Done when:** two terminals selling the last unit offline produce one sale and one flagged
  conflict, never negative stock.

### T99 · AI assist
- **Scope:** demand forecasting feeding T93's reorder points, natural-language report
  queries over the existing report endpoints, and receipt/bill OCR for expense entry.
- **Done when:** each surface returns useful output on the demo dataset with a clear
  confidence/uncertainty display.

---

## Definition of done (unchanged, from `CLAUDE.md`)

- **Backend:** `php artisan test` green · `vendor/bin/pint --test` clean · new endpoints in
  [`API.md`](API.md) · new permission slugs in `PermissionCatalog` · [`PROGRESS.md`](PROGRESS.md) updated.
- **Frontend:** `npm run lint` clean · `npm run build` succeeds · loading/empty/error states
  handled · permission checks via `useAuth().can()`.
- Every Phase 7 task ships with a feature test that **fails before the fix**.
