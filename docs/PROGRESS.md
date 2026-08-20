# SmartPOS Pro — Progress

Task definitions live in [`REQUIREMENTS.md`](REQUIREMENTS.md). One line per task, ticked
when the task's **Done when** holds and the checks in [`../CLAUDE.md`](../CLAUDE.md) pass.

## Phases 1–10 — shipped

Sprints 1–7 are on `dev`: multi-tenancy, RBAC, catalog, POS + offline, inventory, sales &
returns, purchasing, expenses, accounting (incl. P&L and balance sheet), cash register,
Z-report, shifts, loyalty & coupons, tax engine, quotations, stock transfers, variants /
batches / serials, sale void, CSV product import, webhooks, Stripe payments, onboarding
wizard, subscriptions, i18n + palettes, PWA.

Two Phase 10 tasks never got routes and are carried into sprint 11 below: **T105**
(purchase edit / cancel / return) and **T108** (server-side exports).

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

- [ ] T119 · G40 Mobile POS layout
- [ ] T120 · G42 Customer portal
- [x] T121 · G95 ESC/POS hardware
- [ ] T122 · G90 Security settings page

### Sprint 11 — Long tail & unfinished lanes (P2/P3)

- [ ] T123 · G26 Delivery management
- [ ] T124 · G39 AI demand forecasting
- [ ] T125 · G24 Tables & floor plan (completion)
- [ ] T105 · G19 Purchase edit / cancel / return
- [ ] T108 · G11 Server-side export endpoints
