<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\CreatePlanRequest;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Audit\AuditLogService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlanController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AuditLogService $audit,
    ) {}

    public function listPlans(): JsonResponse
    {
        $plans = Plan::orderBy('created_at', 'desc')->paginate();

        return $this->success($plans);
    }

    public function storePlan(CreatePlanRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        $plan = Plan::create($data);

        $this->audit->log('plan.created', $plan, null, [
            'name' => $plan->name,
            'slug' => $plan->slug,
            'price' => $plan->price,
        ]);

        return $this->created($plan);
    }

    public function updatePlan(CreatePlanRequest $request, string $id): JsonResponse
    {
        $plan = Plan::findOrFail($id);
        $oldValues = $plan->only(array_keys($request->validated()));
        $plan->update($request->validated());

        $this->audit->log('plan.updated', $plan, $oldValues, $plan->fresh()->only(array_keys($request->validated())));

        return $this->success($plan->fresh());
    }

    public function destroyPlan(string $id): JsonResponse
    {
        $plan = Plan::findOrFail($id);

        // A live subscription pointing at a missing plan silently falls back to the free
        // tier's limits, so paying tenants would quietly lose capacity. Deactivating is
        // the reversible way to retire a plan — it already hides it from the catalogue.
        $inUse = Subscription::withoutTenancy()
            ->where('plan_id', $plan->id)
            ->where('status', '!=', 'cancelled')
            ->count();

        if ($inUse > 0) {
            throw ValidationException::withMessages([
                'plan' => ["{$inUse} tenant(s) are subscribed to this plan. Deactivate it instead of deleting it."],
            ]);
        }

        $planData = $plan->only('id', 'name', 'slug', 'price');
        $plan->delete();

        $this->audit->log('plan.deleted', $plan, $planData, null);

        return $this->noContent();
    }

    public function assignPlan(Request $request, string $tenantId): JsonResponse
    {
        $data = $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'plan_expires_at' => 'nullable|date|after:now',
        ]);

        $tenant = Tenant::findOrFail($tenantId);
        $plan = Plan::findOrFail($data['plan_id']);

        // `tenants.plan` and `subscriptions.plan_id` feed two different limit paths, so
        // they must not be able to end up disagreeing with each other.
        $oldPlanId = DB::transaction(function () use ($data, $tenantId, $tenant, $plan) {
            $subscription = Subscription::withoutTenancy()
                ->where('tenant_id', $tenantId)
                ->where('status', '!=', 'cancelled')
                ->latest()
                ->first();

            if ($subscription) {
                $previous = $subscription->plan_id;
                $subscription->update([
                    'plan_id' => $plan->id,
                    'status' => 'active',
                    'current_period_ends_at' => $data['plan_expires_at'] ?? $subscription->current_period_ends_at,
                ]);
            } else {
                $previous = null;
                Subscription::withoutTenancy()->create([
                    'tenant_id' => $tenantId,
                    'plan_id' => $plan->id,
                    'status' => 'active',
                    'current_period_ends_at' => $data['plan_expires_at'] ?? now()->addMonth(),
                ]);
            }

            $tenant->update(['plan' => $plan->slug]);

            return $previous;
        });

        $this->audit->log('plan.assigned', $tenant, ['plan_id' => $oldPlanId], [
            'plan_id' => $data['plan_id'],
            'plan_slug' => $plan->slug,
        ], $request->user(), $tenantId);

        return $this->success([
            'tenant_id' => $tenantId,
            'plan_id' => $data['plan_id'],
            'plan' => $plan->only('id', 'name', 'slug', 'price'),
        ], 'Plan assigned to tenant.');
    }
}
