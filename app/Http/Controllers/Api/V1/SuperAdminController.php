<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Super-admin endpoints: tenant management, billing metrics, impersonation.
 * All routes must be guarded by the `super_admin` middleware.
 */
class SuperAdminController extends Controller
{
    public function __construct(protected AuditLogService $audit) {}

    // ------------------------------------------------------------------
    // T31 — Tenant management
    // ------------------------------------------------------------------

    public function tenants(Request $request): JsonResponse
    {
        $tenants = Tenant::withCount(['users', 'businesses'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->latest()
            ->paginate($request->integer('per_page', 25));

        return response()->json($tenants);
    }

    public function showTenant(Tenant $tenant): JsonResponse
    {
        $tenant->loadCount(['users', 'businesses']);

        $subscription = Subscription::where('tenant_id', $tenant->id)->with('plan')->latest()->first();

        $usage = [
            'sales_count' => Sale::where('tenant_id', $tenant->id)->count(),
            'users_count' => User::where('tenant_id', $tenant->id)->count(),
        ];

        return response()->json(['data' => array_merge($tenant->toArray(), [
            'subscription' => $subscription,
            'usage' => $usage,
        ])]);
    }

    public function suspendTenant(Request $request, Tenant $tenant): JsonResponse
    {
        $tenant->update(['status' => 'suspended']);

        $userIds = User::withoutTenancy()->where('tenant_id', $tenant->id)->pluck('id');
        PersonalAccessToken::where('tokenable_type', User::class)->whereIn('tokenable_id', $userIds)->delete();

        $this->audit->log('tenant.suspended', $tenant, null, ['tenant_id' => $tenant->id], $request->user(), $tenant->id);

        return response()->json(['data' => $tenant]);
    }

    public function activateTenant(Request $request, Tenant $tenant): JsonResponse
    {
        $tenant->update(['status' => 'active']);

        $this->audit->log('tenant.activated', $tenant, null, ['tenant_id' => $tenant->id], $request->user(), $tenant->id);

        return response()->json(['data' => $tenant]);
    }

    public function deleteTenant(Tenant $tenant): JsonResponse
    {
        $tenant->delete();

        return response()->json(null, 204);
    }

    // ------------------------------------------------------------------
    // T32 — Billing & revenue metrics
    // ------------------------------------------------------------------

    public function revenueMetrics(Request $request): JsonResponse
    {
        $period = $request->integer('days', 30);
        $from = now()->subDays($period);

        $planDistribution = Subscription::where('status', 'active')
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->selectRaw('plans.name as plan_name, plans.slug, count(*) as count, sum(plans.price_monthly) as mrr')
            ->groupBy('plans.id', 'plans.name', 'plans.slug')
            ->get();

        $newTenants = Tenant::where('created_at', '>=', $from)->count();
        $activeTenants = Tenant::where('status', 'active')->count();
        $suspendedTenants = Tenant::where('status', 'suspended')->count();

        $totalMrr = $planDistribution->sum('mrr');

        $recentSubscriptions = Subscription::with(['plan'])
            ->where('created_at', '>=', $from)
            ->latest()
            ->limit(20)
            ->get();

        return response()->json(['data' => [
            'total_mrr' => $totalMrr,
            'active_tenants' => $activeTenants,
            'suspended_tenants' => $suspendedTenants,
            'new_tenants_last_days' => $newTenants,
            'plan_distribution' => $planDistribution,
            'recent_subscriptions' => $recentSubscriptions,
        ]]);
    }

    // ------------------------------------------------------------------
    // T33 — Tenant impersonation
    // ------------------------------------------------------------------

    public function impersonate(Tenant $tenant, Request $request): JsonResponse
    {
        // Find the owner of this tenant
        $owner = User::where('tenant_id', $tenant->id)
            ->whereHas('roles', fn ($q) => $q->where('slug', 'owner'))
            ->firstOrFail();

        // Issue a short-lived scoped token (24h) with impersonation marker
        $token = $owner->createToken(
            name: 'impersonation:super-admin:'.$request->user()->id,
            expiresAt: now()->addHours(24),
        );

        return response()->json([
            'data' => [
                'token' => $token->plainTextToken,
                'tenant' => $tenant->only('id', 'name', 'slug'),
                'user' => $owner->only('id', 'name', 'email'),
                'expires_at' => now()->addHours(24)->toISOString(),
            ],
        ]);
    }

    public function stopImpersonation(Request $request): JsonResponse
    {
        // Revoke the impersonation token used in this request
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Impersonation ended.']);
    }
}
