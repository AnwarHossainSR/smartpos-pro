# API Reference

Base URL: `/api/v1` · Auth: `Authorization: Bearer <token>` (Sanctum) ·
Content-Type: `application/json`.

Collection endpoints are paginated (`?page=`, `?per_page=`) and return Laravel's
standard `{ data, links, meta }` envelope. Single resources return `{ data: {...} }`.

`per_page` (and the `limit` on "top N" endpoints) is **capped at 200**; a larger value is
clamped, and a zero/negative one falls back to the endpoint's default. Endpoints taking a
`from`/`to` day filter treat both bounds as inclusive; an unparseable date returns `422`
rather than being ignored. Report endpoints additionally reject a range longer than 366 days.

## Idempotency, CORS & security headers

`Idempotency-Key` is scoped to `tenant + key + method + path` — the same key reused on a
different route no longer replays a stranger's cached response, and reusing it on the same
route with a **different** request body returns `409` instead of silently serving the old
result. Keys are pruned after 24h by a scheduled job.

The key row is also a mutex: it is written *before* the handler runs, so a retry that
arrives while the original request is still in flight gets `409`
(`Retry-After: 60`) rather than executing the operation a second time. A claim left behind
by a request that died is reclaimable after 60s, and a `5xx` releases it immediately so the
client can retry at once.

CORS (`config/cors.php`) is restricted to `FRONTEND_URL` (comma-separated for multiple
origins) rather than the framework default of `*`. Every API response carries baseline
security headers (`X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`,
`Permissions-Policy`, a restrictive `Content-Security-Policy`, and HSTS over HTTPS) — defense
in depth alongside the reverse-proxy config in `docs/DEPLOYMENT.md`.

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
`200` → `{ "user": { ... }, "token": "..." }` for an account without 2FA · `422` on bad
credentials · `403` with `{ "code": "tenant_suspended" }` for a suspended tenant · `422`
"Too many failed attempts…" once the account is locked out (see below).

For an account with two-factor authentication enabled, this returns `200` with
`{ "requires_2fa": true, "challenge_token": "...", "expires_in": 300 }` instead — **no
token is issued yet**. Complete the login with:

### `POST /auth/2fa/challenge`
Public, `throttle:login`. `{ "challenge_token", "code" }` (6-digit TOTP) **or**
`{ "challenge_token", "recovery_code" }` (single-use). `200` → the same
`{ "user", "token" }` shape as a normal login. `422` on an invalid/expired challenge token,
wrong code, or an already-used recovery code. The challenge token expires after 5 minutes.

**Progressive account lockout:** after 5 failed login (or 2FA challenge) attempts for the
same account, it locks out for a duration that doubles each time the threshold is hit again
(1, 2, 4, … minutes, capped at 60) — independent of the per-email/per-IP `throttle:login`
rate limiter, which still applies on top. `login`/`2fa/challenge` return `422` with a
"too many failed attempts" message while locked, even with the correct password/code.

Tokens expire after `SANCTUM_TOKEN_EXPIRATION` minutes (default 14 days; expired tokens are
swept daily by `sanctum:prune-expired`) and are issued with **abilities scoped to the user's
current permissions** (`['*']` only for the owner/super-admin) rather than a blanket
wildcard.

### `GET /auth/me`
Returns the current user with `roles` and resolved `permissions`.

### `POST /auth/logout`
Revokes the current access token.

### `GET /auth/sessions`
Lists every active device/session (personal access token) for the current user: `name`
(device name at login), `ip_address`, `last_used_at`, `created_at`, `expires_at`, and
`current` (whether it's the token making this request).

### `DELETE /auth/sessions/{tokenId}`
Revokes one session. `404` if the token doesn't belong to the current user.

### `POST /auth/sessions/revoke-all`
Revokes every session **except** the one making the request.

### Two-factor authentication (TOTP)

| Method & path | Notes |
| --- | --- |
| `POST /auth/2fa/enable` | Generates a pending (unconfirmed) secret. `422` if already enabled. Returns `{ secret, otpauth_url }` — the frontend renders `otpauth_url` as a QR code. |
| `POST /auth/2fa/confirm` | `{ code }`. Verifies the pending secret and confirms it, returning `{ recovery_codes: string[] }` (8 codes, **shown once**, stored hashed). |
| `POST /auth/2fa/disable` | `{ password }`. Clears the secret, recovery codes, and confirmation. |
| `POST /auth/2fa/recovery-codes/regenerate` | `{ password }`. Invalidates existing recovery codes and issues a fresh set. |

Codes are standard 30-second, 6-digit, SHA-1 TOTP (RFC 6238) — compatible with Google
Authenticator, Authy, 1Password, etc. A tenant can require 2FA for every user by setting
`settings.require_2fa: true` via `PUT /settings/business`; `GET /auth/me` and the login
response then include `requires_two_factor_setup: true` for users who haven't enrolled yet
(this is advisory — it does not itself block API access).

### `POST /auth/forgot-password`
Public, `throttle:login`. `{ "email": "..." }` → always `200` with the same generic message
regardless of whether the email is registered (no account-existence leak). Sends a
`ResetPassword` notification (log mailer in dev) whose link points at the **frontend**
(`FRONTEND_URL/reset-password?token=&email=`) — the SPA collects the new password and posts
it back.

### `POST /auth/reset-password`
Public, `throttle:login`. `{ "token", "email", "password", "password_confirmation" }`.
Tokens are single-use and expire after 60 minutes (`config/auth.php`). `200` on success —
also revokes every existing session for that user. `422` (on `email`) for an invalid/expired
token.

### `GET /auth/email/verify/{id}/{hash}`
The link emailed by `MustVerifyEmail` (`signed` middleware — not authenticated, since the
browser clicking the email link has no bearer token). Marks the email verified and redirects
to `FRONTEND_URL/login?verified=1`. `403` for an invalid/expired/tampered link.

### `POST /auth/email/resend`
Authenticated, `throttle:login`. Resends the verification email unless already verified.

**Billing-sensitive gate:** `GET subscription/current` (and its `/subscription` alias) and
`GET subscription/usage` require a verified email (`verified` middleware) — an unverified
owner gets `403`. The public plan catalogue (`GET subscription/plans`) does not require it.

**Invited team members** (`POST /users` with no `password`) no longer get an undelivered
random password — they receive a "set your password" email (same mechanism as password
reset, just different copy) and their account stays `status: invited` until they use it.

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
| `GET /products` | `products.view` | `?search=&category_id=&brand_id=&is_active=&low_stock=&sort=&direction=` — includes live `stock`. `sort` is whitelisted (`name`, `sku`, `selling_price`, `purchase_price`, `created_at`); anything else `422`s. |
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
| `GET /customers` (`?search=&has_due=&sort=&direction=`) | `customers.view` | `sort` whitelisted: `name`, `phone`, `email`, `balance`, `created_at` |
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
**Invoice numbering.** A completed sale is stamped with a per-tenant **gapless** number
formatted `{prefix}{sequence}` (e.g. `INV-1`, `INV-2`). The counter lives on the tenant row,
is read and incremented under a row lock **inside the checkout transaction**, and is
therefore never advanced by a checkout that rolls back. `prefix` comes from
`settings.invoice_prefix` on the business (default `INV-`). A sale created with
`status` `held`, `draft` or `quotation` gets **`invoice_number: null`** — a parked cart
never burns a number; it is stamped when the cart is resumed. Numbering is per tenant, so
each tenant's first invoice is `1`.

`201` → the created sale (`invoice_number` — `null` unless the sale was completed, totals,
`coupon_code`, `coupon_discount`, items, payments, `payment_status`).
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
`Content-Disposition: attachment` download instead. Prints tax per component (e.g. CGST and
SGST as separate lines, not one blended figure), the tax profile's own tax-number label, the
customer's tax number or exemption reason, and the profile's required invoice fields.

Every sale item in every response above also carries its per-component tax breakdown:
```json
{ "tax_amount": 18, "tax_rate": 18, "taxes": [
  { "name": "CGST", "rate": 9, "amount": 9 }, { "name": "SGST", "rate": 9, "amount": 9 }
] }
```

### `POST /sales/{sale}/resume`
Requires `pos.create`. Idempotency-Key aware. Finalises a **held or draft** cart: moves the
stock it deliberately deferred and records any tender taken now, then completes the sale.
Pricing and tax are **not** recomputed — both were already snapshotted when the cart was
held, so a price or rate change while a cart sat held cannot change what the customer was
quoted. The sale's `invoice_number` is issued **here**, at completion, through the same
per-tenant gapless counter a straight checkout uses. Body:
`{ "payments": [{ "method": "cash", "amount": 100 }] }` (optional — a sale can
be resumed with no payment, leaving it fully due). `422` if the sale isn't held/draft.

### `POST /sales/{sale}/void`
Requires `sales.delete`. Cancels a **completed** sale and reverses the stock it moved. Does
**not** auto-refund cash already collected — voiding corrects a mistaken sale; refunding is
`POST /sales/{sale}/returns` below. Body: `{ "reason": "optional string" }`. `422` if the
sale isn't completed.

### `GET /pos/bootstrap`
Requires `pos.view`. One call for everything the terminal needs to open a register:
```json
{ "branch": { "id": 1, "name": "Main" }, "warehouse": { "id": 1, "name": "Main WH" },
  "cash_register": { "id": 1, "name": "Front till" }, "session": { "...": "or null" },
  "tax_profile": { "code": "BD_VAT", "...": "..." },
  "price_display": "inclusive", "currency": "BDT",
  "payment_methods": [{ "value": "cash", "label": "Cash" }, "..."] }
```
`?branch_id=` overrides the caller's own branch (e.g. a multi-branch manager).

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
| `GET /suppliers` (`?search=&has_due=&sort=&direction=`) | `suppliers.view` | `sort` whitelisted: `name`, `company`, `phone`, `balance`, `created_at` |
| `GET /suppliers/{supplier}` | `suppliers.view` |
| `POST /suppliers` · `PUT /suppliers/{supplier}` · `DELETE /suppliers/{supplier}` | `suppliers.create/update/delete` |

## Purchases

### `POST /purchases`
Requires `purchases.create`. Idempotency-Key aware. When `status = received`, each line
increments stock through the ledger, refreshes the product's cost, and increases the
supplier's payable balance by any unpaid amount. **Input tax is resolved server-side** from
each product's tax group (always against the net cost, regardless of how this tenant
displays *sale* prices) — a client-supplied `tax_amount` is not accepted.
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
prior returns) quantity is rejected with `422`. Tax is **reversed proportionally from the
original sale line's snapshotted components**, never recomputed — a tax rate changed after
the sale cannot change what a return refunds.
```json
{ "items": [{ "sale_item_id": 10, "quantity": 2 }], "refund_method": "cash" }
```
Each returned item carries the same per-component breakdown: `"taxes": [{ "name": "CGST",
"rate": 9, "amount": 9 }, "..."]`.

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

`GET /reports/tax-summary` and `GET /reports/tax-return` require `taxes.view` instead (not
`reports.view`) — see [Tax administration](#tax-administration) below.

## Tax administration

Global jurisdiction rules and a tenant's own rates/groups/zones. `taxes.view` for reads,
`taxes.manage` for writes.

| Method & path | Notes |
| --- | --- |
| `GET/POST /tax-rates` · `PUT/DELETE /tax-rates/{taxRate}` | one component: `{ name, code, rate, type: percentage\|fixed, is_compound, is_active }` |
| `GET/POST /tax-groups` · `PUT/DELETE /tax-groups/{taxGroup}` | an ordered set of components — what actually attaches to a product/category. Body: `{ name, rates: [{ tax_rate_id, priority }] }`; `PUT` **syncs** the set (re-ordering or dropping a component), it doesn't append |
| `GET/POST /tax-zones` · `PUT/DELETE /tax-zones/{taxZone}` | a jurisdiction match rule (`country_code`/`state`/`postcode_pattern`) pointing at a default `tax_group_id`; matched against the branch (origin sourcing) or customer (destination sourcing) per the active profile |
| `GET /tax-profiles` · `GET /tax-profiles/{taxProfile}` | **read-only** global catalogue (not tenant-owned): `GENERIC`, `BD_VAT`, `IN_GST`, `EU_VAT`, `US_SALES_TAX`. Each carries `sourcing`, `rounding_scope` (`line`\|`component`\|`invoice`), `rounding_mode`, `price_display`, `tax_number_label`, `required_invoice_fields` |
| `GET /reports/tax-summary` (`?from=&to=&branch_id=`) | output tax (charged on sales) vs. input tax (paid on purchases) by component, and `net_payable`. Read from the snapshotted `*_item_taxes` rows, not recomputed |
| `GET /reports/tax-return` (`?from=&to=&branch_id=`) | the same figures shaped for filing: the business's tax number under the active profile's own label, plus the profile's required invoice fields |

A product or category selects a `tax_group_id` (`GET/POST/PUT /products`, `/categories`);
resolution falls back product → category → zone default, and a product with no group
anywhere is untaxed — a real answer, not a missing one. `GET /settings/business` returns
`tax_profile_id`, `tax_profile` (full object or `null`), and `price_display`
(`inclusive`\|`exclusive`); `PUT /settings/business` accepts both.

## Accounting (chart of accounts + double-entry journals)

| Method & path | Permission | Notes |
| --- | --- | --- |
| `GET /accounts` (`?type=&search=`) · `POST /accounts` · `PUT /accounts/{account}` | `accounting.*` | account types: cash, bank, mobile_banking, wallet, income, expense, asset, liability, equity. Each account carries a derived `normal_side` (`debit`/`credit`) — the side its balance increases on |
| `POST /journals` | `accounting.create` | posts a **balanced** entry; unbalanced debits≠credits → `422` |
| `GET /journals` (`?from=&to=`) · `GET /journals/{journal}` | `accounting.view` | |
| `GET /journals/trial-balance` | `accounting.view` | each account's balance in its normal-side column, plus `total_debit` / `total_credit` |
| `GET /accounts/{account}/ledger` (`?from=&to=&limit=`) | `accounting.view` | per-account general ledger, oldest first, capped at `limit` (default 200) — narrow with `from`/`to` |

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

`settings.invoice_prefix` (free-form string, default `INV-`) prefixes every invoice number
this business issues — see `POST /sales`. Changing it does not renumber past sales and does
not reset the counter.

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

`from_warehouse_id`/`to_warehouse_id`/`product_id`/`variant_id` are now validated against the
caller's own tenant (previously `exists:warehouses,id` etc. accepted any tenant's row).

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

`product_id`/`variant_id`/`warehouse_id` on batch and serial creation are now tenant-scoped
(same fix as T28). `GET /products/{id}/variants` no longer 500s — it eager-loaded a
relationship (`attributes.attribute`) that didn't exist on the model.

| Method & path | Permission | Notes |
| --- | --- | --- |
| `GET /products/{id}/variants` | `inventory.view` | list variants with attribute values |
| `GET /stock/batches` | `inventory.view` | batch list with `?product_id=&warehouse_id=&expiring_before=` |
| `POST /stock/batches` | `inventory.adjust` | create batch record |
| `GET /stock/serials` | `inventory.view` | serial list with `?product_id=&status=in_stock\|sold\|returned\|damaged` |
| `POST /stock/serials` | `inventory.adjust` | register new serial number |

## Quotations (T30)

`branch_id`/`customer_id`/`items.*.product_id`/`warehouse_id` (on convert) are now
tenant-scoped (same fix as T28).

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
| `POST /super/tenants/{id}/impersonate` | body: `{ reason }` (required, min 10 chars, `422` otherwise). Issues a 30-minute token scoped to a single `impersonate` ability for the tenant's owner. Notifies the owner (in-app + email) and audits the start. |
| `POST /super/impersonation/stop` | **not** super-admin-gated — called with the impersonation token itself, from inside the impersonated session, to end it early |
| `POST /super/impersonation/{id}/revoke` | ends a live impersonation **from the super-admin's own session** — the super-admin no longer has to ask the impersonated session to call `stop` |
| `GET /super/impersonations` | every impersonation currently in progress (not yet ended), with tenant/admin/target-user info |

**Impersonation flow:**
1. `POST /super/tenants/42/impersonate` with `{ "reason": "..." }` → `{ impersonation_id, token, expires_at }`
2. Use the returned token as `Authorization: Bearer <token>` for subsequent requests — it expires
   after 30 minutes regardless
3. End it either from inside the session (`POST /super/impersonation/stop`, no `super_admin`
   gate — the token itself proves the session) or remotely from the super-admin's own session
   (`POST /super/impersonation/{id}/revoke`)
4. Both start and stop write an `audit_logs` row (`impersonation.started`/`impersonation.stopped`)

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

## Branches & warehouses (T100)

Full CRUD — previously branches had no management endpoints at all, and warehouses were
list-only (both only ever created by the registration bootstrap).

| Method & path | Permission | Notes |
| --- | --- | --- |
| `GET /branches` | `branches.view` | `?search=&is_active=` |
| `GET /branches/{id}` | `branches.view` | |
| `POST /branches` | `branches.create` | |
| `PUT /branches/{id}` | `branches.update` | |
| `DELETE /branches/{id}` | `branches.delete` | `422` if it's the default branch, still has warehouses, has assigned users, or has sales history |
| `GET /warehouses` | `warehouses.view` | active warehouses (unchanged shape) |
| `GET /warehouses/{id}` | `warehouses.view` | |
| `POST /warehouses` | `warehouses.create` | `branch_id` optional |
| `PUT /warehouses/{id}` | `warehouses.update` | |
| `DELETE /warehouses/{id}` | `warehouses.delete` | `422` if it's the default warehouse, still holds stock, or has sales history |

## Credit-sale settlement (T101)

`Sale.due_amount` could only ever go up (set once at checkout) — there was no way to record
a customer paying down a due/partial sale afterwards.

### `POST /sales/{sale}/payments`
Requires `sales.update`. Honours the `Idempotency-Key` header. Records a payment against an
existing **completed** sale, row-locked so two concurrent payments can never together exceed
the outstanding balance.
```json
{ "method": "cash", "amount": 30, "reference": null }
```
`200` → the updated sale (`paid_amount`, `due_amount`, `payment_status`). If the sale has an
attached customer, their `balance` (accounts-receivable total) is decremented by the same
amount. `422` when the sale isn't completed, has no outstanding balance, or the amount
exceeds it.

### `GET /products/barcode/{barcode}`
Requires `products.view`. POS barcode-scanner lookup — resolves a single product by its
exact barcode (`404` if not found). Previously the only way to find a product by barcode was
the general `?search=` filter on `GET /products`.

## Stock ledger history (T102)

The `stock_movements` ledger — the architectural centerpiece described in
[`ARCHITECTURE.md`](ARCHITECTURE.md) — was write-only via the API (only current balances and
the adjustment endpoint were exposed).

### `GET /inventory/movements`
Requires `inventory.view`. `?product_id=&warehouse_id=&variant_id=&type=&direction=&from=&to=`,
paginated, newest first. Returns each movement's `type`, `direction`, `quantity`,
`quantity_after` (the running balance at that point), `unit_cost`, `reference_type`/
`reference_id` (e.g. the sale or transfer that caused it), and the acting user.
