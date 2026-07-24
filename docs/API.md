# API Reference

Base URL: `/api/v1` · Auth: `Authorization: Bearer <token>` (Sanctum) ·
Content-Type: `application/json`.

Collection endpoints are paginated (`?page=`, `?per_page=`) and return Laravel's
standard `{ data, links, meta }` envelope. Single resources return `{ data: {...} }`.

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

```json
{
  "branch_id": 1,
  "warehouse_id": 1,
  "customer_id": null,
  "discount_amount": 0,
  "shipping_amount": 0,
  "items": [
    { "product_id": 1, "quantity": 2, "unit_price": 8, "discount_amount": 0 }
  ],
  "payments": [
    { "method": "cash", "amount": 17.6 }
  ]
}
```
`201` → the created sale (`invoice_number`, totals, items, payments, `payment_status`).
`422` with an `error` object when a line has insufficient stock:
```json
{ "message": "One or more items do not have enough stock.",
  "error": { "product_id": 1, "warehouse_id": 1, "available": 2, "requested": 5 } }
```

### `GET /sales`
Requires `sales.view`. `?search=&branch_id=&customer_id=&status=&payment_status=&from=&to=`.

### `GET /sales/{sale}`
Requires `sales.view`. Returns the sale with items, payments, customer and cashier.

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
