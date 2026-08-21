# Super Admin UI Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a complete super admin frontend with tenant management, revenue dashboard, and impersonation — routing super admins to a dedicated `/super-admin` section after login.

**Architecture:** Super admins get their own route namespace (`/super-admin/*`) with a dedicated sidebar nav, reusing the existing `AppShell` layout pattern. The feature module follows the established `features/super-admin/` pattern (types, api, hooks, components). The login page redirects super admins to `/super-admin` instead of `/dashboard`.

**Tech Stack:** React 19, React Router 8, TanStack Query, Zustand, react-hook-form + Zod, Tailwind v4, Lucide icons, existing UI primitives (DataTable, ListToolbar, Pagination, Dialog, Drawer, Card, StatCard, Badge).

## Global Constraints

- TypeScript strict mode
- Tailwind v4 with semantic tokens from `globals.css`
- API calls through `src/lib/api.ts` — never raw fetch
- Cache keys through `src/lib/query-keys.ts` — never inline arrays
- Every list: search + filter + pagination + empty + loading + error states
- Every mutation: validation + confirm-on-destructive + toast
- Permission checks via `useAuth().can()` where applicable
- No emojis in code or UI

---

## File Structure

```
web/src/
  types/domain.ts                           # Add Tenant, Impersonation, RevenueMetrics
  lib/query-keys.ts                         # Add superAdmin keys
  lib/permissions.ts                        # Add super-admin route permissions
  lib/nav.ts                                # Add SUPER_ADMIN_NAV_GROUPS
  stores/auth.ts                            # (no change needed — is_super_admin already handled)
  routes/index.tsx                          # Add super-admin route group
  features/super-admin/
    types.ts                                # Entity interfaces
    api.ts                                  # Typed fetchers for /super/* endpoints
    hooks.ts                                # React Query hooks
    index.ts                                # Barrel export
    components/
      tenant-list.tsx                       # Tenant list page content
      tenant-detail-drawer.tsx              # Tenant detail drawer
      revenue-dashboard.tsx                 # Revenue metrics page content
      impersonations-list.tsx               # Active impersonations list
  app/(app)/super-admin/
    layout.tsx                              # Super admin layout wrapper (reuses AppShell)
    tenants/page.tsx                        # Tenant management page
    revenue/page.tsx                        # Revenue dashboard page
    impersonations/page.tsx                 # Active impersonations page
  components/layout/app-shell.tsx           # Update: super admin redirect after /auth/me
  app/(auth)/login/page.tsx                 # Update: redirect super admins to /super-admin
```

---

### Task 1: Add TypeScript types for super admin entities

**Files:**
- Modify: `web/src/types/domain.ts`

**Interfaces:**

```typescript
// Add to web/src/types/domain.ts

export interface Tenant extends Named {
  slug: string;
  status: "active" | "suspended";
  created_at: string;
  updated_at: string;
  users_count?: number;
  businesses_count?: number;
}

export interface TenantDetail extends Tenant {
  subscription: {
    id: string;
    status: string;
    plan: { id: string; name: string; slug: string; price: number } | null;
    created_at: string;
  } | null;
  usage: {
    sales_count: number;
    users_count: number;
  };
}

export interface PlanDistribution {
  plan_name: string;
  slug: string;
  count: number;
  mrr: number;
}

export interface RevenueMetrics {
  total_mrr: number;
  active_tenants: number;
  suspended_tenants: number;
  new_tenants_last_days: number;
  plan_distribution: PlanDistribution[];
  recent_subscriptions: Array<{
    id: string;
    status: string;
    created_at: string;
    plan: { name: string; slug: string; price: number } | null;
  }>;
}

export interface Impersonation {
  id: string;
  tenant_id: string;
  super_admin_id: string;
  target_user_id: string;
  reason: string;
  started_at: string;
  ended_at: string | null;
  ended_by: string | null;
  tenant?: { id: string; name: string };
  superAdmin?: { id: string; name: string; email: string };
  targetUser?: { id: string; name: string; email: string };
}
```

- [ ] **Step 1:** Add the interfaces to `web/src/types/domain.ts`
- [ ] **Step 2:** Run `npx tsc --noEmit` to verify no type errors
- [ ] **Step 3:** Commit: `feat(admin): add super admin TypeScript types`

---

### Task 2: Add query keys for super admin resources

**Files:**
- Modify: `web/src/lib/query-keys.ts`

Add at the end of the `queryKeys` object (before the closing `} as const`):

```typescript
  /* ---- super admin ---- */
  superAdmin: {
    all: ["super-admin"] as const,
    tenants: (params?: ListParams) => ["super-admin", "tenants", params ?? {}] as const,
    tenantDetail: (id: string | undefined) => ["super-admin", "tenant", id ?? ""] as const,
    revenueMetrics: (days?: number) => ["super-admin", "revenue", days ?? 30] as const,
    impersonations: () => ["super-admin", "impersonations"] as const,
  },
```

- [ ] **Step 1:** Add the `superAdmin` key block to `query-keys.ts`
- [ ] **Step 2:** Run `npx tsc --noEmit` to verify
- [ ] **Step 3:** Commit: `feat(admin): add super admin query keys`

---

### Task 3: Add super admin feature module (types, api, hooks)

**Files:**
- Create: `web/src/features/super-admin/types.ts`
- Create: `web/src/features/super-admin/api.ts`
- Create: `web/src/features/super-admin/hooks.ts`
- Create: `web/src/features/super-admin/index.ts`

#### `features/super-admin/types.ts`
Re-export from domain types:
```typescript
export type { Tenant, TenantDetail, RevenueMetrics, Impersonation, PlanDistribution } from "@/types/domain";
```

#### `features/super-admin/api.ts`
```typescript
import { api, type Paginated } from "@/lib/api";
import { buildListQuery } from "@/lib/use-resource-list";
import type { Tenant, TenantDetail, RevenueMetrics, Impersonation } from "./types";

export const superAdminApi = {
  tenants: {
    list: (params: Record<string, unknown>) =>
      api<Paginated<Tenant>>(`/super/tenants?${buildListQuery(params)}`),
    detail: (id: string) =>
      api<{ data: TenantDetail }>(`/super/tenants/${id}`),
    suspend: (id: string) =>
      api<{ data: Tenant }>(`/super/tenants/${id}/suspend`, { method: "POST" }),
    activate: (id: string) =>
      api<{ data: Tenant }>(`/super/tenants/${id}/activate`, { method: "POST" }),
    remove: (id: string) =>
      api<void>(`/super/tenants/${id}`, { method: "DELETE" }),
    impersonate: (id: string, reason: string) =>
      api<{ data: { impersonation_id: string; token: string; tenant: { id: string; name: string }; user: { id: string; name: string; email: string }; expires_at: string } }>(
        `/super/tenants/${id}/impersonate`,
        { method: "POST", body: { reason } },
      ),
  },
  revenue: {
    metrics: (days = 30) =>
      api<{ data: RevenueMetrics }>(`/super/metrics/revenue?days=${days}`),
  },
  impersonations: {
    list: () =>
      api<{ data: Impersonation[] }>("/super/impersonations"),
    revoke: (id: string) =>
      api<{ message: string }>(`/super/impersonation/${id}/revoke`, { method: "POST" }),
  },
};
```

#### `features/super-admin/hooks.ts`
```typescript
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "@/components/ui/toast";
import { useResourceList } from "@/lib/use-resource-list";
import { queryKeys } from "@/lib/query-keys";
import { superAdminApi } from "./api";

export function useTenants() {
  return useResourceList({
    queryKey: queryKeys.superAdmin.tenants(),
    queryFn: superAdminApi.tenants.list,
    defaultSort: "-created_at",
    filterKeys: ["status"] as const,
  });
}

export function useRevenueMetrics(days = 30) {
  return queryKeys.superAdmin.revenueMetrics(days);
}

export function useTenantMutations() {
  const qc = useQueryClient();

  const suspend = useMutation({
    mutationFn: superAdminApi.tenants.suspend,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: queryKeys.superAdmin.all });
      toast("Tenant suspended", "success");
    },
  });

  const activate = useMutation({
    mutationFn: superAdminApi.tenants.activate,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: queryKeys.superAdmin.all });
      toast("Tenant activated", "success");
    },
  });

  const remove = useMutation({
    mutationFn: superAdminApi.tenants.remove,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: queryKeys.superAdmin.all });
      toast("Tenant deleted", "success");
    },
  });

  const impersonate = useMutation({
    mutationFn: ({ id, reason }: { id: string; reason: string }) =>
      superAdminApi.tenants.impersonate(id, reason),
    onSuccess: () => {
      toast("Impersonation session started", "success");
    },
  });

  return { suspend, activate, remove, impersonate };
}

export function useImpersonationMutations() {
  const qc = useQueryClient();

  const revoke = useMutation({
    mutationFn: superAdminApi.impersonations.revoke,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: queryKeys.superAdmin.impersonations() });
      toast("Impersonation revoked", "success");
    },
  });

  return { revoke };
}
```

#### `features/super-admin/index.ts`
```typescript
export * from "./types";
export * from "./api";
export * from "./hooks";
```

- [ ] **Step 1:** Create all four files with the content above
- [ ] **Step 2:** Run `npx tsc --noEmit` to verify
- [ ] **Step 3:** Commit: `feat(admin): add super admin feature module (types, api, hooks)`

---

### Task 4: Build the tenant management page

**Files:**
- Create: `web/src/features/super-admin/components/tenant-list.tsx`
- Create: `web/src/app/(app)/super-admin/tenants/page.tsx`

#### `features/super-admin/components/tenant-list.tsx`
Follow the `customers/page.tsx` pattern: `useTenants()`, `DataTable`, `ListToolbar`, `Pagination`, `Dialog` for delete/suspend, `Drawer` for tenant detail.

Key columns:
- Name (with slug subtitle)
- Status (Badge: success for active, danger for suspended)
- Users count
- Businesses count
- Created at (formatted date)
- Actions: View detail, Suspend/Activate, Impersonate, Delete

The detail drawer loads `superAdminApi.tenants.detail(id)` showing subscription info and usage stats.

Impersonation flow: Dialog with reason input (min 10 chars), calls `impersonate` mutation, opens the impersonation token in a new tab.

#### `app/(app)/super-admin/tenants/page.tsx`
```typescript
import { TenantList } from "@/features/super-admin/components/tenant-list";

export default function SuperAdminTenantsPage() {
  return <TenantList />;
}
```

- [ ] **Step 1:** Create `tenant-list.tsx` with full DataTable implementation
- [ ] **Step 2:** Create the page wrapper
- [ ] **Step 3:** Run `npx tsc --noEmit` and `npm run lint`
- [ ] **Step 4:** Commit: `feat(admin): tenant management page`

---

### Task 5: Build the revenue dashboard page

**Files:**
- Create: `web/src/features/super-admin/components/revenue-dashboard.tsx`
- Create: `web/src/app/(app)/super-admin/revenue/page.tsx`

#### `features/super-admin/components/revenue-dashboard.tsx`
Uses `useQuery` with `queryKeys.superAdmin.revenueMetrics(days)`.

Layout:
- Row 1: 4 StatCards — Total MRR, Active Tenants, Suspended Tenants, New Tenants (last 30 days)
- Row 2: Plan distribution table (plan name, tenant count, MRR contribution)
- Row 3: Recent subscriptions list

Period selector: buttons for 7d / 30d / 90d that change the `days` parameter.

#### `app/(app)/super-admin/revenue/page.tsx`
```typescript
import { RevenueDashboard } from "@/features/super-admin/components/revenue-dashboard";

export default function SuperAdminRevenuePage() {
  return <RevenueDashboard />;
}
```

- [ ] **Step 1:** Create `revenue-dashboard.tsx` with StatCards and tables
- [ ] **Step 2:** Create the page wrapper
- [ ] **Step 3:** Run `npx tsc --noEmit` and `npm run lint`
- [ ] **Step 4:** Commit: `feat(admin): revenue dashboard page`

---

### Task 6: Build the impersonations page

**Files:**
- Create: `web/src/features/super-admin/components/impersonations-list.tsx`
- Create: `web/src/app/(app)/super-admin/impersonations/page.tsx`

#### `features/super-admin/components/impersonations-list.tsx`
Uses `useQuery` with `queryKeys.superAdmin.impersonations()`.

Columns:
- Tenant name
- Target user (name + email)
- Started by (super admin name + email)
- Reason
- Started at
- Status (active / ended)
- Actions: Revoke (if active)

Confirm dialog before revoking.

#### `app/(app)/super-admin/impersonations/page.tsx`
```typescript
import { ImpersonationsList } from "@/features/super-admin/components/impersonations-list";

export default function SuperAdminImpersonationsPage() {
  return <ImpersonationsList />;
}
```

- [ ] **Step 1:** Create `impersonations-list.tsx`
- [ ] **Step 2:** Create the page wrapper
- [ ] **Step 3:** Run `npx tsc --noEmit` and `npm run lint`
- [ ] **Step 4:** Commit: `feat(admin): impersonations list page`

---

### Task 7: Add super admin navigation and route permissions

**Files:**
- Modify: `web/src/lib/nav.ts`
- Modify: `web/src/lib/permissions.ts`

#### `nav.ts` — Add super admin nav groups
Add after the existing `NAV_GROUPS`:
```typescript
import { Building2, DollarSign, Users, Shield } from "lucide-react";

export const SUPER_ADMIN_NAV_GROUPS: NavGroup[] = [
  {
    key: "superAdmin",
    groupIcon: Shield,
    items: [
      { href: "/super-admin/tenants", key: "tenants", icon: Building2 },
      { href: "/super-admin/revenue", key: "revenue", icon: DollarSign },
      { href: "/super-admin/impersonations", key: "impersonations", icon: Users },
    ],
  },
];

export const SUPER_ADMIN_NAV_ITEMS: NavItem[] = SUPER_ADMIN_NAV_GROUPS.flatMap((g) => g.items);

export function superAdminNavKeyForPathname(pathname: string): string | undefined {
  return SUPER_ADMIN_NAV_ITEMS.find(
    (item) => pathname === item.href || pathname.startsWith(`${item.href}/`),
  )?.key;
}
```

#### `permissions.ts` — Add route permissions
Add to `ROUTE_TABLE`:
```typescript
{ prefix: "/super-admin/tenants", any: [] },
{ prefix: "/super-admin/revenue", any: [] },
{ prefix: "/super-admin/impersonations", any: [] },
{ prefix: "/super-admin", any: [] },
```

(Using `any: []` because the backend `super_admin` middleware already enforces access; the frontend just needs the routes to not be blocked by `RequirePermission`.)

- [ ] **Step 1:** Add `SUPER_ADMIN_NAV_GROUPS` and helpers to `nav.ts`
- [ ] **Step 2:** Add super-admin route entries to `permissions.ts`
- [ ] **Step 3:** Run `npx tsc --noEmit`
- [ ] **Step 4:** Commit: `feat(admin): super admin navigation and route permissions`

---

### Task 8: Add super admin routes to the router

**Files:**
- Modify: `web/src/routes/index.tsx`

Add inside the `AppShell` children array (after the existing app routes, before the portal routes):

```typescript
// Super admin routes
{
  path: "super-admin",
  children: [
    {
      index: true,
      lazy: () => loadPage(() => import("app/(app)/super-admin/tenants/page")),
      handle: seo("Super Admin", "Platform administration"),
    },
    {
      path: "tenants",
      lazy: () => loadPage(() => import("app/(app)/super-admin/tenants/page")),
      handle: seo("Tenants", "Manage platform tenants"),
    },
    {
      path: "revenue",
      lazy: () => loadPage(() => import("app/(app)/super-admin/revenue/page")),
      handle: seo("Revenue", "Revenue metrics and analytics"),
    },
    {
      path: "impersonations",
      lazy: () => loadPage(() => import("app/(app)/super-admin/impersonations/page")),
      handle: seo("Impersonations", "Active impersonation sessions"),
    },
  ],
},
```

- [ ] **Step 1:** Add the route block to `routes/index.tsx`
- [ ] **Step 2:** Run `npx tsc --noEmit`
- [ ] **Step 3:** Commit: `feat(admin): add super admin routes to router`

---

### Task 9: Create super admin layout with dedicated sidebar

**Files:**
- Create: `web/src/app/(app)/super-admin/layout.tsx`
- Modify: `web/src/components/layout/sidebar.tsx`

#### `app/(app)/super-admin/layout.tsx`
A layout component that wraps `<Outlet />` with a super-admin-specific sidebar. Since the existing `AppShell` already handles auth and the shell, this layout just provides the super admin nav context.

Actually, a simpler approach: modify `Sidebar` to accept a `groups` prop and have `AppShell` pass the correct groups based on `user.is_super_admin`.

#### Modify `sidebar.tsx`
Update `SidebarContent` to accept `navGroups` prop:
```typescript
function SidebarContent({ onClose, collapsed, navGroups }: { onClose?: void; collapsed: boolean; navGroups?: NavGroup[] }) {
  const groups = navGroups ?? useVisibleNavGroups();
  // ... rest stays the same
}
```

Update `Sidebar` to accept `navGroups`:
```typescript
export function Sidebar({ open, onClose, navGroups }: SidebarProps & { navGroups?: NavGroup[] }) {
  // ...
  <SidebarContent collapsed={collapsed} navGroups={navGroups} />
}
```

#### Modify `app-shell.tsx`
```typescript
import { SUPER_ADMIN_NAV_GROUPS, superAdminNavKeyForPathname } from "@/lib/nav";

// In AppShell, after user is loaded:
const isSuperAdmin = user?.is_super_admin ?? false;
const navGroups = isSuperAdmin ? SUPER_ADMIN_NAV_GROUPS : undefined;
const navKey = isSuperAdmin
  ? superAdminNavKeyForPathname(pathname)
  : navKeyForPathname(pathname);

// Pass to Sidebar:
<Sidebar open={sidebarOpen} onClose={() => setSidebarOpen(false)} navGroups={navGroups} />
```

- [ ] **Step 1:** Update `Sidebar` to accept optional `navGroups` prop
- [ ] **Step 2:** Update `AppShell` to pass super admin nav when user is super admin
- [ ] **Step 3:** Run `npx tsc --noEmit` and `npm run lint`
- [ ] **Step 4:** Commit: `feat(admin): super admin sidebar navigation`

---

### Task 10: Update login redirect for super admins

**Files:**
- Modify: `web/src/app/(auth)/login/page.tsx`

Update the login success handler to redirect super admins to `/super-admin`:

```typescript
// In onSubmit, after setAuth:
if (res.user.is_super_admin) {
  navigate("/super-admin", { replace: true });
} else if (res.user.email_verified_at) {
  navigate("/dashboard", { replace: true });
} else {
  navigate(`/verify-email?email=${encodeURIComponent(res.user.email)}`, { replace: true });
}
```

Also update `useEffect` for already-logged-in users:
```typescript
useEffect(() => {
  if (hydrated && token) {
    if (user?.is_super_admin) {
      navigate("/super-admin", { replace: true });
    } else {
      navigate("/dashboard", { replace: true });
    }
  }
}, [hydrated, token, user, navigate]);
```

Also update the 2FA challenge success redirect similarly.

- [ ] **Step 1:** Update `onSubmit` redirect logic
- [ ] **Step 2:** Update `useEffect` redirect logic
- [ ] **Step 3:** Update `onChallengeSubmit` redirect
- [ ] **Step 4:** Run `npx tsc --noEmit` and `npm run lint`
- [ ] **Step 5:** Commit: `feat(admin): redirect super admins to admin panel on login`

---

### Task 11: End-to-end verification

- [ ] **Step 1:** Run `npm run build` — verify no build errors
- [ ] **Step 2:** Run `npm run lint` — verify zero lint errors
- [ ] **Step 3:** Run `npx tsc --noEmit` — verify no type errors
- [ ] **Step 4:** Manual test: Login as super admin → should land on `/super-admin/tenants`
- [ ] **Step 5:** Manual test: Sidebar shows only super admin nav items
- [ ] **Step 6:** Manual test: Tenant list loads with search/filter/pagination
- [ ] **Step 7:** Manual test: Revenue dashboard shows MRR and stats
- [ ] **Step 8:** Manual test: Impersonation flow works (enter reason → get token → open in new tab)
- [ ] **Step 9:** Manual test: Suspend/Activate tenant works
- [ ] **Step 10:** Manual test: Regular user login still goes to `/dashboard` normally
- [ ] **Step 11:** Commit: `feat(admin): super admin UI complete`
