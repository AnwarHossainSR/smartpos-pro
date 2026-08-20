# SmartPOS Pro

A modern, multi-tenant **cloud POS & retail management platform** for retail shops,
supermarkets, pharmacies, electronics and fashion stores, restaurants, wholesalers
and multi-branch businesses.

This repository contains the **backend API** — a Laravel 13 / PHP 8.4 application built
around strict tenant isolation, an immutable stock ledger, and transaction-safe POS
checkout. It is the foundation of the product described in
[`docs/PROJECT_SPEC.md`](docs/PROJECT_SPEC.md); a Next.js frontend consumes this API.

> **Status:** Backend foundation — auth & tenancy, catalog, inventory ledger, and the
> POS checkout engine are implemented, tested and runnable. See
> [Roadmap](#roadmap) for what is intentionally left as next work.

---

## Highlights

- **Multi-tenant by construction.** Every business-owned row carries a `tenant_id`, and a
  global Eloquent scope transparently constrains every query to the current tenant — data
  cannot leak across tenants. See [`app/Support`](app/Support).
- **Immutable stock ledger.** All stock changes append to `stock_movements`; live
  balances are a locked, materialised projection. Oversell is prevented with
  `SELECT ... FOR UPDATE` row locking. See
  [`StockLedgerService`](app/Services/Inventory/StockLedgerService.php).
- **Transaction-safe POS checkout.** A sale — header, items, stock decrements and
  payments — is created in a single database transaction and rolls back atomically on
  any failure. See [`SaleService`](app/Services/Sales/SaleService.php).
- **Idempotent writes.** Sensitive operations (checkout) honour an `Idempotency-Key`
  header so retries never double-charge or double-decrement stock.
- **Role & permission system.** Global permission matrix (`module.action`) with
  tenant-scoped roles; enforced by `permission:` route middleware.
- **REST API** under `/api/v1`, Sanctum bearer-token auth, Form Request validation,
  and API Resources for consistent responses.

## Tech stack

| Layer        | Technology                                             |
| ------------ | ------------------------------------------------------ |
| Framework    | Laravel 13, PHP 8.4                                     |
| Auth         | Laravel Sanctum (token-based)                          |
| Database     | MySQL 8.4 (SQLite in-memory for the fast test job)     |
| Cache/Queue  | Redis                                                   |
| Tooling      | Pint (style), PHPStan/Larastan (static analysis), PHPUnit |
| Infra        | Docker Compose (app, nginx, mysql, redis, queue), GitHub Actions |

## Getting started

### With Docker (recommended)

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
# API now available at http://localhost:8080/api/v1
```

### Local (without Docker)

Requires PHP 8.4, Composer, and a MySQL 8.4 database (or use the bundled SQLite).

> The schema is designed against a case- and accent-sensitive collation
> (`utf8mb4_0900_as_cs`). MySQL's default folds both, which would quietly loosen
> every unique index on `sku`, `barcode`, and `email`. `docker-compose.yml` and
> `.env.example` already set it; a hand-rolled database needs it too.

```bash
composer install
cp .env.example .env
php artisan key:generate

# Point .env at your database, then:
php artisan migrate --seed
php artisan serve   # http://localhost:8000
```

The seeder provisions a full demo tenant:

```
Owner login:  owner@smartpos.test / password123
Staff logins: admin@ / branch_manager@ / cashier@ / accountant@ / viewer@smartpos.test
```

## Running the checks

```bash
composer test      # PHPUnit feature + unit suites
composer lint      # Laravel Pint (code style)
composer analyse   # PHPStan / Larastan static analysis
```

## API at a glance

All endpoints are prefixed with `/api/v1`. Authenticated routes require an
`Authorization: Bearer <token>` header. Full reference: [`docs/API.md`](docs/API.md).

```http
POST /api/v1/auth/register     # create business + owner, returns token
POST /api/v1/auth/login        # returns token
GET  /api/v1/auth/me           # current user + permissions

GET  /api/v1/products          # search / filter / paginate, includes live stock
POST /api/v1/products          # create (optionally with opening stock)
GET  /api/v1/inventory/balances
POST /api/v1/inventory/adjust  # ledger-backed stock adjustment

POST /api/v1/sales             # POS checkout (Idempotency-Key aware)
GET  /api/v1/sales             # sales history
GET  /api/v1/dashboard/summary # headline KPIs + sales trend
```

Example checkout:

```bash
curl -X POST http://localhost:8080/api/v1/sales \
  -H "Authorization: Bearer $TOKEN" \
  -H "Idempotency-Key: $(uuidgen)" \
  -H "Content-Type: application/json" \
  -d '{
    "branch_id": 1, "warehouse_id": 1,
    "items": [{ "product_id": 1, "quantity": 2 }],
    "payments": [{ "method": "cash", "amount": 20 }]
  }'
```

## Project structure

```
app/
  Http/
    Controllers/Api/V1/   REST controllers (auth, products, inventory, sales, ...)
    Middleware/           ResolveTenant, EnsureTenant, CheckPermission, Idempotency
    Requests/             Form Request validation
    Resources/            API Resources (response shaping)
  Models/                 Eloquent models (all tenant-scoped via BelongsToTenant)
  Services/
    Inventory/            StockLedgerService (immutable ledger + locking)
    Sales/                SaleService (transaction-safe checkout)
    Auth/                 TenantProvisioner (registration bootstrap)
  Support/
    Concerns/             BelongsToTenant, HasRoles traits
    Scopes/               TenantScope global scope
    Tenancy/              TenantContext
    Rbac/                 PermissionCatalog
database/
  migrations/             Full normalized schema
  seeders/                DemoSeeder (realistic demo tenant)
docs/                     Architecture, API and product spec
```

## Documentation

- [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) — tenancy, stock ledger, transaction
  safety, RBAC and idempotency in depth.
- [`docs/API.md`](docs/API.md) — endpoint reference.
- [`docs/PROJECT_SPEC.md`](docs/PROJECT_SPEC.md) — the full product vision this
  foundation is built toward.

## Task-driven development

Work is broken into small, module-wise tasks in
[`docs/REQUIREMENTS.md`](docs/REQUIREMENTS.md), with live status in
[`docs/PROGRESS.md`](docs/PROGRESS.md). Two Claude Code skills drive it:

- **`/implement-task <id>`** — implements one task end to end (code + tests, updates
  progress, commits to the dev branch, opens/updates the PR). E.g. `/implement-task 44`.
- **`/project-status`** — reports what's done vs. left and suggests the next tasks.

Conventions the skills follow live in [`CLAUDE.md`](CLAUDE.md).

## Roadmap

**Implemented:** auth & tenancy · catalog (categories, brands, units, products) ·
inventory ledger & adjustments · customers · suppliers · **purchases & goods receipt** ·
**expenses** · POS checkout · **sale returns & refunds** · **reports center**
(sales summary, sales-by-product, profit, inventory valuation, low stock, dues) · dashboard ·
**accounting** (chart of accounts + balanced double-entry journals, general ledger, trial balance) ·
**cash register sessions** (open/close reconciliation) ·
**team management** (users, roles & permissions) · **notifications** · **business settings** ·
**loyalty points** · **coupons/promotions** · **subscription plans & limit enforcement**.

**Next:** scheduled/exported reports · super-admin SaaS panel · real-time (Reverb)
updates · and the Next.js frontend + offline PWA.

## License

MIT.
