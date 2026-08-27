# Per-Tenant Module Enable/Disable Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans to implement this plan.

**Goal:** Allow super admin to enable/disable modules per tenant. Disabled modules are hidden from that tenant's sidebar and routes.

**Architecture:** Backend endpoint returns enabled modules for the current tenant. Frontend Zustand store tracks them. Sidebar and route guards check the store.

**Tech Stack:** Laravel 13, React 19, Zustand, TanStack Query, Tailwind v4.

---

## Key Findings

- Landing page already exists at `web/src/app/(app)/home/page.tsx` — complete with hero, features, CTA. No work needed.
- `PlatformSetting` model has `tenant_overrides` JSON field — supports per-tenant module overrides
- `SettingController::isModuleEnabled()` exists but is dead code — needs to be exposed as API
- No frontend module store exists — sidebar only checks RBAC permissions, not modules
- `Tenant.feature_limits` column exists but is unused

---

## File Structure

### Backend
| File | Purpose |
|------|---------|
| `app/Http/Controllers/Api/V1/ModuleController.php` | New: returns enabled modules for current tenant |
| `routes/api.php` | Add: `GET /v1/modules` |
| `app/Http/Controllers/Api/V1/SuperAdmin/SettingController.php` | Modify: add per-tenant override endpoint |
| `routes/api.php` | Add: `PUT /v1/super/settings/tenant-overrides` |

### Frontend
| File | Purpose |
|------|---------|
| `web/src/stores/modules.ts` | New: Zustand store for enabled modules |
| `web/src/lib/api.ts` | Add: `getModules()` API call |
| `web/src/components/layout/sidebar.tsx` | Modify: filter nav items by enabled modules |
| `web/src/lib/nav.ts` | Modify: add `module` field to nav items |
| `web/src/components/layout/app-shell.tsx` | Modify: fetch modules on mount |
| `web/src/features/super-admin/components/platform-settings.tsx` | Modify: add per-tenant override UI |
| `web/src/features/super-admin/components/tenant-detail-modal.tsx` | Modify: show/edit module overrides |

---

## Task 1: Backend — Module Endpoint for Current Tenant

**Files:**
- Create: `app/Http/Controllers/Api/V1/ModuleController.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Create ModuleController**

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class ModuleController extends Controller
{
    use ApiResponse;

    public function __invoke(): JsonResponse
    {
        $tenantId = tenant();
        $globalModules = PlatformSetting::get('modules', []);
        $overrides = PlatformSetting::get('tenant_overrides', []);

        $enabled = [];
        foreach ($globalModules as $module => $allowed) {
            if (!$allowed) continue;
            // Check tenant-specific override
            if (isset($overrides[$tenantId][$module])) {
                $enabled[$module] = (bool) $overrides[$tenantId][$module];
            } else {
                $enabled[$module] = true;
            }
        }

        return $this->success($enabled);
    }
}
```

- [ ] **Step 2: Add route**

Add inside the auth middleware group (not super_admin):

```php
Route::get('modules', \App\Http\Controllers\Api\V1\ModuleController::class);
```

- [ ] **Step 3: Write test**

```php
<?php

namespace Tests\Feature\Api;

use App\Models\PlatformSetting;
use App\Models\User;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class ModuleTest extends TestCase
{
    use CreatesTenants;

    public function test_user_can_get_enabled_modules(): void
    {
        PlatformSetting::set('modules', ['pos' => true, 'inventory' => true, 'hr' => false]);

        $user = User::factory()->create();
        $response = $this->actingAs($user)->getJson('/api/v1/modules');

        $response->assertOk();
        $response->assertJsonFragment(['pos' => true]);
        $response->assertJsonMissing(['hr' => true]);
    }

    public function test_tenant_override_respected(): void
    {
        PlatformSetting::set('modules', ['pos' => true, 'inventory' => true]);
        PlatformSetting::set('tenant_overrides', []);

        $user = User::factory()->create();
        $response = $this->actingAs($user)->getJson('/api/v1/modules');

        $response->assertOk();
    }
}
```

- [ ] **Step 4: Run tests**

```bash
php artisan test --filter=ModuleTest
vendor/bin/pint --test
```

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/V1/ModuleController.php routes/api.php tests/Feature/Api/ModuleTest.php
git commit -m "feat: add module endpoint for current tenant"
```

---

## Task 2: Backend — Per-Tenant Override Endpoint for Super Admin

**Files:**
- Modify: `app/Http/Controllers/Api/V1/SuperAdmin/SettingController.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Add tenantOverrides method to SettingController**

```php
public function tenantOverrides(): JsonResponse
{
    $overrides = PlatformSetting::get('tenant_overrides', []);
    return $this->success($overrides);
}

public function updateTenantOverrides(Request $request): JsonResponse
{
    $request->validate([
        'tenant_id' => 'required|string|exists:tenants,id',
        'modules' => 'required|array',
        'modules.*' => 'boolean',
    ]);

    $overrides = PlatformSetting::get('tenant_overrides', []);
    $overrides[$request->tenant_id] = $request->modules;
    PlatformSetting::set('tenant_overrides', $overrides);

    return $this->success($overrides);
}
```

- [ ] **Step 2: Add routes**

```php
Route::get('super/settings/tenant-overrides', [\App\Http\Controllers\Api\V1\SuperAdmin\SettingController::class, 'tenantOverrides']);
Route::put('super/settings/tenant-overrides', [\App\Http\Controllers\Api\V1\SuperAdmin\SettingController::class, 'updateTenantOverrides']);
```

- [ ] **Step 3: Run tests**

```bash
php artisan test --filter=SuperAdminSettingsTest
vendor/bin/pint --test
```

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/Api/V1/SuperAdmin/SettingController.php routes/api.php
git commit -m "feat: add per-tenant module override endpoints"
```

---

## Task 3: Frontend — Module Store + API

**Files:**
- Create: `web/src/stores/modules.ts`
- Modify: `web/src/features/super-admin/api.ts`
- Modify: `web/src/features/super-admin/types.ts`
- Modify: `web/src/features/super-admin/hooks.ts`

- [ ] **Step 1: Create module store**

```ts
import { create } from "zustand";

interface ModuleState {
  modules: Record<string, boolean>;
  loaded: boolean;
  setModules: (modules: Record<string, boolean>) => void;
  isEnabled: (module: string) => boolean;
}

export const useModules = create<ModuleState>((set, get) => ({
  modules: {},
  loaded: false,
  setModules: (modules) => set({ modules, loaded: true }),
  isEnabled: (module) => get().modules[module] ?? false,
}));
```

- [ ] **Step 2: Add API function**

Add to `web/src/features/super-admin/api.ts` (or create a shared `lib/modules.ts`):

```ts
export async function getEnabledModules(): Promise<Record<string, boolean>> {
  const res = await api<{ data: Record<string, boolean> }>("/modules");
  return res.data;
}
```

- [ ] **Step 3: Add query key**

Add to `queryKeys.ts`:

```ts
modules: () => ["modules"] as const,
```

- [ ] **Step 4: Add hook**

```ts
export function useModules() {
  const setModules = useModulesStore((s) => s.setModules);

  return useQuery({
    queryKey: queryKeys.modules(),
    queryFn: getEnabledModules,
    onSuccess: (data) => setModules(data),
    staleTime: 5 * 60 * 1000,
  });
}
```

- [ ] **Step 5: Commit**

```bash
git add web/src/stores/modules.ts web/src/features/super-admin/api.ts web/src/features/super-admin/types.ts web/src/features/super-admin/hooks.ts web/src/lib/query-keys.ts
git commit -m "feat: add module store and API for current tenant"
```

---

## Task 4: Frontend — Sidebar Module Gating

**Files:**
- Modify: `web/src/lib/nav.ts` — add `module` field to nav items
- Modify: `web/src/components/layout/sidebar.tsx` — filter by enabled modules
- Modify: `web/src/components/layout/app-shell.tsx` — fetch modules on mount

- [ ] **Step 1: Add module field to nav items**

In `web/src/lib/nav.ts`, add optional `module` field:

```ts
interface NavItem {
  label: string;
  href: string;
  icon: LucideIcon;
  permissions?: string[];
  module?: string;  // <-- add this
}
```

Map modules to nav items:

```ts
{ label: "POS", href: "/pos", icon: Monitor, module: "pos" },
{ label: "Inventory", href: "/inventory", icon: Package, module: "inventory" },
{ label: "Accounting", href: "/accounting", icon: Calculator, module: "accounting" },
// etc.
```

- [ ] **Step 2: Update sidebar filtering**

In `useVisibleNavGroups()`, add module check:

```ts
import { useModules } from "@/stores/modules";

function useVisibleNavGroups(): NavGroup[] {
  const user = useAuth((s) => s.user);
  const modules = useModules((s) => s.modules);
  const loaded = useModules((s) => s.loaded);

  return useMemo(() => {
    if (!loaded) return [];
    return NAV_GROUPS.map((group) => ({
      ...group,
      items: group.items.filter((item) => {
        // Check module enabled
        if (item.module && !modules[item.module]) return false;
        // Check permissions
        const required = permissionsForPathname(item.href);
        return required.length === 0 || required.some((slug) => hasPermission(user, slug));
      }),
    })).filter((group) => group.items.length > 0);
  }, [user, modules, loaded]);
}
```

- [ ] **Step 3: Fetch modules in AppShell**

In `app-shell.tsx`, add module fetch:

```ts
import { useModules } from "@/modules";

// Inside AppShell component:
useModules(); // triggers fetch on mount
```

- [ ] **Step 4: Run checks**

```bash
cd web && npm run lint && npx tsc --noEmit
```

- [ ] **Step 5: Commit**

```bash
git add web/src/lib/nav.ts web/src/components/layout/sidebar.tsx web/src/components/layout/app-shell.tsx
git commit -m "feat: gate sidebar items by enabled modules"
```

---

## Task 5: Frontend — Super Admin Per-Tenant Module UI

**Files:**
- Modify: `web/src/features/super-admin/components/platform-settings.tsx`
- Modify: `web/src/features/super-admin/api.ts`
- Modify: `web/src/features/super-admin/hooks.ts`
- Modify: `web/src/features/super-admin/components/tenant-detail-modal.tsx`

- [ ] **Step 1: Add tenant overrides API and hooks**

```ts
// api.ts
settings: {
  // ... existing ...
  getTenantOverrides: () =>
    api<{ data: Record<string, Record<string, boolean>> }>("/super/settings/tenant-overrides"),
  updateTenantOverrides: (tenantId: string, modules: Record<string, boolean>) =>
    api<{ data: Record<string, Record<string, boolean>> }>("/super/settings/tenant-overrides", {
      method: "PUT",
      body: { tenant_id: tenantId, modules },
    }),
},
```

```ts
// hooks.ts
export function useTenantModuleOverrides() {
  return useQuery({
    queryKey: ["super-admin", "tenant-overrides"],
    queryFn: superAdminApi.settings.getTenantOverrides,
  });
}

export function useUpdateTenantModules() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ tenantId, modules }: { tenantId: string; modules: Record<string, boolean> }) =>
      superAdminApi.settings.updateTenantOverrides(tenantId, modules),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["super-admin", "tenant-overrides"] });
      toast("Tenant modules updated", "success");
    },
  });
}
```

- [ ] **Step 2: Add tenant module section to platform-settings.tsx**

Below the global module toggles, add a section to manage per-tenant overrides:

```tsx
// Tenant Module Overrides section
const { data: overrides } = useTenantModuleOverrides();
const updateTenantModules = useUpdateTenantModules();
const [selectedTenant, setSelectedTenant] = useState<string | null>(null);

// Render a table/list of tenants with their module overrides
// Click a tenant to edit its module overrides
```

- [ ] **Step 3: Add module toggles to tenant detail modal**

In `tenant-detail-modal.tsx`, add a "Modules" tab that shows/edits that tenant's module overrides.

- [ ] **Step 4: Run checks**

```bash
cd web && npm run lint && npx tsc --noEmit
```

- [ ] **Step 5: Commit**

```bash
git add web/src/features/super-admin/
git commit -m "feat: add per-tenant module override UI in super admin"
```

---

## Task 6: Route Guard for Disabled Modules

**Files:**
- Modify: `web/src/components/require-permission.tsx` — add module check
- Modify: `web/src/components/layout/app-shell.tsx` — redirect if module disabled

- [ ] **Step 1: Create RequireModule component**

```tsx
import { useModules } from "@/stores/modules";
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { useNavigate } from "react-router";

export function RequireModule({ module, children }: { module: string; children: React.ReactNode }) {
  const isEnabled = useModules((s) => s.modules[module]) ?? false;
  const loaded = useModules((s) => s.loaded);
  const navigate = useNavigate();

  if (!loaded) return null;

  if (!isEnabled) {
    return (
      <Card>
        <CardContent className="py-12 text-center">
          <p className="text-sm text-[var(--muted)]">This module is not enabled for your account.</p>
          <Button variant="ghost" className="mt-2" onClick={() => navigate("/dashboard")}>
            Go to Dashboard
          </Button>
        </CardContent>
      </Card>
    );
  }

  return <>{children}</>;
}
```

- [ ] **Step 2: Wrap module routes in AppShell**

In the route definitions, wrap module-specific routes:

```tsx
{
  path: "pos",
  element: <RequireModule module="pos"><Outlet /></RequireModule>,
  children: [
    { index: true, lazy: () => loadPage(() => import("@/app/(app)/pos/page")) },
  ],
},
```

Or alternatively, add module check in `RequirePermission` component.

- [ ] **Step 3: Run checks**

```bash
cd web && npm run lint && npx tsc --noEmit
```

- [ ] **Step 4: Commit**

```bash
git add web/src/components/require-permission.tsx web/src/components/layout/app-shell.tsx web/src/routes/index.tsx
git commit -m "feat: add module guard for disabled module routes"
```

---

## Final Verification

- [ ] Run all backend tests: `php artisan test`
- [ ] Run frontend checks: `cd web && npm run lint && npx tsc --noEmit && npm run build`
- [ ] Manual testing:
  1. Login as super admin → Settings → Toggle a module off globally
  2. Login as tenant user → Verify module hidden from sidebar
  3. Super admin → Settings → Set per-tenant override for a module
  4. Login as that tenant → Verify module visibility matches override
  5. Try accessing a disabled module's URL directly → Should show "module not enabled" message
  6. Verify landing page at `/` works for logged-out users
