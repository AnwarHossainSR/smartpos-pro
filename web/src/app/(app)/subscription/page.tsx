"use client";

import { MailWarning } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { api, ApiError } from "@/lib/api";
import { formatNumber, formatDate } from "@/lib/utils";
import { useQuery } from "@tanstack/react-query";

interface Plan {
  id: string;
  name: string;
  slug: string;
  price_monthly: number;
  price_yearly: number;
  limits: Record<string, number>;
}

interface Subscription {
  id: string;
  plan: Plan;
  status: string;
  current_period_ends_at: string | null;
  trial_ends_at: string | null;
}

interface UsageData {
  usage: Record<string, { used: number; limit: number }>;
}

interface SubscriptionResponse {
  subscription: Subscription | null;
  usage: UsageData["usage"];
}

export default function SubscriptionPage() {
  const { data: subData, isLoading: subLoading, error } = useQuery<{ data: SubscriptionResponse }>({
    queryKey: ["subscription"],
    queryFn: () => api<{ data: SubscriptionResponse }> ("/subscription"),
    retry: (failureCount, err) => !(err instanceof ApiError && err.status === 403) && failureCount < 3,
  });

  const sub = subData?.data.subscription;
  const usage = subData?.data.usage;

  const statusColor: Record<string, "success" | "warning" | "danger" | "default"> = {
    active: "success", trialing: "warning", past_due: "danger", canceled: "default",
  };

  if (error instanceof ApiError && error.status === 403) {
    return (
      <div className="max-w-2xl space-y-5">
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
    <div className="max-w-2xl space-y-5">
      {subLoading ? (
        <Skeleton className="h-40 rounded-[var(--radius)]" />
      ) : sub ? (
        <Card>
          <CardHeader>
            <div className="flex items-center justify-between">
              <CardTitle>{sub.plan.name} Plan</CardTitle>
              <Badge tone={statusColor[sub.status] ?? "default"}>{sub.status}</Badge>
            </div>
          </CardHeader>
          <CardContent className="space-y-2 text-sm">
            <div className="flex justify-between"><span className="text-[var(--muted)]">Billing period ends</span><span>{formatDate(sub.current_period_ends_at)}</span></div>
            {sub.trial_ends_at && <div className="flex justify-between"><span className="text-[var(--muted)]">Trial ends</span><span>{formatDate(sub.trial_ends_at)}</span></div>}
          </CardContent>
        </Card>
      ) : (
        <Card>
          <CardContent className="py-8 text-center text-[var(--muted)]">No active subscription. Contact support to upgrade.</CardContent>
        </Card>
      )}

      {usage && (
        <Card>
          <CardHeader><CardTitle>Plan usage</CardTitle></CardHeader>
          <CardContent className="space-y-4">
            {Object.entries(usage).map(([key, { used, limit }]) => {
              const pct = limit < 0 ? 100 : Math.min(100, (used / limit) * 100);
              const isUnlimited = limit < 0;
              return (
                <div key={key}>
                  <div className="mb-1 flex items-center justify-between text-sm">
                    <span className="capitalize">{key.replace(/_/g, " ")}</span>
                    <span className="text-[var(--muted)]">
                      {formatNumber(used)} / {isUnlimited ? "∞" : formatNumber(limit)}
                    </span>
                  </div>
                  <div className="h-2 rounded-full bg-[var(--surface-2)]">
                    <div
                      className={`h-2 rounded-full transition-all ${pct >= 90 ? "bg-[var(--color-danger)]" : pct >= 70 ? "bg-amber-500" : "bg-[var(--primary)]"}`}
                      style={{ width: `${isUnlimited ? 30 : pct}%` }}
                    />
                  </div>
                </div>
              );
            })}
          </CardContent>
        </Card>
      )}
    </div>
  );
}
