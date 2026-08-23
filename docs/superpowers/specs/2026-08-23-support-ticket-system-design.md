# Support Ticket System — Design Spec

## Overview

A full-featured, tenant-scoped support ticket system. Tenants submit and track tickets via a portal. Super admins manage all tickets across tenants with assignment, internal notes, priority, SLA tracking, and categories.

## Data Model

### `support_tickets` table

| Column | Type | Notes |
|--------|------|-------|
| id | uuid | PK |
| tenant_id | foreign | FK → tenants, nullable (super admin can create on behalf) |
| user_id | foreign | FK → users (creator) |
| subject | string | Required |
| message | text | Required, initial message |
| category | enum | `general`, `billing`, `technical`, `feature_request`, `bug_report` |
| priority | enum | `low`, `medium`, `high`, `urgent` |
| status | enum | `open`, `in_progress`, `waiting_on_customer`, `resolved`, `closed` |
| assigned_to | foreign | FK → users, nullable (super admin user id) |
| sla_due_at | timestamp | Nullable, calculated from priority |
| last_reply_at | timestamp | For SLA tracking |
| created_at | timestamp | |
| updated_at | timestamp | |
| deleted_at | soft-delete | |

### `ticket_replies` table

| Column | Type | Notes |
|--------|------|-------|
| id | uuid | PK |
| ticket_id | foreign | FK → support_tickets |
| user_id | foreign | FK → users (who replied) |
| message | text | Required |
| is_internal | boolean | Default false — internal notes visible only to super admin |
| created_at | timestamp | |

### `ticket_categories` table (optional, for future extensibility)

Skip for now — use the enum in `support_tickets.category`.

## API Endpoints

### Tenant-scoped (standard routes with `ensure.tenant`)

| Method | Path | Permission | Description |
|--------|------|-----------|-------------|
| GET | `/v1/support/tickets` | `any authenticated` | List own tickets (paginated, filterable by status/priority) |
| POST | `/v1/support/tickets` | `any authenticated` | Create ticket |
| GET | `/v1/support/tickets/{id}` | `any authenticated` | View ticket detail + replies |
| POST | `/v1/support/tickets/{id}/replies` | `any authenticated` | Reply to ticket |
| PUT | `/v1/support/tickets/{id}/close` | `any authenticated` | Close own resolved ticket |
| PUT | `/v1/support/tickets/{id}/reopen` | `any authenticated` | Reopen a resolved/closed ticket |

### Super-admin (under `/v1/super/`)

| Method | Path | Permission | Description |
|--------|------|-----------|-------------|
| GET | `/v1/super/support/tickets` | `super-admin.support.view` | List ALL tickets (cross-tenant, paginated, filterable) |
| GET | `/v1/super/support/tickets/{id}` | `super-admin.support.view` | View any ticket detail + replies |
| PUT | `/v1/super/support/tickets/{id}` | `super-admin.support.manage` | Update status, priority, assignment |
| POST | `/v1/super/support/tickets/{id}/replies` | `super-admin.support.manage` | Reply (visible to tenant) |
| POST | `/v1/super/support/tickets/{id}/notes` | `super-admin.support.manage` | Add internal note (not visible to tenant) |
| POST | `/v1/super/support/tickets/{id}/assign` | `super-admin.support.manage` | Assign to self or another admin |

## SLA Logic

Priority → response time SLA:
- `urgent`: 4 hours
- `high`: 8 hours
- `medium`: 24 hours
- `low`: 72 hours

`sla_due_at` = `created_at` + SLA duration. When a super admin replies, reset `last_reply_at`. If SLA passes without reply, mark as "breached" (store in a computed property, no new column needed — can compute from `sla_due_at` vs now).

## Models

### `SupportTicket` (App\Models\SupportTicket)
- Uses `BelongsToTenant` trait
- `user()` → BelongsTo User
- `assignedTo()` → BelongsTo User (nullable)
- `replies()` → HasMany TicketReply
- `repliesVisibleToTenant()` — returns non-internal replies
- Accessor `is_sla_breached` → bool

### `TicketReply` (App\Models\TicketReply)
- Uses `BelongsToTenant` trait (via ticket)
- `ticket()` → BelongsTo SupportTicket
- `user()` → BelongsTo User

## Frontend Pages

### Super Admin: `/super-admin/support`

- **Ticket list** — DataTable with columns: Subject, Tenant, Category, Priority (badge), Status (badge), Assigned to, Last Reply, Created, SLA Status
- Filters: status, priority, category, assigned_to, search
- Row click → opens ticket detail modal
- **Ticket detail modal** — 3 tabs:
  1. **Conversation** — thread of replies (tenant messages in one style, admin replies in another, internal notes highlighted)
  2. **Details** — category, priority, status, assignment, SLA info
  3. **Actions** — change status, change priority, assign to self, add internal note, reply
- **Nav item** — add to SUPER_ADMIN_NAV_GROUPS with `Headset` icon

### Tenant: `/support` (existing page, enhanced)

- **Replace** the current static form with a ticket list + create dialog
- **Ticket list** — shows own tickets with status badges
- **Create ticket** — form with subject, message, category, priority
- **Ticket detail** — view conversation, reply, close/reopen
- **Nav** — the existing Support link stays visible for tenants (was hidden for super admin)

## Permissions

Add to `PermissionCatalog`:
- `super-admin.support.view` — list and view any ticket
- `super-admin.support.manage` — reply, assign, change status/priority

Add to `ROUTE_TABLE` in `permissions.ts`:
- `/super-admin/support` → `["super-admin.support.view"]`

## Migration

Create `database/migrations/2026_08_23_000001_create_ticket_tables.php`:
- `support_tickets` table
- `ticket_replies` table
- Add indexes: `tenant_id`, `user_id`, `status`, `assigned_to`, `created_at`

## Testing

Feature tests:
- Tenant can create ticket
- Tenant can list own tickets
- Tenant can reply to own ticket
- Tenant cannot see other tenants' tickets
- Super admin can list all tickets
- Super admin can reply (visible to tenant)
- Super admin can add internal note (not visible to tenant)
- Super admin can change status/priority/assignment
- Non-authenticated user cannot access tickets
