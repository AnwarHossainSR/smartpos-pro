<?php

namespace App\Services\Billing;

use App\Domain\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubscriptionService
{
    private const TRIAL_DAYS = 30;

    public function checkout(Plan $plan, User $user): Subscription
    {
        return DB::transaction(function () use ($plan) {
            $existing = Subscription::where('status', SubscriptionStatus::Active)->latest('id')->first();

            if ($existing) {
                if ($existing->plan_id === $plan->id) {
                    throw ValidationException::withMessages([
                        'plan' => ['You are already on this plan.'],
                    ]);
                }

                if ($existing->plan->price > $plan->price) {
                    throw ValidationException::withMessages([
                        'plan' => ['Cannot downgrade your plan. Please contact support.'],
                    ]);
                }

                return $this->upgrade($existing, $plan);
            }

            return Subscription::create([
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Trialing,
                'trial_ends_at' => now()->addDays(self::TRIAL_DAYS),
                'current_period_ends_at' => now()->addDays(self::TRIAL_DAYS),
            ]);
        });
    }

    public function upgrade(Subscription $subscription, Plan $newPlan): Subscription
    {
        $subscription->update([
            'plan_id' => $newPlan->id,
            'status' => SubscriptionStatus::Active,
            'trial_ends_at' => null,
            'current_period_ends_at' => now()->addDays(30),
        ]);

        return $subscription->load('plan');
    }

    public function cancel(Subscription $subscription): Subscription
    {
        $subscription->update([
            'status' => SubscriptionStatus::Cancelled,
        ]);

        return $subscription->load('plan');
    }
}
