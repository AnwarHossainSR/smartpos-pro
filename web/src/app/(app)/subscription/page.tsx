"use client";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { api, ApiError } from "@/lib/api";
import { formatCurrency, formatDate, formatNumber } from "@/lib/utils";
import { useQuery } from "@tanstack/react-query";
import { Check, Crown, MailWarning, Zap } from "lucide-react";
import { useRouter } from "next/navigation";

interface Plan {
  id: string;
  name: string;
  slug: string;
  price: number;
  interval: string;
  limits: Record<string, number | null>;
  features: string[] | null;
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

const FEATURE_LABELS: Record<string, string> = {
  branches: "Branches",
  warehouses: "Warehouses",
  users: "Team Members",
  products: "Products",
  monthly_sales: "Monthly Sales",
};

export default function SubscriptionPage() {
  const router = useRouter();

  const { data: plansData, isLoading: plansLoading, error: plansError } = useQuery<{ data: Plan[] }>({
    queryKey: ["subscription-plans"],
    queryFn: () => api<{ data: Plan[] }>("/subscription/plans"),
  });

  const { data: subData, isLoading: subLoading, error } = useQuery<{ data: SubscriptionResponse }>({
    queryKey: ["subscription"],
    queryFn: () => api<{ data: SubscriptionResponse }>("/subscription"),
    retry: (failureCount, err) => !(err instanceof ApiError && err.status === 403) && failureCount < 3,
  });

  const sub = subData?.data.subscription;
  const usage = subData?.data.usage;
  const plans = plansData?.data ?? [];
  const paidPlans = plans.filter((p) => p.price > 0);

  const statusColor: Record<string, "success" | "warning" | "danger" | "default"> = {
    active: "success",
    trialing: "warning",
    past_due: "danger",
    canceled: "default",
  };

  if (error instanceof ApiError && error.status === 403) {
    return (
      <div className="max-w-4xl space-y-5">
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

  return (
    <div className="max-w-4xl space-y-6">

      {subLoading ? (
        <div className="grid gap-4 sm:grid-cols-3">
          {Array.from({ length: 3 }).map((_, i) => (
            <Skeleton key={i} className="h-64 rounded-[var(--radius)]" />
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

            {usage && (
              <div className="space-y-3 pt-2">
                <h3 className="text-sm font-medium">Plan Usage</h3>
                {Object.entries(usage).map(([key, { used, limit }]) => {
                  const isUnlimited = limit === null || limit < 0;
                  const pct = isUnlimited ? 100 : Math.min(100, (used / limit) * 100);
                  return (
                    <div key={key}>
                      <div className="mb-1 flex items-center justify-between text-sm">
                        <span>{FEATURE_LABELS[key] ?? key.replace(/_/g, " ")}</span>
                        <span className="text-[var(--muted)]">
                          {formatNumber(used)} / {isUnlimited ? "∞" : formatNumber(limit)}
                        </span>
                      </div>
                      <div className="h-2 rounded-full bg-[var(--surface-2)]">
                        <div
                          className={`h-2 rounded-full transition-all ${
                            pct >= 90
                              ? "bg-[var(--color-danger)]"
                              : pct >= 70
                                ? "bg-amber-500"
                                : "bg-[var(--primary)]"
                          }`}
                          style={{ width: `${isUnlimited ? 30 : pct}%` }}
                        />
                      </div>
                    </div>
                  );
                })}
              </div>
            )}
          </CardContent>
        </Card>
      ) : null}

      <div className="space-y-3">
        <h2 className="text-lg font-semibold">{sub ? "Change Plan" : "Choose a Plan"}</h2>
        <p className="text-sm text-[var(--muted)]">
          {sub
            ? "Upgrade your plan to unlock more features and higher limits."
            : "Select a plan that fits your business needs."}
        </p>
      </div>

      {plansLoading ? (
        <div className="grid gap-4 sm:grid-cols-3">
          {Array.from({ length: 3 }).map((_, i) => (
            <Skeleton key={i} className="h-80 rounded-[var(--radius)]" />
          ))}
        </div>
      ) : (
        <div className="grid gap-4 sm:grid-cols-3">
          {paidPlans.map((plan) => {
            const isCurrent = sub?.plan.id === plan.id;
            const isDowngrade = !!sub && plan.price < sub.plan.price;

            return (
              <Card
                key={plan.id}
                className={`relative flex flex-col ${
                  isCurrent ? "border-2 border-[var(--primary)]" : ""
                }`}
              >
                {isCurrent && (
                  <div className="absolute -top-3 left-1/2 -translate-x-1/2">
                    <Badge tone="success">Current Plan</Badge>
                  </div>
                )}
                <CardHeader className="text-center">
                  <CardTitle>{plan.name}</CardTitle>
                  <div className="mt-2">
                    <span className="text-3xl font-bold">{formatCurrency(plan.price)}</span>
                    <span className="text-sm text-[var(--muted)]">/month</span>
                  </div>
                </CardHeader>
                <CardContent className="flex flex-1 flex-col">
                  <ul className="mb-6 flex-1 space-y-2">
                    {Object.entries(plan.limits).map(([key, limit]) => (
                      <li key={key} className="flex items-center gap-2 text-sm">
                        <Check className="h-4 w-4 text-[var(--primary)]" />
                        <span>
                          {FEATURE_LABELS[key] ?? key.replace(/_/g, " ")}:{" "}
                          {limit === null || limit < 0 ? "Unlimited" : formatNumber(limit)}
                        </span>
                      </li>
                    ))}
                  </ul>
                  <Button
                    variant={isCurrent ? "outline" : "primary"}
                    className="w-full"
                    disabled={isCurrent || isDowngrade}
                    onClick={() => router.push(`/subscription/checkout?plan=${plan.id}`)}
                  >
                    {isCurrent ? (
                      "Current Plan"
                    ) : isDowngrade ? (
                      "Downgrade"
                    ) : sub ? (
                      <>
                        <Zap className="mr-2 h-4 w-4" />
                        Upgrade
                      </>
                    ) : (
                      "Get Started"
                    )}
                  </Button>
                </CardContent>
              </Card>
            );
          })}
        </div>
      )}
    </div>
  );
}
