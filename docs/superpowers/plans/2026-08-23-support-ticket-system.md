# Support Ticket System + Controller Split — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a full-featured tenant-scoped support ticket system and split the monolithic SuperAdminController into domain-specific controllers.

**Architecture:** Split `SuperAdminController` (~700 lines) into 7 focused controllers under `app/Http/Controllers/Api/V1/SuperAdmin/`. Build `support_tickets` + `ticket_replies` tables with models, services, and API endpoints. Frontend gets a super admin ticket management page and an enhanced tenant support portal.

**Tech Stack:** Laravel 13 / PHP 8.4, Spatie Permissions, Sanctum, React 19, TanStack Query, Tailwind v4, shadcn-style UI primitives.

## Global Constraints

- Tenancy is non-negotiable: every business-owned model uses `BelongsToTenant` trait
- Layering: Controller → FormRequest → Service → Model → Resource
- Money/stock moved through services, never controllers
- Routes versioned under `/v1`, guarded with `permission:<module>.<action>`
- Tests required for every endpoint
- `vendor/bin/pint` and `npx tsc --noEmit` must pass after each task
- Super admin routes have NO `tenant`/`ensure.tenant` middleware
- Cross-tenant queries must use `withoutTenancy()` closures

---

## Phase 1: Controller Split

### Task 1: Create SuperAdmin directory + move TenantController

**Files:**
- Create: `app/Http/Controllers/Api/V1/SuperAdmin/TenantController.php`
- Modify: `routes/super-admin.php`
- Delete from: `app/Http/Controllers/Api/V1/SuperAdminController.php` (method bodies)

**Interfaces:**
- Consumes: existing `CreateTenantRequest`, `UpdateTenantRequest`, `TenantResource`, `TenantProvisioner`, `AuditLogService`
- Produces: `SuperAdmin\TenantController` with methods: `index`, `store`, `update`, `suspend`, `activate`, `destroy`, `metrics`, `show`

- [ ] **Step 1: Create directory**

```bash
mkdir -p app/Http/Controllers/Api/V1/SuperAdmin
```

- [ ] **Step 2: Create TenantController**

Create `app/Http/Controllers/Api/V1/SuperAdmin/TenantController.php`:

```php
<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\CreateTenantRequest;
use App\Http\Requests\SuperAdmin\UpdateTenantRequest;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use App\Services\Auth\TenantProvisioner;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TenantController extends Controller
{
    public function __construct(
        private readonly AuditLogService $audit,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Tenant::query()
            ->withCount(['users' => fn ($q) => $q->withoutTenancy()])
            ->withCount(['businesses' => fn ($q) => $q->withoutTenancy()]);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $tenants = $query->orderBy($request->input('sort', 'created_at'), $request->input('direction', 'desc'))
            ->paginate($request->input('per_page', 20));

        return TenantResource::collection($tenants);
    }

    public function store(CreateTenantRequest $request): JsonResponse
    {
        $data = $request->validated();

        $tenant = \App\Support\TenantContext::runUnscoped(function () use ($data) {
            return app(TenantProvisioner::class)->register(
                tenantName: $data['tenant_name'],
                slug: $data['slug'],
                businessName: $data['business_name'],
                ownerName: $data['owner_name'],
                ownerEmail: $data['owner_email'],
                ownerPassword: $data['owner_password'],
                currency: $data['currency'] ?? 'BDT',
                planSlug: $data['plan_slug'] ?? null,
            );
        });

        $this->audit->log('tenant.created', $tenant, null, [
            'name' => $tenant->name,
            'slug' => $tenant->slug,
        ]);

        return $this->success(new TenantResource($tenant->fresh()), 'Tenant created successfully', 201);
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant): JsonResponse
    {
        $old = $tenant->only(['name', 'slug', 'currency']);
        $data = $request->validated();

        if (isset($data['name'])) $tenant->name = $data['name'];
        if (isset($data['slug'])) $tenant->slug = $data['slug'];
        if (isset($data['currency'])) $tenant->currency = $data['currency'];
        $tenant->save();

        $this->audit->log('tenant.updated', $tenant, $old, $tenant->fresh()->toArray());

        return $this->success(new TenantResource($tenant->fresh()));
    }

    public function show(Tenant $tenant): JsonResponse
    {
        $tenant->loadCount(['users' => fn ($q) => $q->withoutTenancy()])
            ->loadCount(['businesses' => fn ($q) => $q->withoutTenancy()])
            ->load(['businesses' => fn ($q) => $q->withoutTenancy()->with('products')]);

        return $this->success(new TenantResource($tenant));
    }

    public function suspend(Request $request, Tenant $tenant): JsonResponse
    {
        $old = ['status' => $tenant->status];
        $tenant->update(['status' => 'suspended']);

        $this->audit->log('tenant.suspended', $tenant, $old, ['status' => 'suspended']);

        return $this->success(message: 'Tenant suspended');
    }

    public function activate(Request $request, Tenant $tenant): JsonResponse
    {
        $old = ['status' => $tenant->status];
        $tenant->update(['status' => 'active']);

        $this->audit->log('tenant.activated', $tenant, $old, ['status' => 'active']);

        return $this->success(message: 'Tenant activated');
    }

    public function destroy(Tenant $tenant): JsonResponse
    {
        $this->audit->log('tenant.deleted', $tenant, $tenant->toArray(), null);
        $tenant->delete();

        return $this->success(message: 'Tenant deleted');
    }

    public function metrics(): JsonResponse
    {
        $totalTenants = Tenant::count();
        $activeTenants = Tenant::where('status', 'active')->count();
        $totalUsers = User::withoutTenancy()->count();

        return $this->success([
            'total_tenants' => $totalTenants,
            'active_tenants' => $activeTenants,
            'total_users' => $totalUsers,
        ]);
    }
}
```

- [ ] **Step 3: Update routes to use new controller**

Replace in `routes/super-admin.php`:

```php
use App\Http\Controllers\Api\V1\SuperAdmin\TenantController;

// In the tenants.view group:
Route::get('tenants', [TenantController::class, 'index']);
Route::get('tenants/{tenant}', [TenantController::class, 'show']);
Route::get('metrics', [TenantController::class, 'metrics']);

// In the tenants.manage group:
Route::post('tenants', [TenantController::class, 'store']);
Route::put('tenants/{tenant}', [TenantController::class, 'update']);
Route::post('tenants/{tenant}/suspend', [TenantController::class, 'suspend']);
Route::post('tenants/{tenant}/activate', [TenantController::class, 'activate']);
Route::delete('tenants/{tenant}', [TenantController::class, 'destroy']);
```

- [ ] **Step 4: Remove moved methods from old SuperAdminController**

Delete these methods from `SuperAdminController.php`: `indexTenants`, `store`, `update`, `showTenant`, `suspendTenant`, `activateTenant`, `deleteTenant`, `metrics`

- [ ] **Step 5: Run tests**

```bash
php artisan test --filter=SuperAdmin
vendor/bin/pint
```

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/V1/SuperAdmin/ routes/super-admin.php app/Http/Controllers/Api/V1/SuperAdminController.php
git commit -m "refactor: split TenantController from SuperAdminController"
```

---

### Task 2: Move remaining controllers (batch)

Move UserController, ImpersonationController, PlanController, AuditLogController in the same pattern as Task 1. Each gets its own file under `SuperAdmin/`. Update routes after each move. Remove methods from old controller.

- [ ] **Step 1: Create UserController** — move `indexUsers`, `showUser`, `updateUserStatus`, `forceLogoutUser`
- [ ] **Step 2: Create ImpersonationController** — move `startImpersonation`, `stopImpersonation`, `revokeImpersonation`, `activeImpersonations`, `impersonate`
- [ ] **Step 3: Create PlanController** — move `listPlans`, `storePlan`, `updatePlan`, `destroyPlan`, `assignPlan`
- [ ] **Step 4: Create AuditLogController** — move `auditLogs`
- [ ] **Step 5: Update all routes** to reference new controllers
- [ ] **Step 6: Keep old SuperAdminController** with only `dashboard`, `health`, `revenueMetrics`
- [ ] **Step 7: Run tests** — `php artisan test --filter=SuperAdmin && vendor/bin/pint`
- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Api/V1/SuperAdmin/ routes/super-admin.php app/Http/Controllers/Api/V1/SuperAdminController.php
git commit -m "refactor: split all domain controllers from SuperAdminController"
```

---

## Phase 2: Backend — Support Ticket System

### Task 3: Migration + Models

**Files:**
- Create: `database/migrations/2026_08_23_000001_create_ticket_tables.php`
- Create: `app/Models/SupportTicket.php`
- Create: `app/Models/TicketReply.php`

- [ ] **Step 1: Create migration**

Create `database/migrations/2026_08_23_000001_create_ticket_tables.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('subject');
            $table->text('message');
            $table->string('category')->default('general');
            $table->string('priority')->default('medium');
            $table->string('status')->default('open');
            $table->foreignUuid('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sla_due_at')->nullable();
            $table->timestamp('last_reply_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['status', 'priority']);
            $table->index('assigned_to');
            $table->index('created_at');
        });

        Schema::create('ticket_replies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->text('message');
            $table->boolean('is_internal')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['ticket_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_replies');
        Schema::dropIfExists('support_tickets');
    }
};
```

- [ ] **Step 2: Create SupportTicket model**

Create `app/Models/SupportTicket.php`:

```php
<?php

namespace App\Models;

use App\Support\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupportTicket extends Model
{
    use BelongsToTenant, HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'user_id', 'subject', 'message', 'category',
        'priority', 'status', 'assigned_to', 'sla_due_at', 'last_reply_at',
    ];

    protected function casts(): array
    {
        return [
            'sla_due_at' => 'datetime',
            'last_reply_at' => 'datetime',
        ];
    }

    public const CATEGORIES = ['general', 'billing', 'technical', 'feature_request', 'bug_report'];
    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];
    public const STATUSES = ['open', 'in_progress', 'waiting_on_customer', 'resolved', 'closed'];

    public const SLA_HOURS = [
        'urgent' => 4,
        'high' => 8,
        'medium' => 24,
        'low' => 72,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class, 'ticket_id');
    }

    public function visibleReplies(): HasMany
    {
        return $this->replies()->where('is_internal', false);
    }

    public function getIsSlaBreachedAttribute(): bool
    {
        if (! $this->sla_due_at || $this->status === 'resolved' || $this->status === 'closed') {
            return false;
        }
        return now()->greaterThan($this->sla_due_at);
    }

    public static function boot(): void
    {
        parent::boot();

        static::creating(function (SupportTicket $ticket) {
            if (! $ticket->sla_due_at && $ticket->priority) {
                $hours = self::SLA_HOURS[$ticket->priority] ?? 24;
                $ticket->sla_due_at = now()->addHours($hours);
            }
            if (! $ticket->last_reply_at) {
                $ticket->last_reply_at = now();
            }
        });
    }
}
```

- [ ] **Step 3: Create TicketReply model**

Create `app/Models/TicketReply.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketReply extends Model
{
    use HasUuids;

    protected $fillable = ['ticket_id', 'user_id', 'message', 'is_internal'];

    protected function casts(): array
    {
        return ['is_internal' => 'boolean'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 4: Run migration**

```bash
php artisan migrate
```

- [ ] **Step 5: Run pint + commit**

```bash
vendor/bin/pint
git add database/migrations/2026_08_23_000001_create_ticket_tables.php app/Models/SupportTicket.php app/Models/TicketReply.php
git commit -m "feat: add support ticket tables and models"
```

---

### Task 4: Backend — Tenant ticket endpoints

**Files:**
- Create: `app/Http/Controllers/Api/V1/SupportTicketController.php`
- Create: `app/Http/Resources/SupportTicketResource.php`
- Create: `app/Http/Resources/TicketReplyResource.php`
- Create: `app/Http/Requests/Support/CreateTicketRequest.php`
- Create: `app/Http/Requests/Support/ReplyTicketRequest.php`
- Modify: `routes/app.php` — add support ticket routes
- Create: `tests/Feature/Api/SupportTicketTest.php`

- [ ] **Step 1: Create resources and form requests** (follow existing patterns)
- [ ] **Step 2: Create SupportTicketController** with: `index`, `store`, `show`, `reply`, `close`, `reopen`
- [ ] **Step 3: Add routes** to `routes/app.php` under `api/v1/support/tickets`
- [ ] **Step 4: Write tests** — create, list own, reply, close, reopen, cannot see other tenant tickets
- [ ] **Step 5: Run tests + pint**
- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/V1/SupportTicketController.php app/Http/Resources/ app/Http/Requests/Support/ routes/app.php tests/Feature/Api/SupportTicketTest.php
git commit -m "feat: add tenant support ticket endpoints"
```

---

### Task 5: Backend — Super admin support ticket endpoints

**Files:**
- Create: `app/Http/Controllers/Api/V1/SuperAdmin/SupportTicketController.php`
- Create: `app/Http/Resources/SuperAdminSupportTicketResource.php`
- Create: `app/Http/Requests/SuperAdmin/UpdateTicketRequest.php`
- Modify: `routes/super-admin.php` — add support ticket routes
- Modify: `app/Support/Rbac/PermissionCatalog.php` — add `support.view`, `support.manage`
- Create: `tests/Feature/Api/SuperAdminSupportTicketTest.php`

- [ ] **Step 1: Add permission slugs** to PermissionCatalog
- [ ] **Step 2: Create SupportTicketController** with: `index`, `show`, `update`, `reply`, `addNote`, `assign`
- [ ] **Step 3: Add routes** to `routes/super-admin.php`
- [ ] **Step 4: Write tests** — list all, reply, internal note, assign, change status/priority
- [ ] **Step 5: Run tests + pint**
- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/V1/SuperAdmin/SupportTicketController.php app/Http/Resources/ app/Http/Requests/SuperAdmin/ routes/super-admin.php app/Support/Rbac/PermissionCatalog.php tests/Feature/Api/SuperAdminSupportTicketTest.php
git commit -m "feat: add super admin support ticket management endpoints"
```

---

## Phase 3: Frontend — Super Admin Support Page

### Task 6: API + types + hooks

**Files:**
- Modify: `web/src/types/domain.ts` — add SupportTicket, TicketReply types
- Modify: `web/src/features/super-admin/types.ts` — re-export
- Modify: `web/src/features/super-admin/api.ts` — add support ticket API functions
- Modify: `web/src/features/super-admin/hooks.ts` — add useSupportTickets hook
- Modify: `web/src/lib/query-keys.ts` — add support ticket keys
- Modify: `web/src/lib/permissions.ts` — add route permission
- Modify: `web/src/lib/nav.ts` — add support nav item
- Modify: `web/src/routes/index.tsx` — add route
- Modify: `web/src/i18n/messages/en.json` — add i18n keys

- [ ] **Step 1: Add types** to domain.ts
- [ ] **Step 2: Add API functions** to api.ts
- [ ] **Step 3: Add hooks** to hooks.ts
- [ ] **Step 4: Add query keys** to query-keys.ts
- [ ] **Step 5: Add nav item** — `{ href: "/super-admin/support", key: "support", icon: Headset }`
- [ ] **Step 6: Add route permission** — `"/super-admin/support": ["super-admin.support.view"]`
- [ ] **Step 7: Add route** in routes/index.tsx
- [ ] **Step 8: Add i18n key** — `"support": "Support Tickets"` in nav namespace
- [ ] **Step 9: Run tsc + lint**
- [ ] **Step 10: Commit**

```bash
cd web && git add . && git commit -m "feat: add super admin support ticket API, types, hooks, and routing"
```

---

### Task 7: Super admin support ticket list page

**Files:**
- Create: `web/src/app/(app)/super-admin/support/page.tsx`
- Create: `web/src/features/super-admin/components/support-ticket-list.tsx`

- [ ] **Step 1: Create page** at `web/src/app/(app)/super-admin/support/page.tsx`
- [ ] **Step 2: Create list component** — DataTable with columns: Subject, Tenant, Category (badge), Priority (badge), Status (badge), Assigned to, Last Reply, Created, SLA (breached/ok)
- [ ] **Step 3: Add filters** — status, priority, category, search
- [ ] **Step 4: Add pagination**
- [ ] **Step 5: Run tsc + lint**
- [ ] **Step 6: Commit**

```bash
cd web && git add . && git commit -m "feat: add super admin support ticket list page"
```

---

### Task 8: Super admin ticket detail modal

**Files:**
- Create: `web/src/features/super-admin/components/support-ticket-detail-modal.tsx`

- [ ] **Step 1: Create modal** with 3 tabs:
  1. **Conversation** — thread with tenant messages, admin replies (different styles), internal notes (highlighted yellow)
  2. **Details** — category, priority, status, assignment, SLA info
  3. **Actions** — change status, change priority, assign to self, add internal note, reply
- [ ] **Step 2: Wire into list** — row click opens modal
- [ ] **Step 3: Run tsc + lint**
- [ ] **Step 4: Commit**

```bash
cd web && git add . && git commit -m "feat: add super admin support ticket detail modal"
```

---

## Phase 4: Frontend — Tenant Support Portal

### Task 9: Tenant support API + hooks

**Files:**
- Create: `web/src/features/support/api.ts`
- Create: `web/src/features/support/hooks.ts`
- Modify: `web/src/lib/query-keys.ts` — add tenant support keys

- [ ] **Step 1: Create support feature folder** with api.ts and hooks.ts
- [ ] **Step 2: Add API functions** — list, create, show, reply, close, reopen
- [ ] **Step 3: Add hooks** — useTickets, useTicketMutations
- [ ] **Step 4: Run tsc + lint**
- [ ] **Step 5: Commit**

```bash
cd web && git add . && git commit -m "feat: add tenant support ticket API and hooks"
```

---

### Task 10: Replace tenant support page

**Files:**
- Modify: `web/src/app/(app)/support/page.tsx` — replace static form with ticket list + create dialog + detail view

- [ ] **Step 1: Replace page** with ticket list + create dialog pattern (follow users-list.tsx pattern)
- [ ] **Step 2: Add create ticket dialog** — form with subject, message, category, priority
- [ ] **Step 3: Add ticket detail view** — conversation thread + reply + close/reopen
- [ ] **Step 4: Keep FAQ section** below the ticket list
- [ ] **Step 5: Run tsc + lint**
- [ ] **Step 6: Commit**

```bash
cd web && git add . && git commit -m "feat: replace tenant support page with ticket portal"
```

---

## Phase 5: Final

### Task 11: Final verification

- [ ] **Step 1: Run full test suite** — `php artisan test`
- [ ] **Step 2: Run pint** — `vendor/bin/pint`
- [ ] **Step 3: Run tsc** — `cd web && npx tsc --noEmit`
- [ ] **Step 4: Run lint** — `cd web && npm run lint`
- [ ] **Step 5: Manual smoke test** — login as superadmin, verify all pages load
- [ ] **Step 6: Commit if needed**
