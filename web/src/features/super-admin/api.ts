import { api, type Paginated } from "@/lib/api";
import { buildListQuery, type ResourceListParams } from "@/lib/use-resource-list";
import type { Tenant, TenantDetail, RevenueMetrics, Impersonation } from "./types";

export type TenantFilters = { status: string };

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
};
