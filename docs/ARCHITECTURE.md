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
404, not another tenant's data. A `withoutTenancy()` escape hatch exists for
super-admin / system jobs only.

> **Tested by** `ProductTest::test_products_are_isolated_between_tenants`.

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

## 3. Transaction-safe POS checkout

[`SaleService::create()`](../app/Services/Sales/SaleService.php) wraps the entire
checkout in `DB::transaction()`:

1. Resolve each requested line against the live product, **snapshotting** name, price,
   cost and tax onto the `sale_items` row so later product edits never rewrite history.
2. Compute subtotal, per-line tax (inclusive or exclusive), discounts and totals.
3. Create the sale header and derive a deterministic `INV-000123` invoice number from
   the row id.
4. For each stock-tracked line, decrement inventory through the ledger. If any line has
   insufficient stock, an `InsufficientStockException` aborts the transaction and the
   **whole sale rolls back** — no partial inventory, no orphan sale.
5. Record payments and derive `payment_status` (paid / partial / due), change and dues.

> **Tested by** `SaleCheckoutTest` — stock decrement, insufficient-stock rollback, and
> idempotent replay.

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
one transaction: the tenant (with a 14-day trial), its first business, a default branch
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
