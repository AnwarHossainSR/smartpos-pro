import { useQuery } from "@tanstack/react-query";
import { queryKeys } from "@/lib/query-keys";
import { superAdminApi } from "@/features/super-admin";
import { StatCard } from "@/components/stat-card";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { formatCurrency, formatNumber } from "@/lib/utils";

export default function SuperAdminDashboardPage() {
  const { data: metricsData, isLoading: metricsLoading } = useQuery({
    queryKey: queryKeys.superAdmin.revenueMetrics(),
    queryFn: () => superAdminApi.revenue.metrics(),
  });

  const { data: impersonationsData, isLoading: impersonationsLoading } = useQuery({
    queryKey: queryKeys.superAdmin.impersonations(),
    queryFn: () => superAdminApi.impersonations.list(),
  });

  const m = metricsData?.data;
  const totalTenants = (m?.active_tenants ?? 0) + (m?.suspended_tenants ?? 0);
  const activeImpersonations = impersonationsData?.data?.filter((i) => !i.ended_at).length ?? 0;

  return (
    <div className="space-y-6">
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard label="Total Tenants" value={formatNumber(totalTenants)} loading={metricsLoading} />
        <StatCard label="Active Tenants" value={formatNumber(m?.active_tenants ?? 0)} loading={metricsLoading} tone="success" />
        <StatCard label="MRR" value={formatCurrency(m?.total_mrr ?? 0)} loading={metricsLoading} tone="success" />
        <StatCard label="Active Impersonations" value={formatNumber(activeImpersonations)} loading={impersonationsLoading} tone={activeImpersonations > 0 ? "warning" : "default"} />
      </div>

      {m?.plan_distribution?.length ? (
        <Card>
          <CardHeader>
            <CardTitle>Plan Distribution</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-3">
              {m.plan_distribution.map((plan) => (
                <div key={plan.slug} className="flex items-center justify-between">
                  <div className="flex items-center gap-3">
                    <span className="text-sm font-medium">{plan.plan_name}</span>
                  </div>
                  <div className="flex items-center gap-4 text-sm">
                    <span className="text-[var(--muted)]">{formatNumber(plan.count)} tenants</span>
                    <span className="font-medium">{formatCurrency(plan.mrr)}</span>
                  </div>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      ) : null}
    </div>
  );
}
