import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError } from "@/lib/api";
import { queryKeys } from "@/lib/query-keys";
import { useDebouncedValue } from "@/lib/use-debounced-value";
import { toast } from "@/components/ui/toast";
import { useTranslation } from "@/i18n/context";
import { posApi } from "@/features/pos/api";
import type { Customer } from "@/features/pos/types";

/**
 * Customer lookup at the till: type a name or phone, pick a match, or create one inline.
 *
 * Shares `queryKeys.customers` with the customers screen, so creating a customer here shows
 * up there without a second cache entry — it used to live under its own `["pos-customers"]`
 * key and needed its own invalidation on top of the shared one.
 */
export function useCustomerSearch(search: string) {
  const debounced = useDebouncedValue(search);

  const { data } = useQuery({
    queryKey: queryKeys.customers.list({ search: debounced, per_page: 10 }),
    queryFn: () => posApi.customers(debounced),
    enabled: debounced.length > 0,
  });

  return { results: data?.data };
}

/** Live balance, which the search result's cached `loyalty_points` may already be behind. */
export function useCustomerLoyalty(customerId: string | null) {
  const { data } = useQuery({
    queryKey: queryKeys.customerLoyalty(customerId ?? undefined),
    queryFn: () => posApi.loyalty(customerId as string),
    enabled: !!customerId,
  });

  return data?.data?.points;
}

export function useCreateCustomer(onCreated: (customer: Customer) => void) {
  const qc = useQueryClient();
  const t = useTranslation();

  return useMutation({
    mutationFn: (body: { name: string; phone?: string }) => posApi.createCustomer(body),
    onSuccess: ({ data }) => {
      onCreated(data);
      qc.invalidateQueries({ queryKey: queryKeys.customers.all });
      toast(t("pos.customerSearch.created"), "success");
    },
    onError: (e: unknown) => {
      // A 422 here is almost always "phone already taken" — the field error names which one.
      const detail = e instanceof ApiError
        ? Object.values(e.errors ?? {})[0]?.[0] ?? e.message
        : "Could not create customer";
      toast(detail, "error");
    },
  });
}
