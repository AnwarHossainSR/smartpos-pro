import { api, type Paginated } from "@/lib/api";
import { buildListQuery, type ResourceListParams } from "@/lib/use-resource-list";
import type {
  TaxRate,
  TaxRateFormValues,
  TaxGroup,
  TaxGroupFormValues,
  TaxZone,
  TaxZoneFormValues,
  TaxProfile,
  TaxSummaryReport,
  TaxReturnReport,
  TaxReportFilters,
} from "@/features/tax/types";

/** Typed fetchers for the tax engine endpoints. Kept thin — hooks own caching/mutations. */

export type TaxRateFilters = { is_active: string; type: string };
export type TaxGroupFilters = { is_active: string };
export type TaxZoneFilters = { is_active: string; country_code: string };

export const taxApi = {
  rates: {
    list: (params: ResourceListParams<TaxRateFilters>) =>
      api<Paginated<TaxRate>>(`/tax-rates?${buildListQuery(params)}`),
    create: (data: TaxRateFormValues) => api<{ data: TaxRate }>("/tax-rates", { method: "POST", body: data }),
    update: (id: number, data: TaxRateFormValues) =>
      api<{ data: TaxRate }>(`/tax-rates/${id}`, { method: "PUT", body: data }),
    remove: (id: number) => api<void>(`/tax-rates/${id}`, { method: "DELETE" }),
  },

  groups: {
    list: (params: ResourceListParams<TaxGroupFilters>) =>
      api<Paginated<TaxGroup>>(`/tax-groups?${buildListQuery(params)}`),
    create: (data: TaxGroupFormValues) => api<{ data: TaxGroup }>("/tax-groups", { method: "POST", body: data }),
    update: (id: number, data: TaxGroupFormValues) =>
      api<{ data: TaxGroup }>(`/tax-groups/${id}`, { method: "PUT", body: data }),
    remove: (id: number) => api<void>(`/tax-groups/${id}`, { method: "DELETE" }),
    /** Unpaginated, for pickers (product form, zone form) — `per_page=200` matches the API max. */
    listAll: () => api<Paginated<TaxGroup>>("/tax-groups?per_page=200"),
  },

  zones: {
    list: (params: ResourceListParams<TaxZoneFilters>) =>
      api<Paginated<TaxZone>>(`/tax-zones?${buildListQuery(params)}`),
    create: (data: TaxZoneFormValues) => api<{ data: TaxZone }>("/tax-zones", { method: "POST", body: data }),
    update: (id: number, data: TaxZoneFormValues) =>
      api<{ data: TaxZone }>(`/tax-zones/${id}`, { method: "PUT", body: data }),
    remove: (id: number) => api<void>(`/tax-zones/${id}`, { method: "DELETE" }),
  },

  profiles: {
    /** Read-only global catalogue — GENERIC, BD_VAT, IN_GST, EU_VAT, US_SALES_TAX, … */
    list: () => api<Paginated<TaxProfile>>("/tax-profiles"),
    get: (id: number) => api<{ data: TaxProfile }>(`/tax-profiles/${id}`),
  },

  reports: {
    summary: (filters: TaxReportFilters) =>
      api<{ data: TaxSummaryReport }>(`/reports/tax-summary?${reportQuery(filters)}`),
    taxReturn: (filters: TaxReportFilters) =>
      api<{ data: TaxReturnReport }>(`/reports/tax-return?${reportQuery(filters)}`),
  },
};

function reportQuery(filters: TaxReportFilters): string {
  const qs = new URLSearchParams({ from: filters.from, to: filters.to });
  if (filters.branch_id) qs.set("branch_id", String(filters.branch_id));
  return qs.toString();
}
