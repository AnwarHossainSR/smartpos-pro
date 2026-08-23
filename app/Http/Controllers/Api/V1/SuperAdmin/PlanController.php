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

        $subscription = Subscription::withoutTenancy()
            ->where('tenant_id', $tenantId)
            ->where('status', '!=', 'cancelled')
            ->first();

        if ($subscription) {
            $oldPlanId = $subscription->plan_id;
            $subscription->update([
                'plan_id' => $data['plan_id'],
                'status' => 'active',
                'current_period_ends_at' => $data['plan_expires_at'] ?? $subscription->current_period_ends_at,
            ]);
        } else {
            $oldPlanId = null;
            Subscription::withoutTenancy()->create([
                'tenant_id' => $tenantId,
                'plan_id' => $data['plan_id'],
                'status' => 'active',
                'current_period_ends_at' => $data['plan_expires_at'] ?? now()->addMonth(),
            ]);
        }

        $plan = Plan::find($data['plan_id']);
        $tenant->update(['plan' => $plan->slug]);

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
