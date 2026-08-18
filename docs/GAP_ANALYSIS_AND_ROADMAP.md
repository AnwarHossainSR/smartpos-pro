# SmartPOS Pro — Full-Stack Implementation Plan (Frontend + Backend)

**Date:** 2026-08-17
**Purpose:** Every gap item below is specified as **both** a backend deliverable (migration,
model, service, controller, route, permission, tests) **and** a frontend deliverable (page,
components, hooks, API calls, i18n), so any item can be handed to `/implement-task <id>`
or a developer and built end-to-end without further discovery.

Item IDs continue the repo's numbering convention. Items that overlap existing backlog
tasks (T89–T118 in `REQUIREMENTS.md`) are marked. New items start at **G1**.

---

## How to Use This Plan

### Per-item structure
Each item lists:
- **Backend:** exact files to create/modify with the layer each belongs to
- **Frontend:** exact pages/components/hooks/stores to create/modify
- **Done when:** acceptance criteria covering both sides

### Global definition of done (applies to EVERY item)
**Backend:**
- `php artisan test` green (new feature test under `tests/Feature/Api/` using
  `Tests\Support\CreatesTenants`)
- `vendor/bin/pint --test` clean
- New permission slugs added to `PermissionCatalog::MATRIX` + default-role grants
- New endpoints documented in `docs/API.md`, status ticked in `docs/PROGRESS.md`

**Frontend:**
- `bun run lint` clean, `bun run build` succeeds
- Every list: loading (skeleton) + empty + error states
- Every mutation: validation + confirm-on-destructive + toast
- New strings added to `en.json` **and translated** in `de.json`, `bn.json`, `ar.json`, `ja.json`
- Permission-gated UI via `useAuth().can('<module>.<action>')`
- New query keys registered in `web/src/lib/query-keys.ts`

---

## Dependency Graph

### Independent Tasks (can run in parallel)
```
G1 (Receipt Download)  ──────────────────────────────── independent
G5 (Keyboard Shortcuts) ─────────────────────────────── independent
G6 (Hold/Park UI) ───────────────────────────────────── independent
G8 (Numpad) ─────────────────────────────────────────── independent
S1-S15 (Simplicity Batch) ───────────────────────────── independent
G3 (Dashboard v2) ───────────────────────────────────── independent
G18 (Invoice Numbering) ─────────────────────────────── independent
G9 (Onboarding Wizard) ──────────────────────────────── independent
G16 (Shifts/Time Clock) ─────────────────────────────── independent
G21 (Webhooks) ──────────────────────────────────────── independent
G28 (Per-User Theme Sync) ───────────────────────────── independent
G30 (Backups) ───────────────────────────────────────── independent
G31 (Retention & GDPR) ──────────────────────────────── independent
G34 (Observability) ─────────────────────────────────── independent
```

### Dependent Tasks (must respect order)
```
G2 (Barcode) ──→ S7 (Unified Search)
G4 (Product Images) ──→ S2 (Product Thumbnails)
G7 (Customer Quick-Attach) ──→ needs backend search verification
G12 (POS Grid View) ──→ depends on G4 (images for tiles)
G17 (Auto-Journals) ──→ G22 (P&L + Balance Sheet)
G17 (Auto-Journals) ──→ G15 (Supplier Payments journal hook)
G14 (PO Workflow) ──→ G19 (Purchase Returns)
G10 (CSV Import) ──→ G11 (Server Exports) [maatwebsite dependency]
G20 (Stripe) ──→ G42 (Customer Portal) [backlog]
```

### Sprint Dependency Chains
```
Sprint 1: G1, G2, G5, G6, G8, S1-S15 ──→ all independent (backend exists for most)
Sprint 2: G3, G4→G12, G7 ──→ G4 must land before G12
Sprint 3: G17→G22, G17→G15, G18, G13 ──→ G17 gates G22 and G15
Sprint 4: G14→G19, G10→G11 ──→ G14 before G19; G10 before G11
Sprint 5: G20, G9, G16 ──→ all independent
Sprint 6: G23, G24, G25 ──→ all independent (restaurant vertical)
Sprint 7: G29, G30, G31, G33, G34, G21, G28 ──→ all independent (infrastructure)
```

---

## Sprint 1 — Daily Operations (P0 · 1 week · mostly frontend, backend done)

### G1 · Receipt & Invoice Download Across the App
**Backend:** ✅ already exists — `GET /v1/sales/{sale}/invoice` and
`GET /v1/cash-register-sessions/{session}/slip` (both support `?download=1`).

**Frontend:**
- `web/src/app/(app)/sales/page.tsx` — add "Download invoice" (`FileText` icon) action to
  each row + inside the sale detail modal; call `apiBlob('/sales/{id}/invoice?download=1')`
  and trigger `openPdfBlob()` (helper already exists in `src/lib/utils.ts`)
- `web/src/app/(app)/cash-register/page.tsx` — "Download slip" button on the closed-session
  detail view using the same `apiBlob` pattern
- Add i18n keys `actions.downloadInvoice`, `actions.downloadSlip` × 5 languages

**Dependencies:** None (independent)
**Parallelizable:** ✅ Yes
**Effort:** S (small)

**Done when:** invoice PDF downloadable from sales list, sale detail, and cash register session
detail; loading state on button while fetching; toast on failure.

---

### G2 · Barcode Scanner Hardening + ScanAnywhere on POS
**Backend:** ✅ already exists — `GET /v1/products/barcode/{barcode}`.

**Frontend** (extend `web/src/app/(app)/pos/page.tsx`):
- Current state: Enter-on-search tries a barcode lookup (lines 366–379) and bare keystrokes
  refocus the box. **Upgrade to true scanner semantics:**
  - Track inter-keystroke timing; if a full string arrives with <30ms gaps + Enter, treat as
    scan (skip live-search debounce, call barcode endpoint immediately, beep feedback via
    WebAudio API, clear input, keep focus for the next scan)
  - On not-found: red flash on the input + error toast (currently silently swallowed) with
    option to open quick-create product drawer pre-filled with the scanned code as SKU/barcode
- **Backend addition needed:** add nullable `barcode` column to products (new migration
  `add_barcode_to_products_table`), add `barcode` to `StoreProductRequest`/`UpdateProductRequest`
  rules (unique per tenant), expose in `ProductResource`, add `barcode` to the products list
  `search` filter in `ProductController::index` (name OR sku OR barcode)
- Feature test: `ProductBarcodeTest` — create with barcode, lookup by it, duplicate barcode
  within tenant rejected (422), cross-tenant barcode lookup 404

**Dependencies:** Blocks S7 (Unified Search)
**Parallelizable:** ⚠️ Backend part independent; frontend part independent
**Effort:** M (medium)

**Done when:** a hardware scanner adds items with audio feedback; unknown barcode shows an
error with one-click path to create the product; barcode field exists on the product form.

---

### G5 · Full Keyboard Shortcut Layer on POS
**Frontend** (extend `web/src/app/(app)/pos/page.tsx`; extract hooks to
`web/src/features/pos/hooks/use-pos-shortcuts.ts`):
- F2 = focus search (exists) · **F4 = hold/park sale** · **F6 = open payment** (reassign F9) ·
  **F8 = void last line** · **Esc = close modal / clear cart (with confirm)** ·
  **Ctrl+Enter = confirm payment** · **` = toggle shortcut help**
- `use-pos-shortcuts.ts` — single `useEffect` keymap registry, accepts a handler map so
  shortcuts stay declarative
- `web/src/features/pos/components/shortcuts-overlay.tsx` — "?" overlay listing all bindings,
  opened with backtick, dismissed with Esc
- Payment modal: `onKeyDown` handler for Ctrl+Enter wired to existing `handleSubmit`

**Dependencies:** None (independent)
**Parallelizable:** ✅ Yes
**Effort:** M (medium)

**Done when:** every POS action reachable without mouse; overlay documents all shortcuts;
all strings in 5 languages.

---

### G6 · Hold / Park Sale UI
**Backend:** ✅ already exists — `POST /v1/sales` with `status: held`, `POST /v1/sales/{sale}/resume`,
`GET /v1/sales?status=held`.

**Frontend:**
- `web/src/app/(app)/pos/page.tsx`:
  - "Park" button (F4) next to Checkout — POSTs the cart as `status: held` (same body as
    checkout), clears cart, toasts "Sale parked"
  - "Held orders (N)" button above cart — opens `web/src/features/pos/components/held-orders-panel.tsx`:
    list from `GET /sales?status=held&per_page=20&sort=sold_at&direction=desc` showing
    reference, customer/`Walk-in`, line count, total, parked-at time
  - "Resume" per row: `GET /sales/{id}` → repopulate cart (map items back to
    `CartItem { product, qty, unit_price, discount }`; fetch full product objects to restore
    stock/tax data) → `POST /sales/{id}/void` **not** called (held sale stays until resumed —
    resume endpoint finalizes it server-side, stock was never decremented while held)
  - "Discard" per row (confirm dialog) → `POST /sales/{id}/void`
- New query key `queryKeys.heldSales = resource("held-sales")`, invalidate on park/resume/discard

**Dependencies:** None (independent)
**Parallelizable:** ✅ Yes
**Effort:** M (medium)

**Done when:** park → see in list → resume completes the sale correctly; discard voids it;
held list refreshes live.

---

### G8 · On-Screen Numeric Keypad (Touch POS)
**Frontend:**
- New `web/src/components/ui/numpad.tsx` — grid component (7 8 9 / 4 5 6 / 1 2 3 / . 0 ⌫),
  props: `onDigit`, `onBackspace`, `onConfirm`, large 48px+ targets, `haptic` feedback via
  `navigator.vibrate` where supported
- Wire into POS: quantity popover per cart line (tap qty → numpad opens anchored);
  PaymentModal amount field (tap amount → numpad replaces keyboard on `pointer: coarse`
  devices via `matchMedia`)
- Show numpad only on touch devices for the payment amount; keep native keyboard otherwise

**Dependencies:** None (independent)
**Parallelizable:** ✅ Yes
**Effort:** M (medium)

**Done when:** full checkout flow usable with zero physical keyboard on a tablet; numpad is a
reusable primitive with a unit test.

---

### S-Batch · 15 Simplicity Fixes (all `web/src`, ≤30 min each)
| # | Fix | File | Depends On |
|---|-----|------|------------|
| S1 | Auto-focus search on POS mount (verify after route transitions) | `pos/page.tsx` | None |
| S2 | Product thumbnails in POS grid tiles | `pos/page.tsx` | G4 |
| S3 | Sticky cart-total footer visible while scrolling long carts | `pos/page.tsx` | None |
| S4 | Confirm before "Clear" on non-empty cart | `pos/page.tsx` | None |
| S5 | Default report date-filters to current month on load | `reports/page.tsx` | None |
| S6 | Enlarge "Change due" on receipt modal (2xl green) | `pos/page.tsx` | None |
| S7 | Unified search hits name+barcode+sku | `pos/page.tsx` | G2 |
| S8 | Phone shown next to name in customers table column | `customers/page.tsx` | None |
| S9 | Toast on 409 idempotency-replay | `pos/page.tsx` | None |
| S10 | "Print receipt" button → `window.print()` with `@media print` CSS | `pos/page.tsx` + `globals.css` | None |
| S11 | Disable Checkout button while `checkoutMutation.isPending` | `pos/page.tsx` | None |
| S12 | Low-stock warning badge on tile (needs `min_stock_level` in response) | both | None |
| S13 | Payment row 1 defaults to exact total (audit & keep) | `pos/page.tsx` | None |
| S14 | Net-of-tax line total shown under gross when price_display = inclusive | `pos/page.tsx` | None |
| S15 | Persist last branch/warehouse per user → `localStorage` | `pos/page.tsx` | None |

**Dependencies:** S2→G4, S7→G2, rest independent
**Parallelizable:** ✅ Yes (most items)
**Effort:** S (each ≤30 min)

---

## Sprint 2 — Catalog & Dashboard (P0 · 1 week · both sides)

### G3 · Dashboard v2
**Backend** (extend `app/Http/Controllers/Api/V1/DashboardController.php` — keep single
`GET /v1/dashboard/summary` endpoint, one response):
- Add to payload (all single-pass aggregates, reuse existing `toBase()` pattern):
  - `top_products` — last 7 days, `sale_items` join `products`, sum quantity + revenue, limit 5
  - `recent_sales` — last 10 completed: id, invoice_number, total, payment_status, sold_at, customer name
  - `low_stock_items` — 5 worst: product name, warehouse, qty, min_stock_level
  - `week_comparison` — this-week revenue vs last-week revenue + pct change (two `sum()` on
    half-open ranges, no clones)
- Feature test `DashboardTest::test_summary_includes_dashboard_v2_fields`

**Frontend** (rewrite `web/src/app/(app)/dashboard/page.tsx` from 90 lines):
- Row 1: existing 4 KPI cards (`StatCard` component) + revenue-comparison card with ▲/▼ pct
- Row 2: 14-day sales trend area chart (Recharts `AreaChart` — data already returned)
- Row 3 (2-col): Top products mini-table (rank, name, qty, revenue) | Recent transactions
  list (invoice, time, total, status badge) with click → sales page filtered by id
- Row 4: Low-stock strip — amber warning cards, click → inventory page with low-stock filter
- Row 5: Quick actions — "New Sale" (→ /pos), "Add Product" (→ /products?new=1),
  "Open Register" (→ /cash-register), "Record Expense" (→ /expenses?new=1)
- Read `?new=1` on products/expenses pages to auto-open their create drawers
- i18n keys under `dashboard.*` × 5 languages

**Dependencies:** None (independent)
**Parallelizable:** ✅ Yes
**Effort:** L (large)

**Done when:** dashboard answers "how is business doing + what needs attention" without
navigation; all sections handle empty state (e.g. new tenant with zero sales).

---

### G4 · Product Image Upload
**Backend:**
- Migration `add_image_path_to_products_table` (nullable string after `sku`)
- `app/Http/Controllers/Api/V1/ProductController::uploadImage(Request, Product)`:
  - `POST /v1/products/{product}/image` (multipart), validates `image` mimes:jpg,png,webp
    max 2048kb, stores via `Storage::disk('public')->putFile("products/{$tenantId}", $file)`,
    deletes old image, updates `image_path`, returns `['data' => ['url' => Storage::url(...)]]
  - `DELETE /v1/products/{product}/image` — removes file + nulls column
  - Permission: reuse `products.update` (no new slug needed)
- `ProductResource`: add `image_url` (null-safe accessor)
- Config: `FILESYSTEM_DISK=public` documented; symlink note in DEPLOYMENT.md
- Tests: `ProductImageTest` — upload stores + returns URL, oversize rejected 422, delete
  removes file, cross-tenant product 404

**Frontend:**
- `web/src/components/ui/image-upload.tsx` — reusable dropzone: drag-drop + click, client-side
  validation (type/size) with preview, progress state; props `onUpload(file)`, `currentUrl`,
  `aspect` (default square)
- `web/src/app/(app)/products/page.tsx` — image field in ProductForm (create + edit), image
  thumbnail column in DataTable
- POS grid tiles (G12 dependency): `<img>` with `next/image`-less plain fallback + name
  overlay when no image
- Note: `apiBlob`/`api` wrapper assumes JSON — add `apiUpload(path, formData)` helper to
  `src/lib/api.ts` that sets `Content-Type: multipart/form-data` (no manual header; let
  fetch set the boundary) and omits the JSON content-type

**Dependencies:** Blocks G12, S2
**Parallelizable:** ✅ Backend and frontend can be done in parallel within the item
**Effort:** L (large)

**Done when:** merchant uploads a photo on create/edit; image shows in products list + POS
tiles; upload errors (wrong type/too big) toast clearly in all 5 languages.

---

### G7 · Quick Customer Attach at POS (upgrade existing)
**Frontend** (upgrade the existing customer selector in `pos/page.tsx`):
- Replace text-match dropdown with phone-first search: `GET /customers?search={phone}&per_page=5`
  (backend `search` already partial-matches phone — verify in `CustomerController::index`
  `AllowedFilter::search` covers `phone` column; extend if not)
- After attach: fetch loyalty balance `GET /customers/{id}/loyalty` → show points chip
  beside customer name
- "+ Create {phone}" row at dropdown bottom when no matches → inline mini-form (name +
  phone prefilled) → `POST /customers` → auto-attach; toast success
- Show `balance_due` warning badge if customer owes money

**Backend (small):**
- Verify/extend `CustomerController::index` search filter to include `phone`
  (`AllowedFilter::search(... ['name', 'phone', 'email'])`)
- `CustomerResource`: ensure `loyalty_points` balance included (or dedicated lightweight
  field) so POS doesn't need the extra loyalty round-trip
- Test: `CustomerTest::test_index_search_matches_phone`

**Dependencies:** None (independent)
**Parallelizable:** ✅ Yes
**Effort:** M (medium)

**Done when:** typing a phone number finds/creates/attaches a customer in <3 interactions,
shows points + due balance inline.

---

### G12 · POS Grid View with Categories & Favorites
**Frontend:**
- `web/src/features/pos/components/product-grid.tsx` — tile grid (image, name, price,
  stock badge), category tab bar on top (`GET /categories?per_page=50` flat list)
- `web/src/features/pos/components/view-toggle.tsx` — list ↔ grid persisted in
  `localStorage` per user
- Favorites: star icon on tile → stored per user (`localStorage` first pass; no backend);
  "★ Favorites" pseudo-category tab pinned first
- Search filters both views; tap tile = add 1 (with the G8 numpad popover for qty on
  long-press/hover-actions)
- Pagination: infinite scroll or "Load more" (`per_page=50&page=n` via TanStack
  `useInfiniteQuery`)

**Dependencies:** G4 (images for tiles), G8 (numpad for qty)
**Parallelizable:** ⚠️ Must wait for G4 and G8
**Effort:** L (large)

**Done when:** cafe-style merchant can operate POS entirely from tiles organized by
category with favorites one tap away.

---

## Sprint 3 — Accounting Completeness (P1 · 1–2 weeks · backend-heavy)

### G17 · Auto-Journal Posting from Trading Activity (= T117)
**Backend:**
- New `app/Services/Accounting/TradingJournalService.php`:
  - `postSale(Sale $sale): JournalEntry` — DR payment-method accounts / AR (`due`),
    CR Sales Revenue, CR Tax Payable (per tax-component snapshot), CR Discounts (if any);
    reference amounts from `sale_items` snapshots (never recompute)
  - `postPurchase(Purchase $p)` — DR Inventory (at weighted-avg cost recorded), CR AP/Cash
  - `postExpense(Expense $e)` — DR expense-category account, CR cash/bank by method
  - `postSaleReturn(SaleReturn $r)` — exact reversal of the original sale entry
  - `postCashMovement(CashRegisterTransaction $m)` — DR/CR cash drawer ↔ petty cash
  - Each resolves account ids from a **default account map** seeded per tenant
    (`account_defaults` JSON column on `businesses` or a `tenant_account_settings` table;
    seeder creates the standard chart: 1000 Cash, 1100 AR, 1200 Inventory, 2000 AP,
    2100 Tax Payable, 3000 Sales Revenue, 4000 COGS, 5000 Expenses…)
- Wire inside existing services (same DB transaction): `SaleService::create/resume/void`,
  `SaleReturnService::create`, `PurchaseService::create`, `ExpenseController::store`,
  `CashRegisterService::movement`
- Tenant opt-out: `businesses.auto_post_journals` boolean (default true for new tenants,
  false for existing) + Settings endpoint to toggle
- Guard: posting failure must roll back the *sale* too (same transaction) — document decision
- Tests: `TradingJournalTest` — sale posts balanced entry with correct accounts; return
  reverses it; totals in trial balance equal sum of sales after 3 mixed transactions;
  opt-out tenant produces no entries

**Frontend:**
- `web/src/app/(app)/settings/page.tsx` — "Automatic bookkeeping" toggle card (reads/writes
  new setting via `GET/PUT /settings/business`), explanatory copy × 5 languages
- Accounting page journals list: show `source` badge (Sale/Purchase/Expense/Manual) —
  add `source_type` to `JournalEntryResource` + filter chip in list toolbar

**Dependencies:** Blocks G22, G15 (journal hook)
**Parallelizable:** ✅ Backend and frontend within the item
**Effort:** XL (extra large)

**Done when:** trial balance reflects real trading automatically; toggle honored; audit
entry written when toggled.

---

### G18 · Per-Tenant Gapless Invoice Numbering (= T118)
**Backend:**
- Migration: `add_invoice_sequence_to_tenants_table` (bigint default 0) +
  `add_invoice_number_to_sales_table` (nullable unique-per-tenant string) +
  backfill command `php artisan sales:backfill-invoice-numbers` (per tenant, ordered by id)
- `SaleService::create()`: inside the checkout transaction, `SELECT ... FOR UPDATE` on the
  tenant row, increment, format `{prefix}{seq}` (prefix from business settings, default
  `INV-`), write to sale; drop the placeholder-update pattern
- Quotation→Sale conversion and resume flow share the same path (number assigned at
  completion, not hold)
- Tests: `InvoiceNumberingTest` — two concurrent checkouts (parallel transactions) produce
  sequential numbers, no gaps within tenant; existing sales keep numbers after backfill

**Frontend:** none required (already displays `invoice_number`) — QA only.

**Dependencies:** None (independent)
**Parallelizable:** ✅ Yes
**Effort:** M (medium)

**Done when:** invoice numbers are gapless per tenant under concurrency; backfill verified
on seeded data.

**Status:** ✅ Done (`feature/G18`). `tenants.invoice_sequence` + nullable
`sales.invoice_number`; `App\Services\Sales\InvoiceNumberService` takes the row lock and
issues `{prefix}{seq}` inside the checkout transaction; checkout, held-sale resume and
quotation→sale conversion all go through it; `php artisan sales:backfill-invoice-numbers`
fills historical rows without touching existing numbers. Covered by
`tests/Feature/Api/InvoiceNumberingTest.php` (10 tests). The "two *parallel* transactions"
criterion is asserted deterministically rather than with real concurrency — the suite runs
on in-memory SQLite, which has neither a second connection nor `SELECT … FOR UPDATE`.

---

### G22 · Financial Statements: P&L + Balance Sheet (= T109, needs G17)
**Backend:**
- `app/Services/Accounting/FinancialStatementService.php`:
  - `profitLoss(Carbon $from, Carbon $to)` — group `journal_lines` by account type
    (Revenue credit−debit, Expense debit−credit, COGS) → gross profit, operating expenses,
    net profit; includes `sections: [{label, accounts: [{name, amount}], subtotal}]`
  - `balanceSheet(Carbon $asOf)` — Assets = DR−CR, Liabilities & Equity = CR−DR, with
    retained earnings plug (Revenue−Expense累积) so it always balances
- Routes: `GET /v1/reports/profit-loss?from&to`, `GET /v1/reports/balance-sheet?as_of`
  under existing `permission:reports.view` + `throttle:reports`
- PDF exports: `GET /v1/reports/profit-loss/pdf` etc. via dompdf Blade templates
  (`resources/views/pdf/statement.blade.php`) mirroring the invoice pattern
- Tests: `FinancialStatementTest` — posted fixture journals → P&L figures match hand-computed;
  balance sheet balances to zero

**Frontend:**
- `web/src/app/(app)/reports/page.tsx` — add "Profit & Loss" and "Balance Sheet" tabs
  alongside existing 6 reports
- Statement layout component `web/src/features/reports/components/statement.tsx`:
  indented section rows, subtotals bolded, net-profit highlight; date-range picker (P&L) /
  as-of picker (BS); "Download PDF" via `apiBlob`
- Reports nav: no new page (tabs inside /reports) — but add sub-nav quick links
  `web/src/lib/nav.ts` if reports page grows

**Dependencies:** G17 (needs auto-journals for data)
**Parallelizable:** ❌ Must wait for G17
**Effort:** L (large)

**Done when:** owner can produce a P&L for any period and a balancing balance sheet, both
on-screen and as PDF.

---

### G13 · End-of-Day Z-Report
**Backend:**
- `GET /v1/reports/z-report?date=&branch_id=` (ReportController or new
  `ZReportController`; `permission:reports.view`):
  - Completed sales in range grouped by payment method (amount + count), tax collected by
    component (from `sale_item_taxes` snapshots), discounts summed, transaction count,
    average basket, first/last invoice number, cash sessions summary (open cash, expected,
    actual, difference), voids/returns count
- `GET /v1/reports/z-report/pdf` — thermal-friendly Blade PDF
- Tests: `ZReportTest` — seeded day with mixed payments → all groups sum correctly

**Frontend:**
- Cash register close-success screen: "Print Z-report" button → opens PDF
- Reports page: "Z-Report" tab with date + branch filters and on-screen summary table
  (methods grid, tax breakdown, cash reconciliation box) + PDF button

**Dependencies:** None (independent)
**Parallelizable:** ✅ Yes
**Effort:** L (large)

**Done when:** end-of-day closing produces one printable summary covering money, tax, and
cash reconciliation.

---

## Sprint 4 — Supply Chain & Data (P1 · 1–2 weeks · both sides)

### G14 · Purchase Order Workflow (draft → approve → partial receive)
**Backend:**
- Migration: `add_status_workflow_to_purchases_table` — ensure statuses
  `draft|approved|partial|received|cancelled` (enum may already exist as
  `PurchaseStatus`; add `approved_at`, `received_at` timestamps)
- `app/Services/Purchasing/PurchaseService.php` — split `create()`:
  - `create()` stores as `draft`, **no stock movement**, no supplier balance change
  - new `approve(Purchase, User)` → `approved` (permission `purchases.approve` — exists)
  - new `receive(ReceivePurchaseData, User)` → per-line `accepted_qty`; stock via
    `StockLedgerService` for accepted quantities only; supports multiple partial receipts
    (`purchase_receipts` table: purchase_id, user_id, received_at + `purchase_items.accepted_qty`);
    status → `partial` until all lines fully accepted → `received`; supplier balance +
    product cost updated per receipt
  - `cancel()` — allowed on draft/approved (approved cancels must have zero receipts)
- Routes: `POST /purchases/{purchase}/approve|receive|cancel`, all idempotency-wrapped
- `PurchaseResource`: expose `status`, `received_qty` per item, receipts relation
- Tests: `PurchaseWorkflowTest` — draft→approve→partial receive→receive; cancel guards;
  stock only moves on receive; oversell of accepted qty rejected

**Frontend** (`web/src/app/(app)/purchases/page.tsx`):
- Status badges (draft gray / approved blue / partial amber / received green / cancelled red)
- Create form unchanged except copy: "Save as draft"
- Detail drawer: per-line progress (`received/ordered`), "Approve" button
  (`can('purchases.approve')`), "Receive goods" opens `receive-goods-modal.tsx` — per-line
  accepted-qty inputs defaulting to outstanding, writes payment rows for this receipt
- Filter chips by status; void/cancel with confirm

**Dependencies:** Blocks G19
**Parallelizable:** ✅ Backend and frontend within the item
**Effort:** L (large)

**Done when:** a full PO lifecycle is operable with correct stock timing and permissions.

---

### G15 · Supplier Payments / AP Aging (= T94)
**Backend:**
- Migration `create_supplier_payments_table` (tenant_id, supplier_id, amount, method,
  reference, paid_at, notes, timestamps; index supplier_id+paid_at)
- Model `SupplierPayment` (+ `BelongsToTenant`, `Auditable`), `StoreSupplierPaymentRequest`
  (positive amount, method enum, tenant-scoped supplier)
- `app/Services/Purchasing/SupplierPaymentService.php` — record payment (row-locked supplier
  balance decrement; floor at negative → validation), list payments, statement generation
- `POST /v1/suppliers/{supplier}/payments` (idempotent), `GET /v1/suppliers/{supplier}/payments`,
  `GET /v1/suppliers/{supplier}/statement` (purchases + payments merged ledger running balance)
- `GET /v1/reports/payables-aging` — buckets 0–30/31–60/61–90/90+ from purchase date − payments
- If G17 shipped: `postSupplierPayment` journal hook (DR AP, CR Cash)
- Tests: `SupplierPaymentTest` — payment reduces balance; statement running balance correct;
  aging buckets correct on seeded dates

**Frontend:**
- Suppliers page: detail drawer gains "Payments" tab (history table) + "Record payment"
  button → `payment-modal.tsx` (amount, method select, reference, date) — mirrors POS
  payment modal patterns
- "Statement" button → printable statement view (screen + `window.print` CSS)
- Reports page: "Payables aging" tab — bucket table + bar chart (Recharts)

**Dependencies:** G17 (journal hook, optional)
**Parallelizable:** ✅ Yes (G17 hook is optional enhancement)
**Effort:** L (large)

**Done when:** AP cycle closed: purchase → owe → pay → reconcile, with aging visibility.

---

### G19 · Purchase Edit / Cancel / Return
**Backend:**
- `PUT /v1/purchases/{purchase}` — editable while `draft` only (lines, prices, supplier)
- `POST /v1/purchases/{purchase}/return` + `StorePurchaseReturnRequest` —
  `create_purchase_returns_table` (mirrors sale returns): per-line qty ≤ accepted,
  stock **out** via ledger (AdjustmentOut type `purchase_return`), supplier balance credit,
  reference `PR-xxxx`
- Tests: `PurchaseReturnTest` — return restocks correctly (out), balance credited,
  over-return rejected

**Frontend:** edit drawer reuse for draft; "Return to supplier" action on received
purchases → modal with per-line qty inputs + reason; returns listed in a
"Purchase returns" tab on purchases page

**Dependencies:** G14 (must exist first)
**Parallelizable:** ❌ Must wait for G14
**Effort:** M (medium)

**Done when:** wrong receipts are correctable through proper reversal flows instead of
being permanent.

---

### G10 · Bulk CSV/XLSX Product Import (= T107)
**Backend:**
- `composer require maatwebsite/excel`
- `POST /v1/products/import` (multipart, `permission:products.create`, queued for >500 rows):
  - Importer class validates per row (name required, price numeric>0, category/brand/unit
    resolved **by name** — auto-create category/brand if missing flag)
  - Two-phase: `dry_run=true` returns `{rows_valid, rows_invalid, errors:[{row, column, message}]`,
    `dry_run=false` performs insert in chunks inside a transaction
  - Columns: name, sku, barcode, category, brand, unit, purchase_price, selling_price,
    tax_rate, opening_stock, min_stock_level, is_active
- `GET /v1/products/import-template` — returns the CSV template (streamed response)
- Job `ProcessProductImport` implements `ShouldQueue` for large files + progress polling
  endpoint `GET /v1/products/import/{id}` (status, processed, errors)
- Tests: `ProductImportTest` — dry-run errors reported with row numbers; real import
  inserts + auto-creates category; invalid file 422

**Frontend:**
- Products page toolbar: "Import" button → `import-modal.tsx`:
  1. Dropzone (reuse `image-upload` patterns) + "Download template" link
  2. After upload: preview table (first 50 rows), error rows highlighted with messages
  3. Confirm → progress bar (poll import status) → success toast "N products imported"
- Bulk-select in DataTable already exists — add "Export selected" (client CSV exists; server
  export arrives in G11)

**Dependencies:** Blocks G11 (maatwebsite dependency)
**Parallelizable:** ✅ Backend and frontend within the item
**Effort:** L (large)

**Done when:** 500-row CSV becomes a live catalog in one guided flow with row-level errors.

---

### G11 · Server-Side Exports (= T108)
**Backend:**
- Shared trait `app/Http/Controllers/Concerns/ExportsList.php`:
  `exportList(Builder $query, array $columns, string $filename, string $format)` streams
  CSV (` League\Csv` or fputcsv to php://output) / XLSX (maatwebsite from G10) — chunked
  `chunkById(500)` so memory stays flat
- Add `GET /v1/{resource}/export` for: products, sales, customers, suppliers, inventory,
  purchases, expenses (each accepts the same filters as its index — reuse `QueryPipeline`)
- Permissions: existing `*.export` slugs finally enforced on real routes
- Tests: `ExportTest` — filtered export matches index filter; permission 403 without slug

**Frontend:** swap client-side `exportToCsv` usages for `apiBlob('/{resource}/export?…filters')`
on each list toolbar; keep client fallback when API unavailable; button label "Export (CSV)"
with format dropdown later

**Dependencies:** G10 (maatwebsite for XLSX)
**Parallelizable:** ❌ Must wait for G10
**Effort:** M (medium)

**Done when:** every list exports exactly what's on screen, server-side, permission-gated.

---

## Sprint 5 — Growth & Billing (P2 · 2 weeks · both sides)

### G20 · Stripe Payments (Card at POS + Online Invoices + Subscriptions) (= T91)
**Backend** (package `stripe/stripe-php`; keys in env: `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`):
- **Phase A — Online invoice payment:**
  - Migration `create_stripe_columns`: `tenants.stripe_customer_id`, `sales.payment_url_token`
  - `app/Services/Billing/StripeService.php` — `createCheckoutSession(Sale)` (success/cancel
    URLs, metadata sale_id), webhook `checkout.session.completed` → records `SalePayment`
    via existing `SaleService::collectPayment()` path (idempotent via Stripe event id table
    `stripe_events`)
  - `POST /v1/sales/{sale}/pay-link` → returns hosted-checkout URL; route
    `GET /v1/pay/{token}` public page-status endpoint for the frontend
  - Webhook route `POST /v1/webhooks/stripe` (signature-verified, no auth)
- **Phase B — Subscriptions:** `SubscriptionService::checkout()` creates Stripe
  Checkout (mode: subscription) when plan has `stripe_price_id`; webhook
  `customer.subscription.*` syncs `subscriptions` table status; dunning handled by Stripe
  emails; `plans.stripe_price_id` column + super-admin edit
- **Phase C — Terminal (tap to pay):** `POST /v1/payments/terminal/create-intent`
  (payment intent for sale total) + `POST /v1/payments/terminal/confirm` — frontend uses
  Stripe Terminal JS
- PaymentMethod enum: add `card_stripe` case + label
- Tests: `StripePaymentTest` with mocked Stripe client (contract tests on webhook payloads)

**Frontend:**
- Sale detail modal: "Send payment link" (copies URL / email toggle) when `due_amount > 0`
- Public page `web/src/app/pay/[token]/page.tsx` — branded payment status (pending/paid),
  no auth, polls status; success animation
- Subscription checkout page: when plan has `stripe_price_id` redirect flow replaces
  trial-instant activation; "Manage card" → Stripe billing portal route
- POS payment modal: "Card (terminal)" method → intent flow with spinner states
- Settings → Billing: card on file status, invoices list

**Dependencies:** None (independent, but complex)
**Parallelizable:** ✅ Yes (phases can be done sequentially within the item)
**Effort:** XL (extra large)

**Done when:** a card pays an invoice online, a subscription charges monthly, and a tap
pays at POS — all reconciled into the existing payments tables.

---

### G9 · Onboarding Wizard
**Backend (small):**
- `businesses.onboarding_state` JSON column (step flags + dismissed flag) exposed via
  `GET /v1/settings/business`; `POST /v1/settings/onboarding/step` records progress
  (`permission:settings.update`); existing `completeOnboarding` stays as final call

**Frontend:**
- `web/src/features/onboarding/wizard.tsx` — mount in `(app)/layout.tsx` when
  `onboarding_state.current_step < done && !dismissed`:
  1. Business profile (name/logo/currency — pre-filled from registration)
  2. First product (embedded mini-form → `POST /products`, success ✓)
  3. Tax setup (pick country profile → link to tax settings for advanced)
  4. Open register + first sale (CTA buttons into POS)
- Progress rail, skippable per step, "Skip setup" with confirm; completion confetti +
  dashboard greeting banner dismisses
- All copy in 5 languages; steps respect permissions (`can('products.create')` etc. hides
  gated steps)

**Dependencies:** None (independent)
**Parallelizable:** ✅ Yes
**Effort:** M (medium)

**Done when:** a brand-new tenant reaches a completed first sale without support contact,
and the wizard never reappears after dismissal.

---

### G16 · Employee Shifts & Time Clock
**Backend:**
- Migration `create_shifts_table` (tenant_id, user_id, branch_id, clock_in, clock_out,
  note, status open/closed; unique partial index one-open-per-user)
- `app/Services/Hr/ShiftService.php` — `clockIn` (reject if open shift exists), `clockOut`
  (computes minutes), `currentShift(User)`
- Controller `ShiftController`: `POST /v1/shifts/clock-in|clock-out`, `GET /v1/shifts/me`,
  `GET /v1/shifts?user_id&from&to` (`permission:users.view` for listing others)
- `GET /v1/reports/shifts` — hours per user per day/week, flagged overtime > 8h
- New permission module `shifts: ['view','manage']` + default roles (cashier/manager get own-shift;
  admin gets all)
- Tests: `ShiftTest` — double clock-in 422; hours math; report totals

**Frontend:**
- Header (`web/src/components/layout/header.tsx`): "Clock in/out" pill with live elapsed
  timer (visible to all roles); open-shift indicator dot
- Team page: "Shifts" tab — per-user hours table with week picker; admin only
- Reports: "Shift hours" tab joins the reports page family

**Dependencies:** None (independent)
**Parallelizable:** ✅ Yes
**Effort:** M (medium)

**Done when:** staff clock in/out from any page; managers see hours + overtime weekly.

---

## Sprint 6 — Restaurant Vertical (P2 · only if this lane is chosen)

### G23 · Kitchen Display System
**Backend:**
- Migration: `add_kitchen_fields_to_sales_table` (`kitchen_status`
  pending|preparing|ready|served, `kitchen_note`), `businesses.kitchen_token` (random string)
- `PosController::bootstrap` or new `GET /v1/kitchen/bootstrap?token=` — token-authenticated
  (token in query; header alternative), returns pending/Preparing/ready orders for branch
- `PATCH`-style `POST /v1/kitchen/orders/{sale}/status` (token or logged-in with
  `pos.view`) → updates status, **broadcasts new `KitchenOrderUpdated` Reverb event** on
  `branch.{id}` channel
- `SaleCompleted` event already broadcasts — kitchen listens to it for new orders
- Tests: `KitchenDisplayTest` — token bootstrap lists orders; status transition broadcasts

**Frontend:**
- New route `web/src/app/kitchen/page.tsx` (outside `(app)` shell, minimal chrome,
  `kiosk` layout: no sidebar, large type): token entry once → stored in localStorage
- Three-column Kanban (Pending / Preparing / Ready): order cards with items+modifiers,
  elapsed timer (green→amber→red at thresholds), tap card advances status
- TanStack query `refetchInterval` fallback + Reverb websocket push when online
- Sound chime on new order (WebAudio)

**Dependencies:** None (independent)
**Parallelizable:** ✅ Yes
**Effort:** L (large)

**Done when:** kitchen tablet shows orders within a second of checkout and advances them
by tap, surviving reloads.

---

### G24 · Tables & Floor Plan
**Backend:**
- Migration `create_dining_tables_table` (tenant_id, branch_id, name, area, seats,
  status available|occupied|reserved, x, y for layout) — `table_id` FK on `sales` (nullable)
- `TableController` CRUD + `GET /v1/tables?branch_id&include=active_sale`; assigning a
  table = create sale with `table_id` + `status: held` (reuses held infra) → table auto-occupied;
  resume/void frees it (service hooks)
- Permissions: `tables: view/create/update/delete` module
- Tests: `TableTest` — assign/unassign lifecycle, two sales can't hold one table

**Frontend:**
- New page `web/src/app/(app)/tables/page.tsx` + nav entry: grid of table cards colored by
  status, drag-to-position (persist x/y), click occupied table → its held order (resume in POS)
- POS: "Table" selector beside customer when restaurant mode on (`businesses.restaurant_mode`
  setting toggle in settings)

**Dependencies:** None (independent)
**Parallelizable:** ✅ Yes
**Effort:** L (large)

**Done when:** waiter seats an order at a table from POS or the floor view and the table
frees itself when the sale completes.

---

### G25 · Modifiers & Split Bills
**Backend:**
- Migrations: `create_modifiers_table` (name, options JSON [{label, price_delta}]),
  `create_product_modifier_table` pivot, `sale_items.modifiers_snapshot` JSON
- `SaleLineBuilder` — apply price deltas before tax; snapshot into line
- Split bills: `POST /v1/sales/{sale}/split` with `portions: [{payments:[…]}]` — creates N
  payments + due allocation against the single sale (no new sale rows); validation sum = total
- Tests: `ModifierTest` (price math + snapshot immutability), `SplitBillTest`

**Frontend:**
- Product form: modifiers editor (option rows with price delta)
- POS: tapping a tile with modifiers opens `modifier-sheet.tsx` (checkbox options) before
  add-to-cart; cart line shows modifier chips
- Payment modal: "Split bill" mode — equal N ways or custom amounts, renders one payment
  row per portion

**Dependencies:** None (independent)
**Parallelizable:** ✅ Yes
**Effort:** L (large)

**Done when:** "No onions −0 / Extra cheese +1.50" flows into the line total and a bill
splits 3 ways against one invoice.

---

## Sprint 7 — Scale & Reliability (P3 · interleavable)

### G29 · Cache Layer
**Backend only:**
- `pos/bootstrap`: `Cache::remember("pos:bootstrap:{$tenantId}:{$branchId}", 300, …)`
  with invalidation via model `flushCache` hooks (Product/ProductVariant/TaxProfile/Business
  observers) — careful: stock counts inside bootstrap must stay fresh → **split** bootstrap
  into cached catalog part + live session/stock part
- Tax profiles list, permission catalog (`Cache::remember` 1h, flush on Role events)
- Redis as cache store in production (already in docker-compose)
- Tests: cached response changes after product update (observer flush works)

**Dependencies:** None (independent)
**Parallelizable:** ✅ Yes
**Effort:** M (medium)

---

### G30 · Backups
**Infra only:** `spatie/laravel-backup`, nightly `backup:run` schedule, S3 disk target,
7-daily/4-weekly/6-monthly cleanup, health monitor notification route to super-admin email;
documented restore procedure in DEPLOYMENT.md

**Dependencies:** None (independent)
**Parallelizable:** ✅ Yes
**Effort:** S (small)

---

### G31 · Retention & GDPR
**Backend:** `audit_logs` prune job (configurable days via `businesses.audit_retention_days`,
default 180) + `POST /v1/customers/{id}/anonymize` (permission `customers.delete`; hashes
PII, keeps financial rows) + `GET /v1/settings/retention` endpoints.
**Frontend:** Settings → Data & privacy card (retention days, anonymization explanation) +
anonymize action (double-confirm) on customer detail.

**Dependencies:** None (independent)
**Parallelizable:** ✅ Yes
**Effort:** M (medium)

---

### G33 · Critical-Path Test Suite
- `tests/Feature/Api/CheckoutE2ETest` — checkout decrements stock, posts journal (post-G17),
  updates loyalty, honors coupon once, idempotent replay
- `ConcurrencyTest` — two simultaneous checkouts last unit of stock: exactly one succeeds
- `RefundE2ETest`, `PermissionMatrixTest` (cashier denied admin routes), `PlanLimitTest`
- CI: unchanged gates; add coverage step optional

**Dependencies:** G17 (for journal assertions)
**Parallelizable:** ⚠️ Can start structure, but assertions need G17
**Effort:** L (large)

---

### G34 · Observability
- Sentry (Laravel + Next.js both; `sentry-laravel`, `@sentry/nextjs`), PostHog frontend
  capture, `GET /v1/health` (db/redis/queue checks), error-boundary component in web root layout

**Dependencies:** None (independent)
**Parallelizable:** ✅ Yes
**Effort:** M (medium)

---

### G21 · Webhooks (= T110)
**Backend:** `webhooks` + `webhook_deliveries` tables; `WebhookService::dispatch(event, payload)`
 HMAC-signed, queued with 3× exponential retry; events: sale.completed, sale.returned,
 customer.created, inventory.low; Settings CRUD `GET/POST/PUT/DELETE /v1/webhooks`
 (`permission:settings.update`); delivery log UI data endpoint
**Frontend:** Settings → Webhooks card list + create drawer (URL, events multi-select,
 secret reveal), delivery log viewer with response codes + retry button

**Dependencies:** None (independent)
**Parallelizable:** ✅ Yes
**Effort:** L (large)

**Status:** ✅ Done (`feature/G21`). `webhooks` + `webhook_deliveries` tables with
`BelongsToTenant`; `WebhookService` dispatches HMAC-signed jobs on a `webhooks` queue;
`WebhookServiceProvider` listens to `SaleCompleted`, `SaleReturned`, `InventoryLow` events
and `Customer::created` model event; `WebhookController` CRUD + delivery log + retry;
`WebhookTest` (12 tests) covers creation, dispatch, HMAC, permissions, retry. Frontend:
Settings → Webhooks card + create drawer + delivery log viewer with payload expansion and
retry; i18n in en/de/bn/ar/ja.

---

### G28 · Per-User Theme Sync
**Backend:** `users.theme` JSON column (`{mode, palette}`) + included in `auth/me`
response + `PATCH /v1/auth/preferences`
**Frontend:** theme store hydrates from `me` after login; changes PATCH back (debounced);
localStorage stays as offline fallback

**Status:** ✅ Implemented — `users.theme` JSON column, `theme` on the user Resource,
`PATCH /v1/auth/preferences` (no `permission:` guard: self-scoped to `$request->user()`),
values validated against `App\Support\Theme\ThemeCatalog`. Frontend: `useThemeStore`
gained `applyServerTheme`/`markPushed`, `ThemeSync` hydrates from the `me` payload and
PATCHes back on an 800 ms debounce; localStorage remains the pre-auth/offline fallback read
by the pre-paint script in `app/layout.tsx`. Tests: `tests/Feature/Api/ThemePreferenceTest.php`
+ `web/src/stores/__tests__/theme.test.ts`.

**Dependencies:** None (independent)
**Parallelizable:** ✅ Yes
**Effort:** S (small)

---

## Backlog — Not Yet Scheduled (P4)

| ID | Item | Sides | Notes | Depends On |
|----|------|-------|-------|------------|
| G26 | Delivery management | both, XL | zones, drivers, tracking, public status page | None |
| G27 | Customer order-status page | FE + token endpoint, M | `/order/{token}` public status + email hook | None |
| G32 | Email template editor | both, M | `email_templates` table + variables + preview | None |
| G35 | API versioning headers | BE, S | `X-API-Version`, Sunset headers, changelog doc | None |
| G36 | Multi-currency | both, XL | rate service, dual display, FX transactions | None |
| G37 | Scale/weight products | both, L | WebSerial scale integration, per-kg pricing | None |
| G38 | Barcode label printing | FE, M | JS barcode render + label templates + print | None |
| G39 | AI features (pick one) | both, XL | forecasting / NL reports / receipt OCR (T99) | None |
| G40 | Mobile POS layout | FE, M | bottom-sheet cart, 48px targets, swipe gestures | None |
| G41 | True offline sync (T98) | both, XL | IDB catalog cache, sale queue, `POST /sales/sync` batch | None |
| G42 | Customer portal | both, XL | login, history, dues pay (needs G20), invoices | G20 |
| G43 | Campaigns/SMS/email | both, XL | segments, Twilio, analytics | None |
| G44 | Branch/customer-group pricing | both, M | `product_prices` UI + resolver priority | None |
| G89 | Manager approval PIN at POS (T89) | both, S | supervisor PIN challenge for discount/override | None |
| G90 | Security settings page (T90) | FE, S | one surface for 2FA/sessions/lockout (mostly exists) | None |
| G92 | Stocktake / cycle count (T92) | both, M | count sheets, variance journal | None |
| G93 | Reorder suggestions → draft PO (T93) | both, M | velocity + lead time → G14 draft | G14 |
| G95 | ESC/POS hardware (T95) | FE, L | WebUSB printers, drawer kick, label printing | None |

---

## Cross-Cutting Checklist (every task, every PR)

**Backend**
- [ ] `BelongsToTenant` on every new model · `tenant_id` + index on every new table
- [ ] Route inside `auth:sanctum, tenant, ensure.tenant` group · `permission:` middleware
- [ ] Permission slugs in `PermissionCatalog::MATRIX` + default-role grants
- [ ] FormRequest validation (tenant-scoped `exists`) · Resource response shaping
- [ ] Money via `Money` value object · stock only via `StockLedgerService` · journals via `JournalService`/`TradingJournalService`
- [ ] Idempotency middleware on all money-moving POSTs
- [ ] `Auditable` trait or explicit audit log on mutations
- [ ] Feature test + `php artisan test` + Pint + `docs/API.md` + `docs/PROGRESS.md`

**Frontend**
- [ ] API via `src/lib/api.ts` (`api`, `apiBlob`, `apiUpload` for multipart)
- [ ] Query keys in `src/lib/query-keys.ts` · invalidation after mutations
- [ ] Loading/empty/error states · confirm-on-destructive · toast feedback
- [ ] `useAuth().can()` gating on every gated control
- [ ] i18n: en + de/bn/ar/ja keys (RTL verified when layout-affecting)
- [ ] `bun run lint` + `bun run build` + unit test for new logic-heavy components
- [ ] New pages registered in `src/lib/nav.ts` with icons + group

---

## Parallel Execution Matrix

### Wave 1 — Fully Independent (start immediately)
```
┌─────────────────────────────────────────────────────────────────┐
│  G1 (Receipts)    G5 (Shortcuts)   G6 (Hold/Park)   G8 (Numpad) │
│  G3 (Dashboard)   G18 (Invoice#)   G9 (Onboarding)  G16 (Shifts)│
│  G21 (Webhooks)   G28 (Theme)      G30 (Backups)    G34 (Obs)   │
│  S1,S3-S6,S8-S15 (Simplicity batch — most items)                │
└─────────────────────────────────────────────────────────────────┘
```

### Wave 2 — After Wave 1 starts (some Wave 1 items may still be finishing)
```
┌─────────────────────────────────────────────────────────────────┐
│  G2 (Barcode)      G4 (Images)      G7 (Customer)    G17 (Journals)│
│  G13 (Z-Report)    G14 (PO Work)    G10 (CSV Import) G23 (Kitchen)│
│  G24 (Tables)      G25 (Modifiers)  G29 (Cache)      G31 (GDPR)  │
└─────────────────────────────────────────────────────────────────┘
```

### Wave 3 — After Wave 2 (dependent items)
```
┌─────────────────────────────────────────────────────────────────┐
│  S2 (Thumbnails → G4)    S7 (Search → G2)                      │
│  G12 (Grid → G4+G8)      G22 (P&L → G17)                      │
│  G15 (AP → G17)          G19 (Purchase Ret → G14)              │
│  G11 (Exports → G10)     G33 (Tests → G17)                     │
└─────────────────────────────────────────────────────────────────┘
```

### Wave 4 — Revenue & Growth (after Wave 2-3)
```
┌─────────────────────────────────────────────────────────────────┐
│  G20 (Stripe — 3 phases)                                        │
└─────────────────────────────────────────────────────────────────┘
```

---

## Progress Tracker

| Sprint | Item | Backend | Frontend | Status | Issue |
|--------|------|---------|----------|--------|-------|
| 1 | G1 Receipt/invoice downloads | — (exists) | sales + cash-register buttons | ⬜ | [#48](https://github.com/AnwarHossainSR/smartpos-pro/issues/48) |
| 1 | G2 Barcode hardening | barcode column + search | scanner timing + error UX | ⬜ | [#49](https://github.com/AnwarHossainSR/smartpos-pro/issues/49) |
| 1 | G5 Keyboard shortcuts | — | hooks + overlay | ⬜ | [#50](https://github.com/AnwarHossainSR/smartpos-pro/issues/50) |
| 1 | G6 Hold/park UI | — (exists) | park + panel + resume | ⬜ | [#51](https://github.com/AnwarHossainSR/smartpos-pro/issues/51) |
| 1 | G8 Numpad | — | ui primitive + POS wiring | ⬜ | [#52](https://github.com/AnwarHossainSR/smartpos-pro/issues/52) |
| 1 | S1–S15 simplicity batch | 2 small fields | 15 fixes | ⬜ | [#41](https://github.com/AnwarHossainSR/smartpos-pro/issues/41) |
| 2 | G3 Dashboard v2 | 5 new summary fields | full page rebuild | ⬜ | [#53](https://github.com/AnwarHossainSR/smartpos-pro/issues/53) |
| 2 | G4 Product images | upload endpoints | upload component + tiles | ⬜ | [#54](https://github.com/AnwarHossainSR/smartpos-pro/issues/54) |
| 2 | G7 Customer quick-attach | search + resource fields | phone-first UI | ⬜ | [#55](https://github.com/AnwarHossainSR/smartpos-pro/issues/55) |
| 2 | G12 Grid view + favorites | — | grid + tabs + favorites | ⬜ | [#56](https://github.com/AnwarHossainSR/smartpos-pro/issues/56) |
| 3 | G17 Auto-journals (T117) | TradingJournalService + wiring | settings toggle | ⬜ | [#57](https://github.com/AnwarHossainSR/smartpos-pro/issues/57) |
| 3 | G18 Invoice numbering (T118) | sequence + backfill | — (QA) | ⬜ | [#58](https://github.com/AnwarHossainSR/smartpos-pro/issues/58) |
| 3 | G22 P&L + balance sheet (T109) | statements + PDFs | report tabs + layout | ⬜ | [#59](https://github.com/AnwarHossainSR/smartpos-pro/issues/59) |
| 3 | G13 Z-report | endpoint + PDF | close flow + report tab | ⬜ | [#60](https://github.com/AnwarHossainSR/smartpos-pro/issues/60) |
| 4 | G14 PO workflow | service split + receipts | status + receive modal | ⬜ | [#61](https://github.com/AnwarHossainSR/smartpos-pro/issues/61) |
| 4 | G15 Supplier payments (T94) | payments + aging + statement | payment modal + tabs | ⬜ | [#62](https://github.com/AnwarHossainSR/smartpos-pro/issues/62) |
| 4 | G19 Purchase returns | return endpoints | return modal + tab | ⬜ | [#63](https://github.com/AnwarHossainSR/smartpos-pro/issues/63) |
| 4 | G10 CSV import (T107) | importer + template | import wizard | ⬜ | [#64](https://github.com/AnwarHossainSR/smartpos-pro/issues/64) |
| 4 | G11 Server exports (T108) | export trait + 7 routes | toolbar swap | ⬜ | [#65](https://github.com/AnwarHossainSR/smartpos-pro/issues/65) |
| 5 | G20 Stripe (T91) | 3 phases + webhooks | pay page + portal + terminal | ⬜ | [#66](https://github.com/AnwarHossainSR/smartpos-pro/issues/66) |
| 5 | G9 Onboarding wizard | state column + endpoint | wizard component | ⬜ | [#67](https://github.com/AnwarHossainSR/smartpos-pro/issues/67) |
| 5 | G16 Shifts/time clock | shifts + report | header pill + team tab | ⬜ | [#68](https://github.com/AnwarHossainSR/smartpos-pro/issues/68) |
| 6 | G23 Kitchen display | status + token + event | /kitchen kanban | ⬜ | [#69](https://github.com/AnwarHossainSR/smartpos-pro/issues/69) |
| 6 | G24 Tables | tables CRUD + link | floor plan page | ⬜ | [#70](https://github.com/AnwarHossainSR/smartpos-pro/issues/70) |
| 6 | G25 Modifiers + split | schema + line builder | sheets + split mode | ⬜ | [#73](https://github.com/AnwarHossainSR/smartpos-pro/issues/73) |
| 7 | G29 Cache layer | cache + invalidation | — | ⬜ | [#71](https://github.com/AnwarHossainSR/smartpos-pro/issues/71) |
| 7 | G30 Backups | backup schedule + S3 | — | ⬜ | [#72](https://github.com/AnwarHossainSR/smartpos-pro/issues/72) |
| 7 | G31 Retention & GDPR | prune + anonymize | settings card | ⬜ | [#74](https://github.com/AnwarHossainSR/smartpos-pro/issues/74) |
| 7 | G33 Critical test suite | E2E + concurrency | — | ⬜ | [#75](https://github.com/AnwarHossainSR/smartpos-pro/issues/75) |
| 7 | G34 Observability | Sentry + health | error boundary | ⬜ | [#76](https://github.com/AnwarHossainSR/smartpos-pro/issues/76) |
| 7 | G21 Webhooks (T110) | webhook service + CRUD | settings card | ⬜ | [#77](https://github.com/AnwarHossainSR/smartpos-pro/issues/77) |
| 7 | G28 Per-user theme sync | theme column + API | theme store | ✅ | [#78](https://github.com/AnwarHossainSR/smartpos-pro/issues/78) |
