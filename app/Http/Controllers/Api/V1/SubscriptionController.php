<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\StoreSubscriptionRequest;
use App\Http\Resources\PlanResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Billing\PlanLimitService;
use App\Services\Billing\SubscriptionService;
use Illuminate\Http\JsonResponse;

class SubscriptionController extends Controller
{
    public function __construct(
        protected PlanLimitService $limits,
        protected SubscriptionService $subscriptions,
    ) {}

    public function plans(): JsonResponse
    {
        $plans = Plan::where('is_active', true)->orderBy('price')->get();

        return response()->json([
            'data' => PlanResource::collection($plans),
        ]);
    }

    public function current(): JsonResponse
    {
        $subscription = Subscription::with('plan')
            ->where('status', SubscriptionStatus::Active)
            ->latest('id')
            ->first();

        return response()->json([
            'data' => [
                'subscription' => $subscription ? new SubscriptionResource($subscription) : null,
                'usage' => $this->limits->usage(),
            ],
        ]);
    }

    public function usage(): JsonResponse
    {
        return response()->json(['data' => $this->limits->usage()]);
    }

    public function checkout(StoreSubscriptionRequest $request): JsonResponse
    {
        $plan = Plan::findOrFail($request->validated('plan_id'));
        $subscription = $this->subscriptions->checkout($plan, $request->user());

        return (new SubscriptionResource($subscription->load('plan')))
            ->response()
            ->setStatusCode(201);
    }
}
