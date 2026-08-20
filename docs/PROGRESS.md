# SmartPOS Pro — Progress

Task definitions live in [`REQUIREMENTS.md`](REQUIREMENTS.md). One line per task, ticked
when the task's **Done when** holds and the checks in [`../CLAUDE.md`](../CLAUDE.md) pass.

## Phases 1–10 — shipped

Sprints 1–7 are on `dev`: multi-tenancy, RBAC, catalog, POS + offline, inventory, sales &
returns, purchasing, expenses, accounting (incl. P&L and balance sheet), cash register,
Z-report, shifts, loyalty & coupons, tax engine, quotations, stock transfers, variants /
batches / serials, sale void, CSV product import, webhooks, Stripe payments, onboarding
wizard, subscriptions, i18n + palettes, PWA.

Both Phase 10 stragglers — **T105** (purchase edit / cancel / return) and **T108**
(server-side exports) — landed in sprint 11 below and now have routes.

## Phase 11 — backlog sprints

### Sprint 8 — Customer touchpoints & shop-floor polish (P2)

- [x] T111 · G27 Customer order-status page
- [x] T112 · G32 Email template editor
- [x] T126 · Catalog & structure admin UI (categories, brands, units, branches, warehouses)
- [x] T113 · G38 Barcode label printing
- [x] T114 · G89 Manager approval PIN at POS

### Sprint 9 — Scale & operations (P3)

- [x] T115 · G36 Multi-currency
- [x] T116 · G37 Scale / weight products
- [x] T117 · G92 Stocktake / cycle count
- [x] T118 · G93 Reorder suggestions → draft PO

### Sprint 10 — Customer & hardware (P2/P3)

- [x] T119 · G40 Mobile POS layout
- [x] T120 · G42 Customer portal
- [x] T121 · G95 ESC/POS hardware
- [x] T122 · G90 Security settings page

### Sprint 11 — Long tail & unfinished lanes (P2/P3)

- [x] T123 · G26 Delivery management
- [x] T124 · G39 AI demand forecasting
- [x] T125 · G24 Tables & floor plan (completion)
- [x] T105 · G19 Purchase edit / cancel / return
- [x] T108 · G11 Server-side export endpoints

## Media pipeline & SEO

Two pieces of production plumbing that had no owner before.

**Object storage.** Images used to be written straight to the local `public` disk by
`ProductController`, which does not survive a container restart and cannot be shared
between app instances. Uploads now go through `App\Services\Media\MediaService` onto
Cloudflare R2 (`MEDIA_DISK=r2`; local and CI stay on `public`, so neither needs
credentials). Every upload is re-encoded to WebP at the sizes declared in
`config/media.php` — which normalises the format, caps storage, strips EXIF, and means
the bytes served are never the bytes uploaded. SVG is sanitised against an allowlist
instead, since it cannot be re-encoded and is otherwise a stored-XSS vector.

Keys are `media/{tenant_id}/{collection}/{ulid}/{variant}.webp` — tenant-first, so one
tenant's media cannot collide with another's and the set is removable on account
closure. Read-time URL building does **no** existence probing: a HEAD per image per row
would turn a catalogue page into hundreds of round trips.

**Branding.** A business now has five image slots — `logo`, `logo-dark`, `favicon`,
`receipt-logo`, `cover` — declared once in `App\Support\Media\BusinessMediaSlots` and
read by the router, the upload request, the controller, and the settings payload, so they
cannot drift. Brands and user avatars use the same pipeline. Columns are folded into the
existing create migrations, per the reset-the-DB convention.

`PUT /settings/business` no longer accepts `logo_path`: a client could otherwise point a
business at an object it does not own. Paths are only ever set by an upload.

**SEO.** Every route now carries real metadata, driven from `web/src/lib/seo.ts`. The
public surface (`/`, `/login`, `/register`) is indexable with canonicals, OG/Twitter cards,
a generated OG image, and JSON-LD (`Organization`, `WebSite`, `SoftwareApplication`, plus
a `FAQPage` built from the same array the page renders — schema describing invisible
content is cloaking, not optimisation).

The security-relevant half is the exclusions. `/order/*` and `/portal/*` need no login but
are addressed by a per-record token and render a named customer's purchases; they were in
neither the robots disallow list nor carrying `noindex`. Both now do — belt and braces,
because robots.txt is a request and does nothing about a URL pasted somewhere crawlable.
`/reset-password` and `/verify-email` are noindex for the same reason: their URLs carry
single-use tokens.

For agents specifically there is `/llms.txt`, which states what the product is, what it
is not, and which paths not to fetch or cite. AI crawlers are allowed on the same public
surface as search crawlers and held to the same exclusions.

## Post-sprint-11 audit

Sprint 11's last five tasks were ticked before their endpoints actually worked. A backend
audit pass fixed them and brought this file and [`API.md`](API.md) back in line with the
routes that exist:

- `routes/api.php` was left mangled by commit `83281f3`: four live `sales/{sale}/*` routes
  (payments, email-status-link, resume, void) deleted, the `StripeController` import
  dropped, and every em dash in the file corrupted to mojibake. Restored and repaired.
- **T108 exports** never worked: the route was shadowed by `products/{product}` and
  friends, `ExportController` had a duplicate property (fatal), and `ExportService`
  stripped the tenant scope. Exports are now tenant-scoped, correctly ordered, and honour
  the same filters as each module's index endpoint.
- **T123 deliveries**, **T124 forecasts** and **T125 tables** each shipped with a fatal
  defect (missing `deliveries` table, unimported `Product`, and a model pointing at a
  non-existent `tables` table respectively). All three now have passing feature tests.
- `GET /reports/z-report` now routes to the layered `ZReportController`; the duplicate
  copy on `ReportController` (whose `branch_id` was not tenant-scoped) is gone.

- The T125 floor plan collected a table selection the API then discarded: `POST /sales`
  now validates and persists `table_id` (tenant-scoped) on both checkout and park, and
  `SaleResource` returns it.

`tables.*` and `deliveries.*` are now in `PermissionCatalog::MATRIX`, so these endpoints
are reachable by roles other than owner — `cashier` holds `tables.view`/`tables.update`
for seating and clearing, but not `create`/`delete`.

