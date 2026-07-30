<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mail\ImpersonationStartedMail;
use App\Models\AppNotification;
use App\Models\Impersonation;
use App\Models\Sale;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
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

    protected const IMPERSONATION_MINUTES = 30;

    public function impersonate(Tenant $tenant, Request $request): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $admin = $request->user();

        // Find the owner of this tenant
        $owner = User::where('tenant_id', $tenant->id)
            ->whereHas('roles', fn ($q) => $q->where('slug', 'owner'))
            ->firstOrFail();

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

        return response()->json([
            'data' => [
                'impersonation_id' => $impersonation->id,
                'token' => $token->plainTextToken,
                'tenant' => $tenant->only('id', 'name', 'slug'),
                'user' => $owner->only('id', 'name', 'email'),
                'expires_at' => $expiresAt->toISOString(),
            ],
        ]);
    }

    /** Called from inside the impersonated session to end it early. */
    public function stopImpersonation(Request $request): JsonResponse
    {
        $tokenId = $request->user()->currentAccessToken()->id;

        $this->endImpersonation($tokenId, $request->user());

        return response()->json(['message' => 'Impersonation ended.']);
    }

    /** Called from the super-admin's own session to revoke a live impersonation remotely. */
    public function revokeImpersonation(Request $request, int $impersonation): JsonResponse
    {
        $record = Impersonation::whereKey($impersonation)->whereNull('ended_at')->firstOrFail();

        $this->endImpersonation($record->personal_access_token_id, $request->user(), $record);

        return response()->json(['message' => 'Impersonation revoked.']);
    }

    /** Every impersonation currently in progress (not yet ended). */
    public function activeImpersonations(): JsonResponse
    {
        $records = Impersonation::whereNull('ended_at')
            ->with(['tenant:id,name', 'superAdmin:id,name,email', 'targetUser:id,name,email'])
            ->latest('started_at')
            ->get();

        return response()->json(['data' => $records]);
    }

    protected function endImpersonation(?int $tokenId, User $endedBy, ?Impersonation $record = null): void
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
