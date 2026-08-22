<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Enums\SubscriptionStatus;
use App\Domain\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResource;
use App\Mail\ImpersonationStartedMail;
use App\Models\AppNotification;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Impersonation;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Audit\AuditLogService;
use App\Services\SuperAdmin\ImpersonationService;
use App\Support\Query\AllowedFilter;
use App\Support\Query\QueryPipeline;
use App\Support\Scopes\TenantScope;
use App\Support\Tenancy\TenantContext;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Super-admin endpoints: tenant management, billing metrics, impersonation.
 * All routes must be guarded by the `super_admin` middleware.
 */
class SuperAdminController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AuditLogService $audit,
        protected ImpersonationService $impersonationService,
    ) {}

    // ------------------------------------------------------------------
    // T31 — Tenant management
    // ------------------------------------------------------------------

    public function tenants(Request $request): JsonResponse
    {
        $tenants = QueryPipeline::for(
            Tenant::withCount([
                'users' => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
                'businesses' => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
            ]),
            $request,
        )
            ->search(['name', 'slug'])
            ->allow(AllowedFilter::enum('status', TenantStatus::class))
            ->sortable(['id', 'name', 'created_at'], default: '-id')
            ->paginate();

        return $this->success($tenants);
    }

    public function indexTenants(Request $request): AnonymousResourceCollection
    {
        $tenants = QueryPipeline::for(
            Tenant::withCount([
                'users' => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
            ])->with(['businesses' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)]),
            $request,
        )
            ->search(['name', 'slug'])
            ->sortable(['name', 'created_at'], default: 'name')
            ->paginate();

        return TenantResource::collection($tenants);
    }

    public function metrics(): JsonResponse
    {
        return $this->success([
            'total_tenants' => Tenant::count(),
            'total_users' => User::withoutTenancy()->count(),
            'tenants_by_status' => Tenant::query()
                ->select('status', \DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status'),
        ]);
    }

    public function startImpersonation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $targetUser = User::findOrFail($data['user_id']);
        $token = $this->impersonationService->start($request->user(), $targetUser->id);

        return $this->created([
            'token' => $token,
            'user' => $targetUser->only('id', 'name', 'email'),
            'expires_at' => now()->addMinutes(60)->toISOString(),
        ]);
    }

    public function showTenant(Tenant $tenant): JsonResponse
    {
        $tenant->loadCount([
            'users' => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
            'businesses' => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
        ]);

        $tenant->load(['businesses' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)]);

        $users = User::withoutTenancy()->where('tenant_id', $tenant->id)
            ->with(['roles' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)])
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'status' => $u->status?->value ?? $u->status,
                'roles' => $u->roles->pluck('name'),
                'created_at' => $u->created_at,
            ]);

        $businessIds = $tenant->businesses->pluck('id');

        $categoriesCount = Category::withoutTenancy()
            ->where('tenant_id', $tenant->id)
            ->count();

        $productsCount = Product::withoutTenancy()
            ->where('tenant_id', $tenant->id)
            ->count();

        $branchesCount = Branch::withoutTenancy()
            ->where('tenant_id', $tenant->id)
            ->count();

        $warehousesCount = Warehouse::withoutTenancy()
            ->where('tenant_id', $tenant->id)
            ->count();

        $recentSales = Sale::withoutTenancy()
            ->where('tenant_id', $tenant->id)
            ->with(['business' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)])
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'total' => $s->total,
                'payment_status' => $s->payment_status,
                'status' => $s->status,
                'business_name' => $s->business?->name,
                'created_at' => $s->created_at,
            ]);

        $subscription = Subscription::withoutTenancy()
            ->where('tenant_id', $tenant->id)
            ->with('plan')
            ->latest()
            ->first();

        $totalSales = Sale::withoutTenancy()
            ->where('tenant_id', $tenant->id)
            ->count();

        $totalRevenue = Sale::withoutTenancy()
            ->where('tenant_id', $tenant->id)
            ->where('payment_status', 'paid')
            ->sum('total');

        return $this->success([
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'status' => $tenant->status,
            'plan' => $tenant->plan,
            'base_currency' => $tenant->base_currency,
            'trial_ends_at' => $tenant->trial_ends_at,
            'created_at' => $tenant->created_at,
            'updated_at' => $tenant->updated_at,
            'users_count' => $tenant->users_count,
            'businesses_count' => $tenant->businesses_count,
            'businesses' => $tenant->businesses->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'currency' => $b->currency,
            ]),
            'users' => $users,
            'categories_count' => $categoriesCount,
            'products_count' => $productsCount,
            'branches_count' => $branchesCount,
            'warehouses_count' => $warehousesCount,
            'total_sales' => $totalSales,
            'total_revenue' => (float) $totalRevenue,
            'recent_sales' => $recentSales,
            'subscription' => $subscription,
        ]);
    }

    public function suspendTenant(Request $request, Tenant $tenant): JsonResponse
    {
        $tenant->update(['status' => TenantStatus::Suspended]);

        $userIds = User::withoutTenancy()->where('tenant_id', $tenant->id)->pluck('id');
        PersonalAccessToken::where('tokenable_type', User::class)->whereIn('tokenable_id', $userIds)->delete();

        $this->audit->log('tenant.suspended', $tenant, null, ['tenant_id' => $tenant->id], $request->user(), $tenant->id);

        return $this->success($tenant, 'Tenant suspended.');
    }

    public function activateTenant(Request $request, Tenant $tenant): JsonResponse
    {
        $tenant->update(['status' => TenantStatus::Active]);

        $this->audit->log('tenant.activated', $tenant, null, ['tenant_id' => $tenant->id], $request->user(), $tenant->id);

        return $this->success($tenant, 'Tenant activated.');
    }

    public function deleteTenant(Tenant $tenant): JsonResponse
    {
        $tenant->delete();

        return $this->noContent();
    }

    // ------------------------------------------------------------------
    // T32 — Billing & revenue metrics
    // ------------------------------------------------------------------

    public function revenueMetrics(Request $request): JsonResponse
    {
        $period = $request->integer('days', 30);
        $from = now()->subDays($period);

        $planDistribution = Subscription::withoutTenancy()->where('status', SubscriptionStatus::Active)
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->selectRaw('plans.name as plan_name, plans.slug, count(*) as count, sum(plans.price) as mrr')
            ->groupBy('plans.id', 'plans.name', 'plans.slug')
            ->get();

        $newTenants = Tenant::where('created_at', '>=', $from)->count();
        $activeTenants = Tenant::where('status', TenantStatus::Active)->count();
        $suspendedTenants = Tenant::where('status', TenantStatus::Suspended)->count();

        $totalMrr = $planDistribution->sum('mrr');

        $recentSubscriptions = Subscription::withoutTenancy()->with(['plan'])
            ->where('created_at', '>=', $from)
            ->latest()
            ->limit(20)
            ->get();

        return $this->success([
            'total_mrr' => $totalMrr,
            'active_tenants' => $activeTenants,
            'suspended_tenants' => $suspendedTenants,
            'new_tenants_last_days' => $newTenants,
            'plan_distribution' => $planDistribution,
            'recent_subscriptions' => $recentSubscriptions,
        ]);
    }

    // ------------------------------------------------------------------
    // T33 — Tenant impersonation
    // ------------------------------------------------------------------

    protected const IMPERSONATION_MINUTES = 30;

    public function impersonate(Tenant $tenant, Request $request): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $admin = $request->user();

        // Find the owner of this tenant. The `roles` relation is scoped to spatie's
        // current team, and a super admin has no tenant of their own bound — so borrow
        // the target tenant's context for the lookup.
        $owner = app(TenantContext::class)->runAs($tenant->id, fn () => User::withoutTenancy()
            ->where('tenant_id', $tenant->id)
            ->whereHas('roles', fn ($q) => $q->withoutGlobalScope(TenantScope::class)->where('name', 'owner'))
            ->firstOrFail());

        // Issue a short-lived, narrowly-scoped token — 30 minutes, impersonate-only ability.
        $expiresAt = now()->addMinutes(self::IMPERSONATION_MINUTES);
        $token = $owner->createToken(
            name: 'impersonation:super-admin:'.$admin->id,
            abilities: ['impersonate'],
            expiresAt: $expiresAt,
        );

        $impersonation = Impersonation::create([
            'tenant_id' => $tenant->id,
            'super_admin_id' => $admin->id,
            'target_user_id' => $owner->id,
            'personal_access_token_id' => $token->accessToken->id,
            'reason' => $data['reason'],
            'started_at' => now(),
        ]);

        $this->audit->log('impersonation.started', $impersonation, null, [
            'tenant_id' => $tenant->id,
            'target_user_id' => $owner->id,
            'reason' => $data['reason'],
        ], $admin, $tenant->id);

        AppNotification::create([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'type' => 'impersonation.started',
            'title' => 'Support accessed your account',
            'body' => "{$admin->name} started a support session on your account. Reason: {$data['reason']}",
            'data' => ['admin_name' => $admin->name, 'reason' => $data['reason']],
        ]);

        Mail::to($owner)->queue(new ImpersonationStartedMail($owner, $data['reason'], $admin->name));

        return $this->created([
            'impersonation_id' => $impersonation->id,
            'token' => $token->plainTextToken,
            'tenant' => $tenant->only('id', 'name', 'slug'),
            'user' => $owner->only('id', 'name', 'email'),
            'expires_at' => $expiresAt->toISOString(),
        ]);
    }

    /** Called from inside the impersonated session to end it early. */
    public function stopImpersonation(Request $request): JsonResponse
    {
        $token = $request->input('token');

        if ($token) {
            $this->impersonationService->stop($token);

            return $this->success(null, 'Impersonation ended.');
        }

        $tokenId = $request->user()->currentAccessToken()->id;

        $this->endImpersonation($tokenId, $request->user());

        return $this->success(null, 'Impersonation ended.');
    }

    /** Called from the super-admin's own session to revoke a live impersonation remotely. */
    public function revokeImpersonation(Request $request, string $impersonation): JsonResponse
    {
        $record = Impersonation::whereKey($impersonation)->whereNull('ended_at')->firstOrFail();

        $this->endImpersonation($record->personal_access_token_id, $request->user(), $record);

        return $this->success(null, 'Impersonation revoked.');
    }

    /** Every impersonation currently in progress (not yet ended). */
    public function activeImpersonations(): JsonResponse
    {
        $records = Impersonation::whereNull('ended_at')
            ->with(['tenant:id,name', 'superAdmin:id,name,email', 'targetUser:id,name,email'])
            ->latest('started_at')
            ->get();

        return $this->success($records);
    }

    protected function endImpersonation(?string $tokenId, User $endedBy, ?Impersonation $record = null): void
    {
        $record ??= $tokenId
            ? Impersonation::where('personal_access_token_id', $tokenId)->whereNull('ended_at')->first()
            : null;

        if ($tokenId) {
            PersonalAccessToken::whereKey($tokenId)->delete();
        }

        if ($record) {
            $record->update(['ended_at' => now(), 'ended_by' => $endedBy->id]);

            $this->audit->log('impersonation.stopped', $record, null, [
                'tenant_id' => $record->tenant_id,
                'target_user_id' => $record->target_user_id,
            ], $endedBy, $record->tenant_id);
        }
    }
}
