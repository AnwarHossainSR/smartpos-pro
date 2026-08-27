import { api, type Paginated } from "@/lib/api";
import { buildListQuery, type ResourceListParams } from "@/lib/use-resource-list";
import type {
  Tenant,
  TenantDetail,
  RevenueMetrics,
  Impersonation,
  PlatformUser,
  AuditLog,
  SystemHealth,
  SuperAdminPlan,
  PlatformSettings,
  BackupLog,
  SupportTicket,
  TicketMetrics,
} from "./types";

export type TenantFilters = { status: string };

export type UserFilters = { status: string };

export const superAdminApi = {
  tenants: {
    list: (params: ResourceListParams<TenantFilters>) =>
      api<Paginated<Tenant>>(`/super/tenants?${buildListQuery(params)}`),
    detail: (id: string) =>
      api<{ data: TenantDetail }>(`/super/tenants/${id}`),
    create: (data: {
      tenant_name: string;
      slug: string;
      business_name: string;
      owner_name: string;
      owner_email: string;
      owner_password: string;
      owner_password_confirmation: string;
      base_currency?: string;
    }) =>
      api<{ data: Tenant }>("/super/tenants", {
        method: "POST",
        body: data,
      }),
    suspend: (id: string) =>
      api<{ data: Tenant }>(`/super/tenants/${id}/suspend`, { method: "POST" }),
    activate: (id: string) =>
      api<{ data: Tenant }>(`/super/tenants/${id}/activate`, { method: "POST" }),
    update: (id: string, data: { name?: string; plan?: string; trial_ends_at?: string | null; base_currency?: string }) =>
      api<{ data: Tenant }>(`/super/tenants/${id}`, {
        method: "PUT",
        body: data,
      }),
    remove: (id: string) =>
      api<void>(`/super/tenants/${id}`, { method: "DELETE" }),
    impersonate: (id: string, reason: string) =>
      api<{ data: { impersonation_id: string; token: string; tenant: { id: string; name: string }; user: { id: string; name: string; email: string }; expires_at: string } }>(
        `/super/tenants/${id}/impersonate`,
        { method: "POST", body: { reason } },
      ),
  },
  revenue: {
    metrics: (days = 30) =>
      api<{ data: RevenueMetrics }>(`/super/metrics/revenue?days=${days}`),
  },
  impersonations: {
    list: () =>
      api<{ data: Impersonation[] }>("/super/impersonations"),
    revoke: (id: string) =>
      api<{ message: string }>(`/super/impersonation/${id}/revoke`, { method: "POST" }),
  },
  users: {
    list: (params: ResourceListParams<UserFilters>) =>
      api<Paginated<PlatformUser>>(`/super/users?${buildListQuery(params)}`),
    detail: (id: string) =>
      api<{ data: PlatformUser }>(`/super/users/${id}`),
    toggleStatus: (id: string, status: string) =>
      api<{ data: PlatformUser }>(`/super/users/${id}/status`, { method: "PUT", body: { status } }),
    forceLogout: (id: string) =>
      api<{ message: string }>(`/super/users/${id}/force-logout`, { method: "POST" }),
  },
  audit: {
    list: (params: ResourceListParams<{ action: string; tenant_id: string; user_id: string; from: string; to: string }>) =>
      api<Paginated<AuditLog>>(`/super/audit-logs?${buildListQuery(params)}`),
  },
  health: {
    get: () =>
      api<{ data: SystemHealth }>("/super/health"),
  },
  plans: {
    list: (params: ResourceListParams<Record<string, string>>) =>
      api<Paginated<SuperAdminPlan>>(`/super/plans?${buildListQuery(params)}`),
    store: (data: {
      name: string;
      slug: string;
      price: number;
      currency: string;
      trial_days?: number;
      limits?: Record<string, unknown>;
      features?: string[];
      is_active?: boolean;
    }) =>
      api<{ data: SuperAdminPlan }>("/super/plans", { method: "POST", body: data }),
    update: (
      id: string,
      data: {
        name: string;
        slug: string;
        price: number;
        currency: string;
        trial_days?: number;
        limits?: Record<string, unknown>;
        features?: string[];
        is_active?: boolean;
      },
    ) =>
      api<{ data: SuperAdminPlan }>(`/super/plans/${id}`, { method: "PUT", body: data }),
    delete: (id: string) =>
      api<{ message: string }>(`/super/plans/${id}`, { method: "DELETE" }),
  },
  settings: {
    get: () =>
      api<{ data: PlatformSettings }>("/super/settings"),
    update: (settings: Partial<PlatformSettings>) =>
      api<{ data: PlatformSettings }>("/super/settings", {
        method: "PUT",
        body: { settings },
      }),
    getTenantOverrides: () =>
      api<{ data: Record<string, Record<string, boolean>> }>("/super/settings/tenant-overrides"),
    updateTenantOverrides: (tenantId: string, modules: Record<string, boolean>) =>
      api<{ data: Record<string, Record<string, boolean>> }>("/super/settings/tenant-overrides", {
        method: "PUT",
        body: { tenant_id: tenantId, modules },
      }),
  },
  backups: {
    list: (params: ResourceListParams<Record<string, string>>) =>
      api<Paginated<BackupLog>>(`/super/backups?${buildListQuery(params)}`),
    create: () =>
      api<{ data: BackupLog }>("/super/backups", { method: "POST" }),
    download: (id: string) =>
      api<{ data: { url: string; filename: string; expires_at: string } }>(
        `/super/backups/${id}/download`,
      ),
    remove: (id: string) =>
      api<void>(`/super/backups/${id}`, { method: "DELETE" }),
  },
};

// Support Ticket API
export async function getSupportTickets(params?: Record<string, string | number | undefined>) {
  const searchParams = new URLSearchParams();
  if (params) {
    Object.entries(params).forEach(([key, value]) => {
      if (value !== undefined && value !== "") {
        searchParams.set(key, String(value));
      }
    });
  }
  const qs = searchParams.toString();
  return api<Paginated<SupportTicket>>(`/super/support/tickets${qs ? `?${qs}` : ""}`);
}

export async function getSupportTicket(id: string) {
  return api<{ data: SupportTicket }>(`/super/support/tickets/${id}`);
}

export async function updateTicketStatus(id: string, status: string) {
  return api<{ data: SupportTicket }>(`/super/support/tickets/${id}/status`, {
    method: "PUT",
    body: { status },
  });
}

export async function replyToTicket(id: string, message: string) {
  return api<{ data: import("./types").TicketReply }>(`/super/support/tickets/${id}/reply`, {
    method: "POST",
    body: { message },
  });
}

export async function addInternalNote(id: string, message: string) {
  return api<{ data: import("./types").TicketReply }>(`/super/support/tickets/${id}/notes`, {
    method: "POST",
    body: { message },
  });
}

export async function getTicketMetrics(tenantId?: string) {
  const qs = tenantId ? `?tenant_id=${tenantId}` : "";
  return api<{ data: TicketMetrics }>(`/super/support/tickets/metrics${qs}`);
}

export async function getEnabledModules(): Promise<Record<string, boolean>> {
  const res = await api<{ data: Record<string, boolean> }>("/modules");
  return res.data;
}
