# API Reference

Base URL: `/api/v1` · Auth: `Authorization: Bearer <token>` (Sanctum) ·
Content-Type: `application/json`.

Collection endpoints are paginated (`?page=`, `?per_page=`) and return Laravel's
standard `{ data, links, meta }` envelope. Single resources return `{ data: {...} }`.

## Rate limiting

Every route is throttled (`throttle:api`, 120 req/min per token or IP). Report and PDF
endpoints (`/reports/*`, `/sales/{sale}/invoice`, `/cash-register-sessions/{session}/slip`)
carry a tighter `throttle:reports` ceiling (20 req/min). `POST /auth/login` and
`POST /auth/register` are throttled per-account **and** per-IP (`throttle:login`: 5/min by
email, 20/min by IP), so credential stuffing against one account is throttled even when
spread across many source IPs. Exceeding a limit returns `429` with a `Retry-After` header.

## Authentication

### `POST /auth/register`
Creates a new business (tenant + business + branch + warehouse + owner) and returns a token.

```json
{
  "name": "Jane Owner",
  "email": "jane@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "business_name": "Jane Mart",
  "business_type": "retail",
  "currency": "USD"
}
```
`201` → `{ "user": { ... }, "token": "..." }`

### `POST /auth/login`
```json
{ "email": "jane@example.com", "password": "password123", "device_name": "web" }
```
`200` → `{ "user": { ... }, "token": "..." }` · `422` on bad credentials.

### `GET /auth/me`
Returns the current user with `roles` and resolved `permissions`.

### `POST /auth/logout`
Revokes the current access token.

## Dashboard

### `GET /dashboard/summary?from=&to=`
Requires `dashboard.view`. Returns headline KPIs (today's sales, transactions, dues,
product/customer counts, low-stock count) and a 14-day sales trend.

## Categories

| Method & path | Permission | Notes |
| --- | --- | --- |
| `GET /categories` | `products.view` | `?search=&is_active=` |
| `POST /categories` | `products.create` | |
| `PUT /categories/{category}` | `products.update` | |
| `DELETE /categories/{category}` | `products.delete` | soft delete |

## Products

| Method & path | Permission | Notes |
| --- | --- | --- |
| `GET /products` | `products.view` | `?search=&category_id=&brand_id=&is_active=&low_stock=&sort=&direction=` — includes live `stock` |
| `GET /products/{product}` | `products.view` | |
| `POST /products` | `products.create` | optional `opening_stock: { warehouse_id, quantity }` |
| `PUT /products/{product}` | `products.update` | |
| `DELETE /products/{product}` | `products.delete` | soft delete |

Create example:
```json
{
  "name": "Cola 500ml",
  "category_id": 3,
  "purchase_price": 5,
  "selling_price": 8,
  "tax_rate": 10,
  "opening_stock": { "warehouse_id": 1, "quantity": 50 }
}
```

## Inventory

### `GET /inventory/balances`
Requires `inventory.view`. `?warehouse_id=&search=&low_stock=`. Returns balances with
`available_quantity` and `average_cost`.

### `POST /inventory/adjust`
Requires `inventory.update`. Ledger-backed manual adjustment.
```json
{ "product_id": 1, "warehouse_id": 1, "direction": "in", "quantity": 10, "unit_cost": 5, "note": "Recount" }
```
`201` → `{ "message": "Stock adjusted.", "quantity_after": 60 }`

## Customers

| Method & path | Permission |
| --- | --- |
| `GET /customers` (`?search=&has_due=`) | `customers.view` |
| `GET /customers/{customer}` | `customers.view` |
| `POST /customers` | `customers.create` |
| `PUT /customers/{customer}` | `customers.update` |
| `DELETE /customers/{customer}` | `customers.delete` |

## Sales / POS

### `POST /sales`
Requires `pos.create`. **Honours the `Idempotency-Key` header.** Creates the sale,
decrements stock through the ledger, and records payments — all in one transaction.

Every line is priced from the product's catalog `selling_price` on the server; a
client-supplied `unit_price` (that differs from the catalog price) or a `discount_amount`
(line- or sale-level) is only honoured when the cashier holds `pos.price_override` /
`pos.discount` respectively — otherwise the request `403`s and no sale is created. An
honoured override writes an `audit_logs` row (`sale.price_override`). Sale-level
`discount_amount` is also capped at the sale subtotal (`422` if it isn't). An optional
`coupon_code` is re-validated and redeemed server-side **inside the same transaction**
(active, date window, min-spend, `usage_limit`) — the discount is computed from the coupon,
never trusted from the client, and `used_count` is incremented under a row lock so a coupon
can never be over-redeemed by concurrent checkouts. `POST coupons/validate` remains
available as a pre-checkout preview only.

```json
{
  "branch_id": 1,
  "warehouse_id": 1,
  "customer_id": null,
  "discount_amount": 0,
  "shipping_amount": 0,
  "coupon_code": "SAVE10",
  "items": [
    { "product_id": 1, "quantity": 2, "unit_price": 8, "discount_amount": 0 }
  ],
  "payments": [
    { "method": "cash", "amount": 17.6 }
  ]
}
```
`201` → the created sale (`invoice_number`, totals, `coupon_code`, `coupon_discount`, items,
payments, `payment_status`).
`403` when `unit_price`/`discount_amount` is overridden without the required permission.
`422` with a `discount_amount` validation error when the sale-level discount exceeds the
subtotal, a `coupon_code` error when the coupon is invalid/expired/exhausted, or with an
`error` object when a line has insufficient stock:
```json
{ "message": "One or more items do not have enough stock.",
  "error": { "product_id": 1, "warehouse_id": 1, "available": 2, "requested": 5 } }
```

### `GET /sales`
Requires `sales.view`. `?search=&branch_id=&customer_id=&status=&payment_status=&from=&to=`.

### `GET /sales/{sale}`
Requires `sales.view`. Returns the sale with items, payments, customer and cashier.

### `GET /sales/{sale}/invoice`
Requires `sales.view`. Renders the sale as a PDF invoice (`application/pdf`). Streamed inline
by default so it opens in the browser's PDF viewer; add `?download=1` to force a
`Content-Disposition: attachment` download instead.

## Errors

Standard Laravel JSON errors: `401` unauthenticated, `403` missing tenant/permission,
`404` not found (also for cross-tenant access), `422` validation with
`{ "message", "errors": { field: [ ... ] } }`.

---

# Modules added after the foundation

## Brands · Units

| Method & path | Permission |
| --- | --- |
| `GET/POST /brands`, `PUT/DELETE /brands/{brand}` | `products.*` |
| `GET/POST /units`, `PUT/DELETE /units/{unit}` | `products.*` |

## Suppliers

| Method & path | Permission |
| --- | --- |
| `GET /suppliers` (`?search=&has_due=`) | `suppliers.view` |
| `GET /suppliers/{supplier}` | `suppliers.view` |
| `POST /suppliers` · `PUT /suppliers/{supplier}` · `DELETE /suppliers/{supplier}` | `suppliers.create/update/delete` |

## Purchases

### `POST /purchases`
Requires `purchases.create`. Idempotency-Key aware. When `status = received`, each line
increments stock through the ledger, refreshes the product's cost, and increases the
supplier's payable balance by any unpaid amount.
```json
{
  "warehouse_id": 1, "supplier_id": 2, "status": "received",
  "items": [{ "product_id": 1, "quantity": 100, "unit_cost": 2.5 }],
  "payments": [{ "method": "cash", "amount": 100 }]
}
```
`GET /purchases` (`?search=&supplier_id=&status=&payment_status=`) · `GET /purchases/{purchase}` — `purchases.view`.

## Expenses

| Method & path | Permission |
| --- | --- |
| `GET/POST /expense-categories` | `expenses.view/create` |
| `GET /expenses` (`?search=&expense_category_id=&branch_id=&status=&from=&to=`) | `expenses.view` |
| `POST /expenses` · `PUT /expenses/{expense}` | `expenses.create/update` |
| `POST /expenses/{expense}/approve` | `expenses.approve` |
| `DELETE /expenses/{expense}` | `expenses.delete` |

## Sale returns / refunds

### `POST /sales/{sale}/returns`
Requires `sales.refund`. Returned quantities flow back into stock via the ledger; the
refund reduces any outstanding customer due first. Over-returning beyond the sold (net of
prior returns) quantity is rejected with `422`.
```json
{ "items": [{ "sale_item_id": 10, "quantity": 2 }], "refund_method": "cash" }
```
`GET /sale-returns` (`?sale_id=`) · `GET /sale-returns/{saleReturn}` — `sales.view`.

## Reports

All require `reports.view`; all accept `?from=&to=` (and `branch_id` where relevant).

| Path | Returns |
| --- | --- |
| `GET /reports/sales-summary` | invoices, gross/net sales, discounts, tax, paid, due |
| `GET /reports/sales-by-product` | top products by quantity, revenue and profit |
| `GET /reports/profit` | revenue, COGS, gross profit, margin % |
| `GET /reports/inventory-valuation` | stock value (qty × avg cost) per warehouse |
| `GET /reports/low-stock` | products at/below minimum stock level |
| `GET /reports/dues` | customer receivables & supplier payables |

## Accounting (chart of accounts + double-entry journals)

| Method & path | Permission | Notes |
| --- | --- | --- |
| `GET /accounts` (`?type=&search=`) · `POST /accounts` · `PUT /accounts/{account}` | `accounting.*` | account types: cash, bank, mobile_banking, wallet, income, expense, asset, liability, equity |
| `POST /journals` | `accounting.create` | posts a **balanced** entry; unbalanced debits≠credits → `422` |
| `GET /journals` (`?from=&to=`) · `GET /journals/{journal}` | `accounting.view` | |
| `GET /journals/trial-balance` | `accounting.view` | balance per account |
| `GET /accounts/{account}/ledger` (`?from=&to=`) | `accounting.view` | per-account general ledger |

Journal posting adjusts balances by each account's normal side (debit-normal: assets & expenses; credit-normal: liabilities, income, equity):
```json
{
  "description": "Cash sale",
  "lines": [
    { "account_id": 1, "type": "debit",  "amount": 100 },
    { "account_id": 2, "type": "credit", "amount": 100 }
  ]
}
```

## Cash register sessions

| Method & path | Permission | Notes |
| --- | --- | --- |
| `GET /cash-registers` · `POST /cash-registers` | `accounting.*` | registers per branch |
| `POST /cash-registers/{cashRegister}/open` | `accounting.create` | body `{ opening_cash }`; one open session per register |
| `GET /cash-registers/{cashRegister}/current-session` | `accounting.view` | `{ data: session \| null }` — the register's open session, if any |
| `POST /cash-register-sessions/{session}/movement` | `accounting.create` | body `{ type: in\|out, amount, reason }` |
| `POST /cash-register-sessions/{session}/close` | `accounting.create` | body `{ actual_cash, note }`; computes `expected_cash` and `difference` |
| `GET /cash-register-sessions/{session}` | `accounting.view` | session + transactions |
| `GET /cash-register-sessions/{session}/slip` | `accounting.view` | PDF cash-drawer slip (`application/pdf`); streamed inline, `?download=1` to force download. Works for open sessions (X-report) and closed ones (Z-report). |

## Team — users, roles & permissions

| Method & path | Permission | Notes |
| --- | --- | --- |
| `GET /users` (`?search=&branch_id=&status=`) · `GET /users/{user}` | `users.view` | |
| `POST /users` | `users.create` | invites a member (`roles: []`); no password → `status: invited` |
| `PUT /users/{user}` | `users.update` | update profile, status, branch and roles. `403`s: changing your own roles; granting/revoking `owner` unless you hold it (or are super-admin); editing a user outside your branch when your own role assignment is branch-scoped. Role-assignment changes are audited (`user.roles_changed`). |
| `DELETE /users/{user}` | `users.delete` | cannot delete self or the owner |
| `GET /roles` · `POST /roles` · `PUT /roles/{role}` · `DELETE /roles/{role}` | `roles.*` | roles carry a `permissions` slug list; system roles are protected. `PUT` on the `owner` role `403`s unless the caller holds `owner` (or is super-admin). |
| `GET /permissions` | `roles.view` | full permission catalog grouped by module |

## Audit log

| Method & path | Permission | Notes |
| --- | --- | --- |
| `GET /audit-logs` (`?actor=&action=&model=&from=&to=&per_page=`) | `audit.view` | immutable — no update/delete endpoint. `action` matches with `LIKE %term%`; `model` matches the full auditable class name. |

Every `Sale`, `SaleReturn`, `Purchase`, `Expense`, `JournalEntry`, `User`, `Role`, `Business`
and `Coupon` create/update/delete is written automatically (via the `Auditable` trait);
non-CRUD security events (`sale.price_override`, `auth.login`, `auth.login_failed`, …) are
logged explicitly through `AuditLogService`. Rows carry `old_values`/`new_values`,
`ip_address`, `user_agent` and the acting user.

## Notifications

| Method & path | Notes |
| --- | --- |
| `GET /notifications` (`?unread=`) | user-addressed + tenant-broadcast (null user) |
| `GET /notifications/unread-count` | `{ unread: n }` |
| `POST /notifications/{notification}/read` · `POST /notifications/read-all` | mark read |

## Settings

| Method & path | Permission |
| --- | --- |
| `GET /settings/business` · `PUT /settings/business` | `settings.view` / `settings.update` |
| `POST /settings/onboarding/complete` | `settings.update` |

## Loyalty points

| Method & path | Permission | Notes |
| --- | --- | --- |
| `GET /customers/{customer}/loyalty` | `loyalty.view` | balance + recent history |
| `POST /customers/{customer}/loyalty/redeem` | `loyalty.manage` | `{ points }`; rejects redeeming beyond balance |
| `POST /customers/{customer}/loyalty/adjust` | `loyalty.manage` | `{ points }` (signed) |

Points are also **earned automatically** on completed sales when the business sets a
`loyalty_earn_rate` (points per currency unit) in `settings/business` — off by default.

## Coupons

| Method & path | Permission | Notes |
| --- | --- | --- |
| `GET /coupons` · `POST /coupons` · `PUT /coupons/{coupon}` · `DELETE /coupons/{coupon}` | `coupons.*` | percentage or fixed; min-purchase, max-discount, usage limit, date window |
| `POST /coupons/validate` | `pos.create` | `{ code, cart_total }` → `{ valid, discount }` or `{ valid:false, reason }` |

## Subscription & plan limits

| Method & path | Permission | Notes |
| --- | --- | --- |
| `GET /subscription/plans` | `subscriptions.view` | active plan catalogue (Free → Enterprise) |
| `GET /subscription/current` | `subscriptions.view` | current subscription + live usage-vs-limits |

Plan limits are enforced on creation: e.g. `POST /products` and `POST /users` return
`422 { errors: { plan: [...] } }` when the tenant's plan limit is reached. A `null` limit
means unlimited.

## Stock transfers (T28)

| Method & path | Permission | Notes |
| --- | --- | --- |
| `GET /stock-transfers` | `inventory.view` | list with `?status=pending\|approved\|in_transit\|received\|cancelled` |
| `POST /stock-transfers` | `inventory.adjust` | create pending transfer |
| `GET /stock-transfers/{id}` | `inventory.view` | detail with items |
| `POST /stock-transfers/{id}/approve` | `inventory.adjust` | approve → moves stock out of source |
| `POST /stock-transfers/{id}/receive` | `inventory.adjust` | receive → moves stock into destination |
| `POST /stock-transfers/{id}/cancel` | `inventory.adjust` | cancel (pending only) |

**Create transfer body:**
```json
{
  "from_warehouse_id": 1,
  "to_warehouse_id": 2,
  "note": "Monthly replenishment",
  "items": [
    { "product_id": 12, "quantity": 50, "unit_cost": 8.50 }
  ]
}
```

## Product variants, batches & serials (T29)

| Method & path | Permission | Notes |
| --- | --- | --- |
| `GET /products/{id}/variants` | `inventory.view` | list variants with attribute values |
| `GET /stock/batches` | `inventory.view` | batch list with `?product_id=&warehouse_id=&expiring_before=` |
| `POST /stock/batches` | `inventory.adjust` | create batch record |
| `GET /stock/serials` | `inventory.view` | serial list with `?product_id=&status=in_stock\|sold\|returned\|damaged` |
| `POST /stock/serials` | `inventory.adjust` | register new serial number |

## Quotations (T30)

| Method & path | Permission | Notes |
| --- | --- | --- |
| `GET /quotations` | `sales.view` | paginated, `?status=draft\|sent\|accepted\|converted` |
| `POST /quotations` | `sales.create` | create draft quotation |
| `GET /quotations/{id}` | `sales.view` | detail with items, customer |
| `PUT /quotations/{id}` | `sales.update` | update status / note / terms |
| `DELETE /quotations/{id}` | `sales.delete` | soft-delete |
| `POST /quotations/{id}/convert` | `pos.create` | convert to confirmed sale |

**Create quotation body:**
```json
{
  "branch_id": 1,
  "customer_id": 5,
  "valid_until": "2026-08-31",
  "items": [
    { "product_id": 3, "quantity": 2, "unit_price": 29.99 }
  ]
}
```

**Convert to sale body:**
```json
{
  "warehouse_id": 1,
  "payments": [{ "method": "cash", "amount": 59.98 }]
}
```

## Super-admin API (T31–T33)

Base path: `/api/v1/super` · Requires: `is_super_admin = true` on the authenticated user.

| Method & path | Notes |
| --- | --- |
| `GET /super/tenants` | list all tenants with user/business counts |
| `GET /super/tenants/{id}` | detail + subscription + live usage |
| `POST /super/tenants/{id}/suspend` | set status = suspended, deletes every live token for the tenant's users, audited |
| `POST /super/tenants/{id}/activate` | set status = active, audited |
| `DELETE /super/tenants/{id}` | soft-delete tenant |
| `GET /super/metrics/revenue` | MRR, plan distribution, recent subscriptions |
| `POST /super/tenants/{id}/impersonate` | issues a 24h scoped token for the tenant's owner |
| `POST /super/impersonation/stop` | revokes the current impersonation token |

**Impersonation flow:**
1. `POST /super/tenants/42/impersonate` → `{ token, expires_at }`
2. Use the returned token as `Authorization: Bearer <token>` for subsequent requests
3. `POST /super/impersonation/stop` to revoke when done

**Tenant suspension:** every tenant-scoped route (guarded by the `ensure.tenant` middleware)
403s with `{ "code": "tenant_suspended" }` once a tenant's status leaves `trialing`/`active`,
and `POST /auth/login` refuses the same tenant's users with the same `403`/code. The
frontend shows a full-screen "account suspended" block on that code.

## Real-time events (T34 — Laravel Reverb)

Subscribe to private channels with the Sanctum token:

| Channel | Event | Payload |
| --- | --- | --- |
| `private-tenant.{tenant_id}` | `sale.completed` | `{ id, total, payment_status, sold_at }` |
| `private-tenant.{tenant_id}` | `inventory.low` | `{ product_id, warehouse_id, quantity }` |
| `private-branch.{branch_id}` | `sale.completed` | same as above |

**Laravel Echo setup (JavaScript):**
```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
window.Pusher = Pusher;

const echo = new Echo({
  broadcaster: 'reverb',
  key: process.env.NEXT_PUBLIC_REVERB_APP_KEY,
  wsHost: process.env.NEXT_PUBLIC_REVERB_HOST,
  wsPort: 443,
  wssPort: 443,
  forceTLS: true,
  authEndpoint: `${process.env.NEXT_PUBLIC_API_URL}/broadcasting/auth`,
  auth: { headers: { Authorization: `Bearer ${token}` } },
});

echo.private(`tenant.${tenantId}`)
  .listen('.sale.completed', (data) => console.log('New sale', data))
  .listen('.inventory.low', (data) => console.warn('Low stock', data));
```

## Scheduled tasks (T36)

| Schedule | Task |
| --- | --- |
| Daily 07:00 UTC | Send `DailyReportMail` to every active tenant's owner |
| Hourly | Auto-expire trialing tenants whose `trial_ends_at` has passed |

Enable via cron: `* * * * * php /var/www/smartpos/artisan schedule:run >> /dev/null 2>&1`
