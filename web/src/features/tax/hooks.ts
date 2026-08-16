"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError } from "@/lib/api";
import { queryKeys } from "@/lib/query-keys";
import { useResourceList } from "@/lib/use-resource-list";
import { toast } from "@/components/ui/toast";
import { taxApi } from "@/features/tax/api";
import type {
  TaxRateFormValues,
  TaxGroupFormValues,
  TaxZoneFormValues,
  TaxReportFilters,
} from "@/features/tax/types";

function errorMessage(e: unknown, fallback: string): string {
  return e instanceof ApiError ? e.message : fallback;
}

/* ---- tax rates ---- */

export function useTaxRates() {
  return useResourceList({
    queryKey: queryKeys.taxRates.lists(),
    queryFn: taxApi.rates.list,
    defaultSort: "name",
    filterKeys: ["is_active", "type"] as const,
  });
}

export function useTaxRateMutations() {
  const qc = useQueryClient();
  const invalidate = () => qc.invalidateQueries({ queryKey: queryKeys.taxRates.all });

  const create = useMutation({
    mutationFn: (data: TaxRateFormValues) => taxApi.rates.create(data),
    onSuccess: () => { invalidate(); toast("Tax rate created"); },
    onError: (e) => toast(errorMessage(e, "Could not create tax rate"), "error"),
  });

  const update = useMutation({
    mutationFn: ({ id, data }: { id: string; data: TaxRateFormValues }) => taxApi.rates.update(id, data),
    onSuccess: () => { invalidate(); toast("Tax rate updated"); },
    onError: (e) => toast(errorMessage(e, "Could not update tax rate"), "error"),
  });

  const remove = useMutation({
    mutationFn: (id: string) => taxApi.rates.remove(id),
    onSuccess: () => {
      // Also refetch groups: a deleted rate disappears from any group's component list.
      invalidate();
      qc.invalidateQueries({ queryKey: queryKeys.taxGroups.all });
      toast("Tax rate deleted");
    },
    onError: (e) => toast(errorMessage(e, "Could not delete tax rate"), "error"),
  });

  return { create, update, remove };
}

/* ---- tax groups ---- */

export function useTaxGroups() {
  return useResourceList({
    queryKey: queryKeys.taxGroups.lists(),
    queryFn: taxApi.groups.list,
    defaultSort: "name",
    filterKeys: ["is_active"] as const,
  });
}

/** Unpaginated tax group list for pickers (product form, zone form). */
export function useAllTaxGroups() {
  return useQuery({
    queryKey: queryKeys.taxGroups.list({ all: true }),
    queryFn: () => taxApi.groups.listAll(),
    staleTime: 60_000,
  });
}

/** All active tax rates, for the group builder's "add component" picker. */
export function useAllTaxRates() {
  return useQuery({
    queryKey: queryKeys.taxRates.list({ all: true }),
    queryFn: () => taxApi.rates.list({ search: "", sort: "name", page: 1, perPage: 200, filters: {} }),
    staleTime: 60_000,
  });
}

export function useTaxGroupMutations() {
  const qc = useQueryClient();
  const invalidate = () => qc.invalidateQueries({ queryKey: queryKeys.taxGroups.all });

  const create = useMutation({
    mutationFn: (data: TaxGroupFormValues) => taxApi.groups.create(data),
    onSuccess: () => { invalidate(); toast("Tax group created"); },
    onError: (e) => toast(errorMessage(e, "Could not create tax group"), "error"),
  });

  const update = useMutation({
    mutationFn: ({ id, data }: { id: string; data: TaxGroupFormValues }) => taxApi.groups.update(id, data),
    onSuccess: () => { invalidate(); toast("Tax group updated"); },
    onError: (e) => toast(errorMessage(e, "Could not update tax group"), "error"),
  });

  const remove = useMutation({
    mutationFn: (id: string) => taxApi.groups.remove(id),
    onSuccess: () => {
      invalidate();
      qc.invalidateQueries({ queryKey: queryKeys.taxZones.all });
      toast("Tax group deleted");
    },
    onError: (e) => toast(errorMessage(e, "Could not delete tax group — it may still be assigned to products or zones"), "error"),
  });

  return { create, update, remove };
}

/* ---- tax zones ---- */

export function useTaxZones() {
  return useResourceList({
    queryKey: queryKeys.taxZones.lists(),
    queryFn: taxApi.zones.list,
    defaultSort: "priority",
    filterKeys: ["is_active", "country_code"] as const,
  });
}

export function useTaxZoneMutations() {
  const qc = useQueryClient();
  const invalidate = () => qc.invalidateQueries({ queryKey: queryKeys.taxZones.all });

  const create = useMutation({
    mutationFn: (data: TaxZoneFormValues) => taxApi.zones.create(data),
    onSuccess: () => { invalidate(); toast("Tax zone created"); },
    onError: (e) => toast(errorMessage(e, "Could not create tax zone"), "error"),
  });

  const update = useMutation({
    mutationFn: ({ id, data }: { id: string; data: TaxZoneFormValues }) => taxApi.zones.update(id, data),
    onSuccess: () => { invalidate(); toast("Tax zone updated"); },
    onError: (e) => toast(errorMessage(e, "Could not update tax zone"), "error"),
  });

  const remove = useMutation({
    mutationFn: (id: string) => taxApi.zones.remove(id),
    onSuccess: () => { invalidate(); toast("Tax zone deleted"); },
    onError: (e) => toast(errorMessage(e, "Could not delete tax zone"), "error"),
  });

  return { create, update, remove };
}

/* ---- tax profiles (read-only catalogue) ---- */

export function useTaxProfiles() {
  return useQuery({
    queryKey: queryKeys.taxProfiles.lists(),
    queryFn: () => taxApi.profiles.list(),
    staleTime: 5 * 60_000,
  });
}

/* ---- tax reports ---- */

export function useTaxSummaryReport(filters: TaxReportFilters, enabled = true) {
  return useQuery({
    queryKey: queryKeys.taxReports.summary(filters),
    queryFn: () => taxApi.reports.summary(filters),
    enabled: enabled && !!filters.from && !!filters.to,
  });
}

export function useTaxReturnReport(filters: TaxReportFilters, enabled = true) {
  return useQuery({
    queryKey: queryKeys.taxReports.taxReturn(filters),
    queryFn: () => taxApi.reports.taxReturn(filters),
    enabled: enabled && !!filters.from && !!filters.to,
  });
}
