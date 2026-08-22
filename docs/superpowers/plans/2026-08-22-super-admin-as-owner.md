# Super Admin as Tenant Owner with Platform Permissions

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Make the super admin a normal tenant owner (with `tenant_id` and `owner` role) who additionally holds platform-level permissions for tenant management, impersonation, and revenue monitoring.

**Architecture:** The super admin keeps `is_super_admin = true` for bypass logic but also has a real tenant with the `owner` role. New `super-admin.*` permissions are added to `PermissionCatalog` and synced to the database. Backend routes use `permission:super-admin.*` middleware. Frontend shows super-admin nav items only when the user holds `super-admin.tenants.view`.

**Tech Stack:** Laravel 13 / PHP 8.4, Spatie Permission, React 19, Zustand, TanStack Query, React Router 8, Tailwind v4.

---

## Task 1: Add `super-admin` module to PermissionCatalog + frontend PERMISSION_MATRIX

**Files:**
- Modify: `app/Support/Rbac/PermissionCatalog.php` — add `'super-admin' => ['tenants.view', 'tenants.manage', 'impersonate']` to MATRIX
- Modify: `web/src/lib/permissions.ts` — add `"super-admin": ["tenants.view", "tenants.manage", "impersonate"]` to PERMISSION_MATRIX, update ROUTE_TABLE entries to use `super-admin.tenants.view` / `super-admin.impersonate`

## Task 2: Seed super admin as tenant owner

**Files:**
- Modify: `database/seeders/FullDemoSeeder.php` — add `createSuperAdmin()` method that uses `TenantProvisioner::register()` to create a real tenant, assigns `owner` role, sets `is_super_admin = true`

## Task 3: Backend — SuperAdminController + ImpersonationService + routes

**Files:**
- Create: `app/Services/SuperAdmin/ImpersonationService.php` — cache-based impersonation tokens
- Create: `app/Http/Resources/TenantResource.php` — tenant API resource
- Create: `app/Http/Controllers/Api/V1/SuperAdminController.php` — indexTenants, metrics, startImpersonation, stopImpersonation
- Modify: `routes/api.php` — add super-admin route group with `permission:super-admin.*` middleware

## Task 4: Frontend — Super admin pages, API, hooks, routes, nav

**Files:**
- Create: `web/src/features/super-admin/api.ts` + `hooks.ts`
- Create: `web/src/app/(app)/super-admin/dashboard/page.tsx`
- Create: `web/src/app/(app)/super-admin/tenants/page.tsx`
- Create: `web/src/app/(app)/super-admin/impersonations/page.tsx`
- Create: `web/src/app/(app)/super-admin/revenue/page.tsx`
- Modify: `web/src/routes/index.tsx` — add super-admin routes
- Modify: `web/src/lib/nav.ts` — add dashboard item to SUPER_ADMIN_NAV_GROUPS
- Modify: `web/src/lib/query-keys.ts` — add superAdmin query keys
- Modify: `web/src/components/layout/app-shell.tsx` — show super-admin nav for users with `super-admin.tenants.view` permission
- Modify: `web/src/i18n/messages/en.json` — add nav keys
