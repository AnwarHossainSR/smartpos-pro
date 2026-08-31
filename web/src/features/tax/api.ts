import type {
  TaxGroup,
  TaxGroupFormValues,
  TaxProfile,
  TaxRate,
  TaxRateFormValues,
  TaxReportFilters,
  TaxReturnReport,
  TaxSummaryReport,
  TaxZone,
  TaxZoneFormValues,
} from "@/features/tax/types";
import { api, type Paginated } from "@/lib/api";
import { buildListQuery, type ResourceListParams } from "@/lib/use-resource-list";

/** Typed fetchers for the tax engine endpoints. Kept thin — hooks own caching/mutations. */

export type TaxRateFilters = { is_active: string; type: string };
export type TaxGroupFilters = { is_active: string };
export type TaxZoneFilters = { is_active: string; country_code: string };

export const taxApi = {
  rates: {
    list: (params: ResourceListParams<TaxRateFilters>) =>
      api<Paginated<TaxRate>>(`/tax-rates?${buildListQuery(params)}`),
    create: (data: TaxRateFormValues) =>
      api<{ data: TaxRate }>("/tax-rates", { method: "POST", body: data }),
    update: (id: string, data: TaxRateFormValues) =>
      api<{ data: TaxRate }>(`/tax-rates/${id}`, { method: "PUT", body: data }),
    remove: (id: string) => api<void>(`/tax-rates/${id}`, { method: "DELETE" }),
  },

  groups: {
    list: (params: ResourceListParams<TaxGroupFilters>) =>
      api<Paginated<TaxGroup>>(`/tax-groups?${buildListQuery(params)}`),
    create: (data: TaxGroupFormValues) =>
      api<{ data: TaxGroup }>("/tax-groups", { method: "POST", body: data }),
    update: (id: string, data: TaxGroupFormValues) =>
      api<{ data: TaxGroup }>(`/tax-groups/${id}`, { method: "PUT", body: data }),
    remove: (id: string) => api<void>(`/tax-groups/${id}`, { method: "DELETE" }),
    /** Unpaginated, for pickers (product form, zone form) — `per_page=200` matches the API max. */
    listAll: () => api<Paginated<TaxGroup>>("/tax-groups?per_page=200"),
  },

  zones: {
    list: (params: ResourceListParams<TaxZoneFilters>) =>
      api<Paginated<TaxZone>>(`/tax-zones?${buildListQuery(params)}`),
    create: (data: TaxZoneFormValues) =>
      api<{ data: TaxZone }>("/tax-zones", { method: "POST", body: data }),
    update: (id: string, data: TaxZoneFormValues) =>
      api<{ data: TaxZone }>(`/tax-zones/${id}`, { method: "PUT", body: data }),
    remove: (id: string) => api<void>(`/tax-zones/${id}`, { method: "DELETE" }),
  },

  profiles: {
    /** Read-only global catalogue — GENERIC, BD_VAT, IN_GST, EU_VAT, US_SALES_TAX, … */
    list: () => api<Paginated<TaxProfile>>("/tax-profiles"),
    get: (id: string) => api<{ data: TaxProfile }>(`/tax-profiles/${id}`),
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
