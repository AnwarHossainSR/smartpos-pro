import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "@/components/ui/toast";
import { useResourceList } from "@/lib/use-resource-list";
import { queryKeys } from "@/lib/query-keys";
import { superAdminApi } from "./api";

export function useTenants() {
  return useResourceList({
    queryKey: queryKeys.superAdmin.tenants(),
    queryFn: superAdminApi.tenants.list,
    defaultSort: "-created_at",
    filterKeys: ["status"] as const,
  });
}

export function useRevenueMetrics(days = 30) {
  return useQuery({
    queryKey: queryKeys.superAdmin.revenueMetrics(days),
    queryFn: () => superAdminApi.revenue.metrics(days),
  });
}

export function useTenantMutations() {
  const qc = useQueryClient();

  const suspend = useMutation({
    mutationFn: superAdminApi.tenants.suspend,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: queryKeys.superAdmin.all });
      toast("Tenant suspended", "success");
    },
  });

  const activate = useMutation({
    mutationFn: superAdminApi.tenants.activate,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: queryKeys.superAdmin.all });
      toast("Tenant activated", "success");
    },
  });

  const remove = useMutation({
    mutationFn: superAdminApi.tenants.remove,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: queryKeys.superAdmin.all });
      toast("Tenant deleted", "success");
    },
  });

  const impersonate = useMutation({
    mutationFn: ({ id, reason }: { id: string; reason: string }) =>
      superAdminApi.tenants.impersonate(id, reason),
    onSuccess: () => {
      toast("Impersonation session started", "success");
    },
  });

  const create = useMutation({
    mutationFn: superAdminApi.tenants.create,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: queryKeys.superAdmin.all });
      toast("Tenant created", "success");
    },
  });

  return { create, suspend, activate, remove, impersonate };
}

export function useImpersonationMutations() {
  const qc = useQueryClient();

  const revoke = useMutation({
    mutationFn: superAdminApi.impersonations.revoke,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: queryKeys.superAdmin.impersonations() });
      toast("Impersonation revoked", "success");
    },
  });

  return { revoke };
}
