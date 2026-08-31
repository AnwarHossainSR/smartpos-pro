import { useQuery } from "@tanstack/react-query";
import { api, type Paginated } from "@/lib/api";
import { queryKeys } from "@/lib/query-keys";
import type { Branch, Brand, Category, Plan, Role, Unit, Warehouse } from "@/types/domain";

/**
 * Shared read-only lookups — the lists that fill selects, comboboxes and filter bars.
 *
 * These were previously re-declared per page, each with its own ad-hoc key
 * (`warehouses-all`, `branches-options`, `categories-all`, POS's own `categories-all`…),
 * so opening Products then Purchases then Stocktakes fetched `/warehouses` three times
 * and cached it under three keys. Routing every screen through these hooks means one key,
 * one in-flight request, one cache entry — and a mutation elsewhere invalidates the whole
 * resource (`queryKeys.warehouses.all`) and refreshes every consumer at once.
 *
 * They are reference data that changes rarely, so they carry a long `staleTime`; that is
 * what makes navigating between screens free rather than a refetch per mount.
 */

/** Reference data barely moves within a session — don't refetch it on every mount. */
const LOOKUP_STALE_TIME = 5 * 60_000;

/** The API caps `per_page` at 200. */
const LOOKUP_PER_PAGE = 200;

interface LookupOptions {
  /** Skip the fetch (e.g. the user lacks the permission, or a parent select is empty). */
  enabled?: boolean;
}

function lookup<T>(key: readonly unknown[], path: string, { enabled = true }: LookupOptions = {}) {
  return {
    queryKey: key,
    queryFn: () => api<Paginated<T>>(path),
    staleTime: LOOKUP_STALE_TIME,
    enabled,
  };
}

export function useWarehouses(options?: LookupOptions) {
  const query = useQuery(lookup<Warehouse>(queryKeys.warehouses.options(), "/warehouses", options));
  return { ...query, warehouses: query.data?.data ?? [] };
}

export function useBranches(options?: LookupOptions) {
  const query = useQuery(
    lookup<Branch>(queryKeys.branches.options(), `/branches?per_page=${LOOKUP_PER_PAGE}`, options),
  );
  return { ...query, branches: query.data?.data ?? [] };
}

export function useCategories(options?: LookupOptions) {
  const query = useQuery(
    lookup<Category>(
      queryKeys.categories.options(),
      `/categories?per_page=${LOOKUP_PER_PAGE}`,
      options,
    ),
  );
  return { ...query, categories: query.data?.data ?? [] };
}

export function useBrands(options?: LookupOptions) {
  const query = useQuery(
    lookup<Brand>(queryKeys.brands.options(), `/brands?per_page=${LOOKUP_PER_PAGE}`, options),
  );
  return { ...query, brands: query.data?.data ?? [] };
}

export function useUnits(options?: LookupOptions) {
  const query = useQuery(
    lookup<Unit>(queryKeys.units.options(), `/units?per_page=${LOOKUP_PER_PAGE}`, options),
  );
  return { ...query, units: query.data?.data ?? [] };
}

export function useRoles(options?: LookupOptions) {
  const query = useQuery(lookup<Role>(queryKeys.roles.options(), "/roles?per_page=50", options));
  return { ...query, roles: query.data?.data ?? [] };
}

export function usePlans(options?: LookupOptions) {
  const query = useQuery(
    lookup<Plan>(queryKeys.subscription.plans(), "/subscription/plans", options),
  );
  return { ...query, plans: query.data?.data ?? [] };
}
