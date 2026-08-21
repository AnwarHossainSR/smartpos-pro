import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { queryKeys } from "@/lib/query-keys";
import { superAdminApi } from "../api";
import { StatCard } from "@/components/stat-card";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { formatCurrency, formatDateTime } from "@/lib/utils";
import { Skeleton } from "@/components/ui/skeleton";

const PERIODS = [
  { label: "7d", days: 7 },
  { label: "30d", days: 30 },
  { label: "90d", days: 90 },
] as const;

export function RevenueDashboard() {
  const [days, setDays] = useState(30);

  const { data, isLoading } = useQuery({
    queryKey: queryKeys.superAdmin.revenueMetrics(days),
    queryFn: () => superAdminApi.revenue.metrics(days),
  });

  const m = data?.data;

  const statusTone = (status: string) => {
    if (status === "active") return "success" as const;
    if (status === "canceled") return "danger" as const;
    return "default" as const;
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-lg font-semibold">Revenue Dashboard</h1>
        <div className="flex gap-1">
          {PERIODS.map((p) => (
            <Button
              key={p.days}
              variant={days === p.days ? "primary" : "ghost"}
              size="sm"
              onClick={() => setDays(p.days)}
            >
              {p.label}
            </Button>
          ))}
        </div>
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard
          label="Total MRR"
          value={formatCurrency(m?.total_mrr ?? 0)}
          loading={isLoading}
          tone="success"
        />
        <StatCard
          label="Active Tenants"
          value={m?.active_tenants ?? 0}
          loading={isLoading}
        />
        <StatCard
          label="Suspended Tenants"
          value={m?.suspended_tenants ?? 0}
          loading={isLoading}
          tone={m && m.suspended_tenants > 0 ? "danger" : "default"}
        />
        <StatCard
          label={`New Tenants (last ${days}d)`}
          value={m?.new_tenants_last_days ?? 0}
          loading={isLoading}
          tone="success"
        />
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Plan Distribution</CardTitle>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="space-y-3">
              {Array.from({ length: 3 }).map((_, i) => (
                <Skeleton key={i} className="h-10 w-full" />
              ))}
            </div>
          ) : m?.plan_distribution?.length ? (
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-[var(--border)] text-left text-[var(--muted)]">
                  <th className="pb-2 font-medium">Plan Name</th>
                  <th className="pb-2 font-medium text-right">Tenants</th>
                  <th className="pb-2 font-medium text-right">MRR Contribution</th>
                </tr>
              </thead>
              <tbody>
                {m.plan_distribution.map((plan) => (
                  <tr key={plan.slug} className="border-b border-[var(--border)] last:border-0">
                    <td className="py-2.5 font-medium">{plan.plan_name}</td>
                    <td className="py-2.5 text-right">{plan.count}</td>
                    <td className="py-2.5 text-right">{formatCurrency(plan.mrr)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          ) : (
            <div className="flex h-24 items-center justify-center text-sm text-[var(--muted)]">
              No plan data available.
            </div>
          )}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Recent Subscriptions</CardTitle>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="space-y-3">
              {Array.from({ length: 4 }).map((_, i) => (
                <Skeleton key={i} className="h-10 w-full" />
              ))}
            </div>
          ) : m?.recent_subscriptions?.length ? (
            <div className="space-y-3">
              {m.recent_subscriptions.map((sub) => (
                <div
                  key={sub.id}
                  className="flex items-center justify-between rounded-lg border border-[var(--border)] p-3"
                >
                  <div className="flex flex-col gap-1">
                    <span className="text-sm font-medium">
                      {sub.plan?.name ?? "No plan"}
                    </span>
                    <span className="text-xs text-[var(--muted)]">
                      {formatDateTime(sub.created_at)}
                    </span>
                  </div>
                  <Badge tone={statusTone(sub.status)}>{sub.status}</Badge>
                </div>
              ))}
            </div>
          ) : (
            <div className="flex h-24 items-center justify-center text-sm text-[var(--muted)]">
              No recent subscriptions.
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
