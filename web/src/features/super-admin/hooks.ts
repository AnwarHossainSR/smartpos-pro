import { useEffect } from "react";
import { toast } from "@/components/ui/toast";
import { queryKeys } from "@/lib/query-keys";
import { useResourceList } from "@/lib/use-resource-list";
import { useModulesStore } from "@/stores/modules";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  addInternalNote,
  getEnabledModules,
  getSupportTicket,
  getSupportTickets,
  getTicketMetrics,
  replyToTicket,
  superAdminApi,
  updateTicketStatus,
} from "./api";
import type { AuditLog, TicketMetrics } from "./types";

export function useTenants() {
  return useResourceList({
    queryKey: queryKeys.superAdmin.tenants(),
    queryFn: superAdminApi.tenants.list,
    defaultSort: "-created_at",
    filterKeys: ["status"] as const,
  });
}

export function useUsers() {
  return useResourceList({
    queryKey: queryKeys.superAdmin.users(),
    queryFn: superAdminApi.users.list,
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

export function useUserMutations() {
  const qc = useQueryClient();

  const toggleStatus = useMutation({
    mutationFn: ({ id, status }: { id: string; status: string }) =>
      superAdminApi.users.toggleStatus(id, status),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: queryKeys.superAdmin.users() });
      toast("User status updated", "success");
    },
  });

  const forceLogout = useMutation({
    mutationFn: superAdminApi.users.forceLogout,
    onSuccess: () => {
      toast("User logged out", "success");
    },
  });

  return { toggleStatus, forceLogout };
}

export function useAuditLogs() {
  return useResourceList<AuditLog, { action: string; tenant_id: string; user_id: string; from: string; to: string }>({
    queryKey: queryKeys.superAdmin.audit(),
    queryFn: superAdminApi.audit.list,
    defaultSort: "-created_at",
    filterKeys: ["action", "tenant_id", "user_id", "from", "to"] as const,
  });
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

export function useBackups() {
  const list = useResourceList({
    queryKey: queryKeys.superAdmin.backups(),
    queryFn: superAdminApi.backups.list,
    defaultSort: "-created_at",
  });

  // Poll every 3s when there are pending backups
  const hasPending = list.data?.some((b) => b.status === "pending") ?? false;

  useQuery({
    queryKey: [...queryKeys.superAdmin.backups(), "poll"],
    queryFn: async () => {
      await list.refetch();
      return null;
    },
    enabled: hasPending,
    refetchInterval: 3000,
    refetchIntervalInBackground: true,
  });

  return list;
}

export function useBackupMutations() {
  const qc = useQueryClient();

  const create = useMutation({
    mutationFn: superAdminApi.backups.create,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: queryKeys.superAdmin.backups() });
      toast("Backup started", "success");
    },
  });

  const remove = useMutation({
    mutationFn: superAdminApi.backups.remove,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: queryKeys.superAdmin.backups() });
      toast("Backup deleted", "success");
    },
  });

  const download = useMutation({
    mutationFn: superAdminApi.backups.download,
  });

  return { create, remove, download };
}

export function usePlans() {
  return useResourceList({
    queryKey: queryKeys.superAdmin.plans(),
    queryFn: superAdminApi.plans.list,
    defaultSort: "-created_at",
  });
}

export function usePlanMutations() {
  const qc = useQueryClient();

  const create = useMutation({
    mutationFn: superAdminApi.plans.store,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: queryKeys.superAdmin.plans() });
      toast("Plan created", "success");
    },
  });

  const update = useMutation({
    mutationFn: ({ id, data }: { id: string; data: Parameters<typeof superAdminApi.plans.update>[1] }) =>
      superAdminApi.plans.update(id, data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: queryKeys.superAdmin.plans() });
      toast("Plan updated", "success");
    },
  });

  const remove = useMutation({
    mutationFn: superAdminApi.plans.delete,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: queryKeys.superAdmin.plans() });
      toast("Plan deleted", "success");
    },
  });

  return { create, update, remove };
}

export function usePlatformSettings() {
  const qc = useQueryClient();

  const query = useQuery({
    queryKey: queryKeys.superAdmin.settings(),
    queryFn: superAdminApi.settings.get,
  });

  const update = useMutation({
    mutationFn: superAdminApi.settings.update,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: queryKeys.superAdmin.settings() });
      toast("Settings updated", "success");
    },
  });

  return { ...query, update };
}

// Support Ticket hooks
export function useSupportTickets(params?: Record<string, string | number | undefined>) {
  return useQuery({
    queryKey: ["super-admin", "support-tickets", params],
    queryFn: () => getSupportTickets(params),
  });
}

export function useSupportTicket(id: string | null) {
  return useQuery({
    queryKey: ["super-admin", "support-ticket", id],
    queryFn: () => getSupportTicket(id!),
    enabled: !!id,
  });
}

export function useTicketMetrics(tenantId?: string) {
  return useQuery<{ data: TicketMetrics }>({
    queryKey: ["super-admin", "ticket-metrics", tenantId],
    queryFn: () => getTicketMetrics(tenantId),
  });
}

export function useUpdateTicketStatus() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, status }: { id: string; status: string }) => updateTicketStatus(id, status),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["super-admin", "support-tickets"] });
      qc.invalidateQueries({ queryKey: ["super-admin", "ticket-metrics"] });
      toast("Ticket status updated", "success");
    },
  });
}

export function useReplyToTicket() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, message }: { id: string; message: string }) => replyToTicket(id, message),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["super-admin", "support-tickets"] });
      toast("Reply sent", "success");
    },
  });
}

export function useAddInternalNote() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, message }: { id: string; message: string }) => addInternalNote(id, message),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["super-admin", "support-tickets"] });
      toast("Internal note added", "success");
    },
  });
}

export function useModules() {
  const setModules = useModulesStore((s) => s.setModules);

  const query = useQuery({
    queryKey: queryKeys.modules,
    queryFn: getEnabledModules,
    staleTime: 5 * 60 * 1000,
  });

  useEffect(() => {
    if (query.data) {
      setModules(query.data);
    }
  }, [query.data, setModules]);

  return query;
}
