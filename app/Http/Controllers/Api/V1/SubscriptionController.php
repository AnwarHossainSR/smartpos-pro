<?php

namespace App\Http\Controllers\Api\V1;

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

    /** The current tenant's subscription, plan and live usage-vs-limits snapshot. */
    public function current(): JsonResponse
    {
        $subscription = Subscription::with('plan')->latest('id')->first();

        return response()->json([
            'data' => [
                'subscription' => $subscription,
                'usage' => $this->limits->usage(),
            ],
        ]);
    }
}
