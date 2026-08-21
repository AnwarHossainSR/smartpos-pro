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
spread across many source IPs. `POST /pos/approvals` and the approval-PIN management routes
carry `throttle:approval-pin` (10/min per caller) on top of the service's own five-miss,
15-minute lockout. Exceeding a limit returns `429` with a `Retry-After` header.

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
Returns the current user with `roles` and resolved `permissions`, plus `theme` — the
user's saved UI preference, `{ "mode": "light|dark|system", "palette": "<id>" }`, or `null`
if they have never set one (the client then falls back to its own local default).

### `PATCH /auth/preferences`
Saves per-user UI preferences for the **caller** — there is no user id in the path and no
`permission:` guard, because the handler only ever writes `$request->user()`: being
authenticated is the authorisation, and a cashier must be able to theme their own screen
without an admin permission.

```json
{ "theme": { "mode": "dark", "palette": "emerald" } }
```
`200` → the same `{ "data": { ... } }` user envelope as `GET /auth/me`.

`mode` and `palette` are both required and validated against
`App\Support\Theme\ThemeCatalog` (which mirrors `web/src/stores/theme.ts` and
`web/src/lib/palettes.ts`); an unknown value, a missing key, or an unrecognised key inside
`theme` returns `422`. The whole `theme` object is replaced, not merged. The web client
PATCHes this on an 800 ms debounce, so cycling through palettes sends one request; the
change is deliberately **not** written to the audit log.

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

### `GET /auth/2fa/status`
Returns 2FA enabled status and recovery codes count: `{ data: { enabled, recovery_codes_remaining } }`.

### `POST /auth/change-password`
`{ current_password, new_password, new_password_confirmation }`. Validates the current password, updates it, and invalidates all other sessions.

### `GET /auth/lockout-status`
Returns the caller's lockout state: `{ data: { is_locked, locked_until, failed_attempts } }`.
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

## Customer order status (public)

### `GET /orders/{token}`
**Unauthenticated.** The 48-character `public_token` minted for every sale is the only
credential; rate limited to 30 requests/minute. Returns a trimmed, customer-safe view of one
completed or cancelled sale — invoice number, status, line items, totals and the seller's
contact details. No ids, costs, customer record or anything else tenant-internal. Anything
else (unknown token, a held or draft cart) is a 404.

```json
{ "data": {
  "invoice_number": "INV-000123", "status": "completed", "payment_status": "paid",
  "sold_at": "2026-08-19T11:12:17+00:00", "currency": "BDT",
  "subtotal": 200, "discount_amount": 0, "tax_amount": 0, "shipping_amount": 0,
  "total": 200, "paid_amount": 200, "due_amount": 0,
  "items": [{ "name": "Widget", "quantity": 2, "unit_price": 100, "line_total": 200 }],
  "seller": { "name": "Demo Store", "phone": null, "email": null, "branch": "Main" }
} }
```

## Email templates

Tenants may rewrite the emails their customers receive. A template is markdown with
`{variable}` placeholders — never Blade — and only the keys in `EmailTemplateCatalog` can be
overridden. Angle brackets are neutralised on render, so stored markup is inert.

### `GET /email-templates`
Requires `settings.view`. Returns every catalogued email with the tenant's override applied,
plus `default_subject`, `default_body`, the available `variables` and `is_customised`.

### `GET /email-templates/{key}` · `PUT /email-templates/{key}` · `DELETE /email-templates/{key}`
`view` to read, `settings.update` to write. `PUT` takes `{ "subject": "...", "body": "..." }`
and creates or replaces the override; `DELETE` drops it so the packaged default applies again.
An unknown key is a 404.

### `POST /email-templates/{key}/preview`
Requires `settings.view`. Renders an unsaved `{subject, body}` draft against sample values and
returns `{ subject, markdown, html }`. Saves nothing.

### `POST /email-templates/{key}/test`
Requires `settings.update`. Queues the rendered template to one `email` address.

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
| `POST /products/{product}/image` · `DELETE` | `products.update` | multipart, field `file` — see [Media & images](#media--images) |

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

## Stocktakes

### `GET /stocktakes`
Requires `stocktakes.view`. `?search=&warehouse_id=&branch_id=&status=`. Paginated.

### `POST /stocktakes`
Requires `stocktakes.create`. Creates a draft stocktake.
```json
{ "branch_id": 1, "warehouse_id": 1, "name": "Monthly Count", "notes": "Optional" }
```
`201` → `{ "data": { "id": "...", "status": "draft", ... } }`

### `GET /stocktakes/{stocktake}`
Requires `stocktakes.view`. Returns stocktake with items, product, warehouse, branch.

### `POST /stocktakes/{stocktake}/start`
Requires `stocktakes.update`. Snapshots current stock levels into items, sets status to `counting`.

### `POST /stocktakes/{stocktake}/items/{item}/count`
Requires `stocktakes.update`. Records counted quantity for an item.
```json
{ "counted_qty": 12.5 }
```

### `POST /stocktakes/{stocktake}/complete`
Requires `stocktakes.update`. Posts stock adjustments via `StockLedgerService` for variances, sets status to `completed`.

### `POST /stocktakes/{stocktake}/cancel`
Requires `stocktakes.update`. Cancels a draft or counting stocktake.

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

Beyond the permission check, the tenant's **approval limits** are re-derived here: an
over-limit discount or a supervised price override returns `403` with
`code: approval_required` unless the caller holds `pos.approve` or the body carries a
valid `approval_token`. See *Manager approvals* below.

An optional `table_id` records which dining table the order was rung up on
(see [Dining tables & floor plan](#dining-tables--floor-plan-t125)). It is accepted on both
a checkout and a park (`status: "held"`), must belong to the caller's tenant — a table id
from another business is a `422` — and comes back on the sale as `table_id`.

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

### `POST /sales/{sale}/email-status-link`
Requires `sales.view`. Queues an email to the customer with the link to their public order
status page. Uses the customer's address by default; pass `{"email": "..."}` for a walk-in
sale. 422 if no address is available.

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
`POST /sales/{sale}/returns` below. Body:
`{ "reason": "optional string", "approval_token": "optional string" }`. `422` if the sale
isn't completed; `403` with `code: approval_required` when the tenant requires a supervisor
for voids and the caller neither holds `pos.approve` nor supplied a valid approval.

### `GET /pos/bootstrap`
Requires `pos.view`. One call for everything the terminal needs to open a register:
```json
{ "branch": { "id": 1, "name": "Main" }, "warehouse": { "id": 1, "name": "Main WH" },
  "cash_register": { "id": 1, "name": "Front till" }, "session": { "...": "or null" },
  "tax_profile": { "code": "BD_VAT", "...": "..." },
  "price_display": "inclusive", "currency": "BDT",
  "approval_policy": { "max_discount_percent": 10, "max_discount_amount": null,
    "require_approval_for_price_override": false, "require_approval_for_void": true,
    "approval_ttl_minutes": 5 },
  "can_approve": false,
  "payment_methods": [{ "value": "cash", "label": "Cash" }, "..."] }
```
`?branch_id=` overrides the caller's own branch (e.g. a multi-branch manager).
`approval_policy` lets the terminal raise the PIN prompt before the round trip — it is
**not** the enforcement point; see *Manager approvals* below.

## Manager approvals (supervisor PIN)

A cashier who exceeds the tenant's till limits is refused server-side and must have a
supervisor authorise the action with a PIN. The gate lives on `POST /sales` and
`POST /sales/{sale}/void`, so calling the API directly bypasses nothing.

**The refusal.** An over-limit request returns `403` with a machine-readable body:

```json
{ "message": "Discount over limit requires manager approval.",
  "code": "approval_required",
  "error": { "action": "discount", "requested_value": 30, "limit_value": 10 } }
```

`action` is `discount`, `price_override` or `void`. No sale is created.

### `POST /pos/approvals`
Requires `pos.create`, `throttle:approval-pin`. Verifies a supervisor's PIN and mints a
**single-use, short-lived** approval. Body:

```json
{ "action": "discount", "pin": "4821", "requested_value": 30,
  "limit_value": 10, "sale_id": null, "reason": "price match" }
```

Echo `action`, `requested_value` and `limit_value` **verbatim from the `403`** — the sale
endpoint re-derives the same numbers and refuses an approval granted for less.

```json
{ "data": { "id": "...", "token": "48 chars", "action": "discount",
  "approved_by": { "id": "...", "name": "Dana" },
  "requested_value": 30, "limit_value": 10, "expires_at": "..." } }
```

The `token` is returned exactly once (only its SHA-256 is stored). Retry the original
request with `"approval_token": "<token>"` in the body.

A PIN is only accepted when its holder is an **active user in the same tenant who holds
`pos.approve`** — a PIN alone authorises nothing. A wrong PIN is `422` (`errors.pin`); five
misses lock that cashier's terminal out of the prompt for 15 minutes, correct PIN included.

**Consumption.** The approval is stamped `consumed_at` under a row lock inside the sale's
transaction, so it cannot be replayed onto a second document, used for a different action,
used by a different cashier, or stretched past the value it was granted for.

**Audit.** Every attempt is written through `AuditLogService`: `pos.approval_granted`
(actor = the **approver**; `new_values` carries the action, both user ids and names, the
requested value, the limit and the sale id), `pos.approval_denied` (actor = the cashier,
`reason: invalid_pin`) and `pos.approval_locked`.

### Limits: `GET /settings/approvals` and `PUT /settings/approvals`
`settings.view` / `settings.update`. Stored on the business under `settings.approvals`.

| Field | Meaning |
| --- | --- |
| `max_discount_percent` | 0-100, default **100** (off). Measured against the basket's *catalogue* value, so raising a unit price cannot buy a bigger allowance. |
| `max_discount_amount` | optional absolute ceiling, `null` for none. Whichever of the two bites first applies. |
| `require_approval_for_price_override` | default `false`. Any line sold below catalogue raises the challenge. |
| `require_approval_for_void` | default `false`. `POST /sales/{sale}/void` raises the challenge. |
| `approval_ttl_minutes` | 1-60, default 5. |

The discount total counts line discounts, the sale-level `discount_amount` and the value of
any downward price override. A server-computed `coupon_code` discount is excluded — the
cashier did not choose it. Holders of `pos.approve` are never challenged for their own work.

The limit layers **on top of** the permission, it does not replace it: `pos.discount` /
`pos.price_override` decide whether a role may do the thing at all (a role without them
still gets a plain `403`, and the terminal hides the inputs); the limit decides how far
it may go before a supervisor has to sign off.

### Approval PINs

| Method & path | Permission | Notes |
| --- | --- | --- |
| `PUT /auth/approval-pin` | none (own row) | body `{ current_password, pin, pin_confirmation }`; `pin` is 4-8 digits. Stored bcrypt-hashed, `$hidden`, never returned. Audited (`user.approval_pin_set`). |
| `DELETE /auth/approval-pin` | none (own row) | body `{ current_password }`. Audited (`user.approval_pin_cleared`). |
| `DELETE /users/{user}/approval-pin` | `users.update` | revoke a team member's PIN. An admin can only ever *revoke* — never read or set someone else's, so a PIN stays known to exactly one person. |

`GET /users` and `GET /auth/me` expose `has_approval_pin` (boolean) and, when roles are
loaded, `can_approve`. The hash itself is never serialised.

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
| `GET /reports/payables-aging` | supplier payables bucketed by age |
| `GET /reports/profit-loss` | P&L from the journal, by account section |
| `GET /reports/balance-sheet` | assets, liabilities, equity and retained earnings |
| `GET /reports/shifts` | hours worked per user/period with an overtime flag |

`GET /reports/tax-summary` and `GET /reports/tax-return` require `taxes.view` instead (not
`reports.view`) — see [Tax administration](#tax-administration) below.

### `GET /reports/z-report`
The end-of-day close for one trading day: headline totals, a payment-method grid, tax by
component, discounts, the invoice-number range and corrections (voids/returns). `?date=`
(defaults to today) and an optional `?branch_id=`, which must belong to the caller's tenant.
`GET /reports/z-report/pdf` returns the same figures as an 80mm receipt-roll PDF —
streamed inline, or as a download with `?download=1`.

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

`pos.approve` is the supervisor bit — it lets a user's PIN authorise an over-limit
discount, a price override or a void. Granted to `owner`, `admin` and `branch_manager`
by default.

## Audit log

| Method & path | Permission | Notes |
| --- | --- | --- |
| `GET /audit-logs` (`?actor=&action=&model=&from=&to=&per_page=`) | `audit.view` | immutable — no update/delete endpoint. `action` matches with `LIKE %term%`; `model` matches the full auditable class name. |

Every `Sale`, `SaleReturn`, `Purchase`, `Expense`, `JournalEntry`, `User`, `Role`, `Business`
and `Coupon` create/update/delete is written automatically (via the `Auditable` trait);
non-CRUD security events (`sale.price_override`, `pos.approval_granted`,
`pos.approval_denied`, `pos.approval_locked`, `auth.login`, `auth.login_failed`, …) are
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
| `GET /settings/approvals` · `PUT /settings/approvals` | `settings.view` / `settings.update` |
| `POST /settings/onboarding/complete` | `settings.update` |

`GET /settings/business` includes a `media` object keyed by slot, each carrying
`{ path, url, urls }` — an empty slot reports `null` rather than being absent, so a
client can render a placeholder without special-casing missing keys. `logo_path` is
still returned alongside it for clients that already read it. Uploads go through
`POST /settings/business/media/{slot}`, not `PUT /settings/business` — the `PUT` no
longer accepts a `logo_path`, so a path cannot be set to point at an object the
caller does not own.

`settings.invoice_prefix` (free-form string, default `INV-`) prefixes every invoice number
this business issues — see `POST /sales`. Changing it does not renumber past sales and does
not reset the counter.

## Media & images

Every image in the app goes through one pipeline. Uploads are `multipart/form-data`
with a single field named **`file`**.

| Method & path | Permission | Collection |
| --- | --- | --- |
| `GET /media/collections` | — (any authenticated user) | the limits catalogue itself |
| `POST /products/{product}/image` · `DELETE` | `products.update` | `product.image` |
| `POST /brands/{brand}/logo` · `DELETE` | `products.update` | `brand.logo` |
| `POST /settings/business/media/{slot}` · `DELETE` | `settings.update` | `business.*` |
| `POST /me/avatar` · `DELETE` | — (the caller's own photo) | `user.avatar` |

`{slot}` is one of `logo`, `logo-dark`, `favicon`, `receipt-logo`, `cover`. Anything
else `404`s at the router.

**Upload response**

```json
{
  "data": {
    "path": "media/{tenant}/product.image/01J8Z.../",
    "url": "https://cdn.example.com/media/.../original.webp",
    "urls": {
      "original": "https://cdn.example.com/media/.../original.webp",
      "md": "https://cdn.example.com/media/.../md.webp",
      "sm": "https://cdn.example.com/media/.../sm.webp"
    }
  }
}
```

`path` is what is persisted on the model; `url`/`urls` are derived at read time and
must not be stored, because the bucket a path resolves against is environment
configuration. `DELETE` returns the same shape with every value `null`.

**What happens to an upload**

Each file is re-encoded to WebP at every size declared for its collection, then
written to the media bucket. The re-encode is not only for bandwidth: it normalises
the format, bounds storage cost, strips EXIF (a phone photo of a shelf carries the
shop's GPS coordinates), and means the bytes served are never the bytes uploaded.

SVG cannot be re-encoded, so it is run through an allowlist sanitiser instead —
`<script>`, `on*` handlers, external references, CSS `@import`/`url()`, and entity
declarations are removed, and the artwork is kept.

**Limits** are declared server-side in `config/media.php` and served by
`GET /media/collections`, which returns `key`, `label`, `max_kb`, `mimes`, a
ready-made `accept` string, and the variant boxes. Clients read their client-side
validation from there rather than hardcoding it, so the browser and the server
cannot disagree about what is allowed.

**Errors**: `422` with a `file` key for a file that is too large, the wrong type, or
undecodable. Cross-tenant ids `404` as everywhere else.

**Storage**: production writes to Cloudflare R2 (`MEDIA_DISK=r2`); local development
and the test suite fall back to the `public` disk, so neither needs credentials.
Keys are `media/{tenant_id}/{collection}/{ulid}/{variant}.webp` — tenant-first, so a
tenant's media can be removed wholesale on account closure. If the bucket has no
public URL configured it is treated as private and URLs come back as time-limited
signed links.

## Webhooks

| Method & path | Permission | Notes |
| --- | --- | --- |
| `GET /webhooks` | `settings.update` | list all webhooks for the tenant |
| `POST /webhooks` | `settings.update` | create a webhook (`url`, `events[]`); returns the secret once |
| `GET /webhooks/{webhook}` | `settings.update` | show a single webhook |
| `PUT /webhooks/{webhook}` | `settings.update` | update URL, events, or is_active |
| `DELETE /webhooks/{webhook}` | `settings.update` | delete a webhook and its deliveries |
| `GET /webhook-deliveries` | `settings.update` | paginated delivery log (filterable by `webhook_id`, `event`) |
| `POST /webhook-deliveries/{delivery}/retry` | `settings.update` | re-queue a failed delivery |

**Supported events:** `sale.completed`, `sale.returned`, `customer.created`, `inventory.low`

Each webhook delivery is signed with HMAC-SHA256 (`X-Webhook-Signature` header) using the
webhook's secret. The payload is JSON in the request body. Additional headers:
`X-Webhook-Event` (event name), `X-Webhook-Delivery` (delivery UUID). Failed deliveries are
retried up to 3 times with exponential backoff (30s, 120s, 300s).

`POST /webhooks` returns the secret in the response body — it is **not** stored in plaintext
and cannot be retrieved later.

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
exact barcode (`404` if not found). For weighed products with weight-embedded barcodes
(GS1 prefix `21` or ITF-14 format), the response includes a `parsed_weight` field with
the weight in the product's `weight_unit`. Previously the only way to find a product by
barcode was the general `?search=` filter on `GET /products`.

## Stock ledger history (T102)

The `stock_movements` ledger — the architectural centerpiece described in
[`ARCHITECTURE.md`](ARCHITECTURE.md) — was write-only via the API (only current balances and
the adjustment endpoint were exposed).

### `GET /inventory/movements`
Requires `inventory.view`. `?product_id=&warehouse_id=&variant_id=&type=&direction=&from=&to=`,
paginated, newest first. Returns each movement's `type`, `direction`, `quantity`,
`quantity_after` (the running balance at that point), `unit_cost`, `reference_type`/
`reference_id` (e.g. the sale or transfer that caused it), and the acting user.

## Data export

### `GET /{module}/export`
One endpoint per exportable module: `products`, `inventory`, `sales`, `purchases`,
`customers`, `suppliers`, `reports` (the journal) and `accounting`. Anything else 404s —
the module segment is constrained by the route.

`?format=csv` (default) streams `text/csv; charset=utf-8`; `?format=pdf` streams a PDF of
the same rows.

Authorisation is the per-module `<module>.export` slug — `products.export`,
`sales.export` and so on — checked inside the controller, because the module is a URL
segment and a static `permission:` middleware alias cannot see it. A caller without the
slug gets `403`.

Every other query parameter is the **same filter the module's own index endpoint accepts**
(`search`, `sort`, and the module's declared filters — e.g. `is_active`, `category_id` for
products; `status`, `payment_status`, `from`/`to` for sales). An export therefore returns
exactly what the list the user is looking at would, minus pagination. Unknown parameters
are ignored rather than rejected.

Exports are tenant-scoped like every other read: a business can only ever export its own
rows.

## Dining tables & floor plan (T125)

Table layout for food-service tenants. Rows live in `dining_tables`.

| Path | Permission | Notes |
| --- | --- | --- |
| `GET /tables` | `tables.view` | all tables, ordered by name; `?branch_id=` and `?status=` filter |
| `POST /tables` | `tables.create` | `name`, `seats` required; `branch_id`, `area`, `status`, `x`, `y` optional |
| `GET /tables/{table}` | `tables.view` | one table |
| `PUT /tables/{table}` | `tables.update` | partial update |
| `DELETE /tables/{table}` | `tables.delete` | soft delete |

`status` is one of `available`, `occupied`, `reserved` and defaults to `available`.
`x`/`y` are the table's position on the floor plan. `branch_id` must belong to the caller's
tenant. Each table exposes `has_active_sale`, driven by `active_sale_id`; `sales.table_id`
links a sale back to the table it was rung up on.

## Deliveries (T123)

| Path | Permission | Notes |
| --- | --- | --- |
| `POST /sales/{sale}/deliveries` | `deliveries.create` | body `{ "zone_id": "<uuid>" }`; `201` with the new delivery |
| `GET /deliveries` | `deliveries.view` | paginated; `?status=` and `?zone_id=` filter |
| `GET /deliveries/{delivery}` | `deliveries.view` | one delivery with its sale and zone |
| `PUT /deliveries/{delivery}/status` | `deliveries.update` | body `{ "status": "…" }` |

`status` is one of `dispatched`, `in-transit`, `completed`, `cancelled`; a new delivery
starts `dispatched`. A missing `zone_id` is a `422`; a `zone_id` that does not exist —
or belongs to another tenant — is a `404`. `tracking_token` mirrors the sale's public
token so a customer can follow the drop without logging in.

## Demand forecasts (T124)

### `GET /forecasts`
Requires `reorder-suggestions.view`. Projected 30-day demand per product, with a
confidence score and a `trend` of `up`/`down`/`stable`, computed from the last 90 days of
completed sales.

`?product_id=<uuid>` forecasts one product; `?product_ids=<uuid>,<uuid>` forecasts several.
With neither, it returns the first 10 active products that have a recorded average daily
sales figure. The same figures feed `GET /reorder-suggestions`.
