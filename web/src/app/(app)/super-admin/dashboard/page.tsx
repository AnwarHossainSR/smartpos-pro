import { useQuery } from "@tanstack/react-query";
import { Activity, CreditCard, Plus } from "lucide-react";
import { useState } from "react";
import { Link } from "react-router";
import { StatCard } from "@/components/stat-card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { superAdminApi } from "@/features/super-admin";
import { CreateTenantDialog } from "@/features/super-admin/components/create-tenant-dialog";
import { useTenantMutations, useUsers } from "@/features/super-admin/hooks";
import type { AuditLog } from "@/features/super-admin/types";
import { queryKeys } from "@/lib/query-keys";
import { formatCurrency, formatNumber } from "@/lib/utils";

function formatAction(action: string): string {
  return action
    .split(".")
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(" ");
}

function getActionBadgeTone(action: string): "success" | "warning" | "danger" | "info" {
  if (action.includes("create")) return "success";
  if (action.includes("update") || action.includes("edit")) return "warning";
  if (action.includes("delete") || action.includes("remove")) return "danger";
  return "info";
}

export default function SuperAdminDashboardPage() {
  const [creating, setCreating] = useState(false);
  const mutations = useTenantMutations();

  const { data: metricsData, isLoading: metricsLoading } = useQuery({
    queryKey: queryKeys.superAdmin.revenueMetrics(),
    queryFn: () => superAdminApi.revenue.metrics(),
  });

  const userList = useUsers();

  const { data: auditData, isLoading: auditLoading } = useQuery({
    queryKey: ["super-admin", "dashboard-audit"],
    queryFn: () =>
      superAdminApi.audit.list({
        search: "",
        sort: "-created_at",
        page: 1,
        perPage: 10,
        filters: {},
      }),
  });

  const m = metricsData?.data;
  const totalTenants = (m?.active_tenants ?? 0) + (m?.suspended_tenants ?? 0);
  const totalUsers = userList.meta?.total ?? 0;

  return (
    <div className="space-y-6">
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard
          label="Total Tenants"
          value={formatNumber(totalTenants)}
          loading={metricsLoading}
        />
        <StatCard
          label="Active Tenants"
          value={formatNumber(m?.active_tenants ?? 0)}
          loading={metricsLoading}
          tone="success"
        />
        <StatCard
          label="Total Users"
          value={formatNumber(totalUsers)}
          loading={userList.isLoading}
        />
        <StatCard
          label="MRR"
          value={formatCurrency(m?.total_mrr ?? 0)}
          loading={metricsLoading}
          tone="success"
        />
      </div>

      <div className="flex flex-wrap gap-3">
        <Button size="sm" onClick={() => setCreating(true)}>
          <Plus className="h-4 w-4" />
          Create Tenant
        </Button>
        <Button asChild variant="outline" size="sm">
          <Link to="/super-admin/health">
            <Activity className="h-4 w-4" />
            View Health
          </Link>
        </Button>
        <Button asChild variant="outline" size="sm">
          <Link to="/super-admin/plans">
            <CreditCard className="h-4 w-4" />
            Manage Plans
          </Link>
        </Button>
      </div>

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>Recent Activity</CardTitle>
          </CardHeader>
          <CardContent>
            {auditLoading ? (
              <div className="space-y-3">
                {Array.from({ length: 5 }).map((_, i) => (
                  <div key={i} className="flex items-center gap-3">
                    <Skeleton className="h-5 w-16" />
                    <Skeleton className="h-4 flex-1" />
                    <Skeleton className="h-4 w-20" />
                  </div>
                ))}
              </div>
            ) : auditData?.data?.length ? (
              <div className="space-y-3">
                {auditData.data.map((log: AuditLog) => (
                  <div key={log.id} className="flex items-center gap-3 text-sm">
                    <Badge tone={getActionBadgeTone(log.action)} className="shrink-0">
                      {formatAction(log.action)}
                    </Badge>
                    <span className="flex-1 truncate text-[var(--muted)]">
                      {log.user ? log.user.name : "System"}
                      {" — "}
                      {log.auditable_type.split("\\").pop()} #{log.auditable_id}
                    </span>
                    <span className="shrink-0 text-xs text-[var(--muted)]">
                      {new Date(log.created_at).toLocaleDateString()}
                    </span>
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-sm text-[var(--muted)]">No recent activity.</p>
            )}
          </CardContent>
        </Card>

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
                      <span className="text-[var(--muted)]">
                        {formatNumber(plan.count)} tenants
                      </span>
                      <span className="font-medium">{formatCurrency(plan.mrr)}</span>
                    </div>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>
        ) : null}
      </div>

      <CreateTenantDialog
        open={creating}
        onClose={() => setCreating(false)}
        onSubmit={(data) =>
          mutations.create.mutate(data, {
            onSuccess: () => setCreating(false),
          })
        }
        loading={mutations.create.isPending}
      />
    </div>
  );
}
