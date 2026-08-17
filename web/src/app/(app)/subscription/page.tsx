"use client";

import { Check, Crown, MailWarning, X } from "lucide-react";
import { useRouter } from "next/navigation";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { api, ApiError } from "@/lib/api";
import { formatCurrency, formatDate } from "@/lib/utils";
import { useQuery } from "@tanstack/react-query";

interface PlanFeature {
  key: string;
  label: string;
  value: string;
  included: boolean;
}

interface Plan {
  id: string;
  name: string;
  slug: string;
  price: number;
  interval: string;
  limits: Record<string, number | null>;
  features: PlanFeature[];
  is_active: boolean;
}

interface Subscription {
  id: string;
  plan: Plan;
  status: string;
  current_period_ends_at: string | null;
  trial_ends_at: string | null;
}

interface SubscriptionResponse {
  subscription: Subscription | null;
  usage: Record<string, { used: number; limit: number }>;
}

const PLAN_COLORS: Record<string, string> = {
  starter: "border-t-emerald-500",
  professional: "border-t-amber-500",
  business: "border-t-rose-500",
};

export default function SubscriptionPage() {
  const router = useRouter();

  const {
    data: plansData,
    isLoading: plansLoading,
    error: plansError,
  } = useQuery<{ data: Plan[] }>({
    queryKey: ["subscription-plans"],
    queryFn: () => api<{ data: Plan[] }>("/subscription/plans"),
  });

  const {
    data: subData,
    isLoading: subLoading,
    error: subError,
  } = useQuery<{ data: SubscriptionResponse }>({
    queryKey: ["subscription"],
    queryFn: () => api<{ data: SubscriptionResponse }>("/subscription"),
    retry: (failureCount, err) =>
      !(err instanceof ApiError && err.status === 403) && failureCount < 3,
  });

  const sub = subData?.data.subscription;
  const plans = plansData?.data ?? [];

  const statusColor: Record<string, "success" | "warning" | "danger" | "default"> = {
    active: "success",
    trialing: "warning",
    past_due: "danger",
    canceled: "default",
  };

  if (subError instanceof ApiError && subError.status === 403) {
    return (
      <div className="max-w-5xl space-y-5">
        <h1 className="text-xl font-semibold">Subscription</h1>
        <Card>
          <CardContent className="flex flex-col items-center gap-2 py-10 text-center text-[var(--muted)]">
            <MailWarning className="h-6 w-6" />
            <p>Verify your email address to view billing and subscription details.</p>
          </CardContent>
        </Card>
      </div>
    );
  }

  if (plansError) {
    return (
      <div className="max-w-5xl space-y-5">
        <h1 className="text-xl font-semibold">Subscription</h1>
        <Card>
          <CardContent className="py-8 text-center text-[var(--muted)]">
            Failed to load subscription plans. Please try again later.
          </CardContent>
        </Card>
      </div>
    );
  }

  return (
    <div className="max-w-5xl space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-xl font-semibold">Subscription</h1>
      </div>

      {subLoading ? (
        <div className="grid gap-4 sm:grid-cols-3">
          {Array.from({ length: 3 }).map((_, i) => (
            <Skeleton key={i} className="h-[500px] rounded-[var(--radius)]" />
          ))}
        </div>
      ) : sub ? (
        <Card>
          <CardHeader>
            <div className="flex items-center justify-between">
              <CardTitle className="flex items-center gap-2">
                <Crown className="h-5 w-5 text-[var(--primary)]" />
                Current Plan: {sub.plan.name}
              </CardTitle>
              <Badge tone={statusColor[sub.status] ?? "default"}>{sub.status}</Badge>
            </div>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="flex justify-between text-sm">
                <span className="text-[var(--muted)]">Price</span>
                <span className="font-medium">{formatCurrency(sub.plan.price)}/month</span>
              </div>
              <div className="flex justify-between text-sm">
                <span className="text-[var(--muted)]">Billing period ends</span>
                <span>{formatDate(sub.current_period_ends_at)}</span>
              </div>
              {sub.trial_ends_at && (
                <div className="flex justify-between text-sm">
                  <span className="text-[var(--muted)]">Trial ends</span>
                  <span>{formatDate(sub.trial_ends_at)}</span>
                </div>
              )}
            </div>
          </CardContent>
        </Card>
      ) : null}

      <div className="space-y-3">
        <h2 className="text-lg font-semibold">{sub ? "Change Plan" : "Choose a Plan"}</h2>
        <p className="text-sm text-[var(--muted)]">
          {sub
            ? "Upgrade your plan to unlock more features and higher limits."
            : "Start with a 30-day free trial. No credit card required."}
        </p>
      </div>

      {plansLoading ? (
        <div className="grid gap-4 sm:grid-cols-3">
          {Array.from({ length: 3 }).map((_, i) => (
            <Skeleton key={i} className="h-[500px] rounded-[var(--radius)]" />
          ))}
        </div>
      ) : (
        <div className="grid gap-4 sm:grid-cols-3">
          {plans.map((plan) => {
            const isCurrent = sub?.plan.id === plan.id;
            const isDowngrade = !!sub && plan.price < sub.plan.price;

            return (
              <div
                key={plan.id}
                className={`flex flex-col rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)] border-t-[3px] ${
                  PLAN_COLORS[plan.slug] ?? "border-t-[var(--primary)]"
                } ${isCurrent ? "ring-2 ring-[var(--primary)]" : ""}`}
              >
                <div className="p-5">
                  <p className="text-xs text-[var(--muted)]">Per month</p>
                  <h3 className="mt-2 text-xl font-bold">{plan.name}</h3>
                  <p className="mt-1 text-sm text-[var(--muted)]">
                    {plan.slug === "starter"
                      ? "Essential POS and inventory for new retailers starting their online journey."
                      : plan.slug === "professional"
                        ? "Growing sales and business management with advanced features."
                        : "Full analytics, integration and marketing for established businesses."}
                  </p>

                  <div className="mt-4 flex items-baseline gap-1">
                    <span className="text-3xl font-bold">{formatCurrency(plan.price)}</span>
                    <span className="text-sm text-[var(--muted)]">/month</span>
                  </div>

                  <Button
                    variant={isCurrent ? "outline" : "primary"}
                    className="mt-5 w-full"
                    disabled={isCurrent || isDowngrade}
                    onClick={() =>
                      router.push(`/subscription/checkout?plan=${plan.id}`)
                    }
                  >
                    {isCurrent
                      ? "Current Plan"
                      : isDowngrade
                        ? "Downgrade"
                        : "Start 30-day Free Trial"}
                  </Button>
                </div>

                <div className="flex-1 space-y-2.5 border-t border-[var(--border)] p-5">
                  {plan.features.map((feature) => (
                    <div key={feature.key} className="flex items-center gap-2 text-sm">
                      {feature.included ? (
                        <Check className="h-4 w-4 shrink-0 text-emerald-500" />
                      ) : (
                        <X className="h-4 w-4 shrink-0 text-[var(--color-danger)]" />
                      )}
                      <span className={feature.included ? "" : "text-[var(--muted)]"}>
                        {feature.label}
                        {feature.value ? `: ${feature.value}` : ""}
                      </span>
                    </div>
                  ))}
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
