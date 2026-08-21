# Architecture

SmartPOS Pro is a multi-tenant SaaS backend. This document explains the core
design decisions that keep tenant data isolated, inventory correct under
concurrency, and financial operations safe to retry.

## 1. Multi-tenancy

A **single-database, shared-schema** model is used: every business-owned table has a
`tenant_id` column. Isolation is enforced in three layers so it can never be forgotten
at a call site:

1. **`TenantContext`** ([`app/Support/Tenancy/TenantContext.php`](../app/Support/Tenancy/TenantContext.php))
   is a request-scoped singleton holding the current tenant id.
2. **`ResolveTenant` middleware** sets the context from the authenticated user, and
   **`EnsureTenant`** rejects tenant routes that somehow have no tenant bound.
3. **`BelongsToTenant` trait** ([`app/Support/Concerns/BelongsToTenant.php`](../app/Support/Concerns/BelongsToTenant.php))
   is applied to every tenant-owned model. It:
   - registers the global **`TenantScope`**, which appends `where tenant_id = ?` to
     every query automatically, and
   - auto-fills `tenant_id` from the context on insert.

Because reads are constrained by a global scope, even route-model binding
(`/products/{product}`) is tenant-safe: a product from another tenant resolves to a
404, not another tenant's data.

**The scope fails closed.** A query on a tenant-owned model with no tenant bound
throws (`TenantScope`) rather than silently running unscoped — an unbound context used
to mean "no filter," which is a data leak waiting to happen the first time a route or
job forgot to bind one. There are two explicit, intentional escape hatches:
- **`Model::withoutTenancy()`** when you control the query directly (super-admin routes,
  public auth routes that resolve a user by email before a tenant is known).
- **`TenantContext::runUnscoped(fn () => ...)`** around code you don't control the
  queries of (e.g. Laravel's password-reset broker, which looks up the user internally).

Queued jobs, console commands, and scheduled closures that operate on a specific
tenant must bind it explicitly — `TenantContext::runAs($tenantId, fn () => ...)` for a
callback, or `$context->set($tenantId)` for the lifetime of a job's `handle()` (see
`SendDailyReportJob`). A job that forgets to bind one fails immediately instead of
reading every tenant's rows.

> **Tested by** `ProductTest::test_products_are_isolated_between_tenants` and
> `TenantScopeFailClosedTest`.

## 2. Immutable stock ledger

Inventory is modelled as an **append-only ledger** plus a **materialised balance**:

- `stock_movements` — one immutable row per physical stock change (sale, purchase,
  adjustment, transfer, opening). Each row records the movement magnitude, direction,
  unit cost, the polymorphic `reference` (e.g. the Sale that caused it), and the
  **running balance after** the movement. The model disables `updated_at` to signal
  append-only intent.
- `inventory_balances` — the current on-hand / reserved quantity and weighted-average
  cost per `(product, variant, warehouse)`. This is a projection of the ledger kept in
  sync on every write.

All mutations flow through a single choke point,
[`StockLedgerService::record()`](../app/Services/Inventory/StockLedgerService.php),
which:

1. Locks the balance row with `SELECT ... FOR UPDATE` (`lockForUpdate`), preventing
   lost updates and oversell under concurrent checkouts.
2. Validates availability for outbound movements (unless `allow_negative`).
3. Recomputes weighted-average cost on inbound movements.
4. Writes the balance and appends the ledger row.

Callers must run inside a transaction so the balance update and ledger insert commit
atomically.

## 3. Static frontend build (Vite + React Router 8)

The frontend is a single-page application built with Vite and React Router 8. It produces
a static `dist/` directory consisting of HTML, CSS, and JavaScript assets. Production deployment
uses nginx to serve `dist/` directly, with a `try_files` fallback for SPA routing:

```nginx
server {
    listen 443 ssl http2;
    server_name pos.yourdomain.com;

    ssl_certificate     /etc/ssl/pos.yourdomain.com.crt;
    ssl_certificate_key /etc/ssl/pos.yourdomain.com.key;

    root /var/www/smartpos/dist;
    index index.html;

    location / {
        try_files $uri $uri/ /index.html;
    }

    # Cache assets immutably — content hashes in filenames ensure safe long-term caching
    location /assets/ {
        add_header Cache-Control "public, max-age=31536000, immutable";
    }
}
```

No PM2, no `next start`. The process is purely: `npm run build` → copy `dist/` → nginx reload.

> **SPA fallback**: `try_files $uri $uri/ /index.html` ensures hard refreshes on deep links
> (e.g. `/pos` or `/order/[token]`) resolve correctly. This is the one deployment detail that
> silently breaks everything if missed.

## 4. Idempotency

POST checkout is wrapped by the **`Idempotency` middleware**. When a request carries an
`Idempotency-Key` header, the first response (status + body) is persisted per tenant in
`idempotency_keys`; any later request with the same key replays that stored response
(with an `Idempotent-Replayed: true` header) instead of re-executing. A retried POS sale
therefore never double-charges or double-decrements stock.

## 5. Authorization (RBAC)

- **Permissions** are global rows shaped as `module.action` (e.g. `products.create`,
  `sales.refund`), defined once in
  [`PermissionCatalog`](../app/Support/Rbac/PermissionCatalog.php).
- **Roles** are tenant-scoped and map to a set of permissions. On registration,
  `TenantProvisioner` provisions the default roles (owner, admin, branch_manager,
  cashier, accountant, viewer) for the new tenant.
- Enforcement is via the **`permission:` route middleware**
  (e.g. `->middleware('permission:products.delete')`). The business **owner** implicitly
  holds every permission; a super-admin bypasses tenant scoping entirely.

> **Tested by** `PermissionTest::test_cashier_cannot_delete_products`.

## 6. Registration bootstrap

[`TenantProvisioner::register()`](../app/Services/Auth/TenantProvisioner.php) creates, in
one transaction: the tenant (active on the free plan), its first business, a default branch
and warehouse, the default role set, and the owner user assigned the `owner` role. This
is the single entry point shared by the `/auth/register` endpoint and the demo seeder.

## Request lifecycle (authenticated)

```
Request
  → auth:sanctum         (resolve user from bearer token)
  → tenant               (ResolveTenant: bind TenantContext from user)
  → ensure.tenant        (EnsureTenant: 403 if no tenant)
  → permission:<slug>    (CheckPermission: 403 if missing)
  → [idempotency]        (replay or record, on sensitive writes)
  → Controller → FormRequest validation → Service → Resource
```
