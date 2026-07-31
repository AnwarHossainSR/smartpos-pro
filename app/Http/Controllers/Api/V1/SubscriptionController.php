<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Billing\PlanLimitService;
use Illuminate\Http\JsonResponse;

class SubscriptionController extends Controller
{
    public function __construct(protected PlanLimitService $limits) {}

    /** Publicly-listable catalogue of active plans. */
    public function plans(): JsonResponse
    {
        return response()->json([
            'data' => Plan::where('is_active', true)->orderBy('price')->get(),
        ]);
    }

    /** The current tenant's active subscription with plan and live usage snapshot. */
    public function current(): JsonResponse
    {
        $subscription = Subscription::with('plan')->where('status', SubscriptionStatus::Active)->latest('id')->first();

        return response()->json([
            'data' => [
                'subscription' => $subscription,
                'usage' => $this->limits->usage(),
            ],
        ]);
    }

    /** Live per-feature usage vs plan limits. */
    public function usage(): JsonResponse
    {
        return response()->json(['data' => $this->limits->usage()]);
    }
}
