import { useCallback, useMemo } from "react";
import { useNavigate, useLocation, useSearchParams } from "react-router";
import { useQuery, keepPreviousData, type QueryKey } from "@tanstack/react-query";
import type { Paginated } from "@/lib/api";

export interface ResourceListParams<F extends Record<string, string>> {
  search: string;
  sort: string | undefined;
  page: number;
  perPage: number;
  filters: Partial<F>;
}

interface UseResourceListOptions<T, F extends Record<string, string>> {
  queryKey: QueryKey;
  queryFn: (params: ResourceListParams<F>) => Promise<Paginated<T>>;
  defaultSort?: string;
  perPage?: number;
  filterKeys?: readonly (keyof F & string)[];
  staleTime?: number;
  enabled?: boolean;
}

export function buildListQuery<F extends Record<string, string>>(params: ResourceListParams<F>): string {
  const qs = new URLSearchParams();
  if (params.search) qs.set("search", params.search);
  if (params.sort) qs.set("sort", params.sort);
  if (params.page > 1) qs.set("page", String(params.page));
  qs.set("per_page", String(params.perPage));
  for (const [key, value] of Object.entries(params.filters)) {
    if (value !== undefined && value !== "") qs.set(key, String(value));
  }
  return qs.toString();
}

export function useResourceList<T, F extends Record<string, string> = Record<string, string>>({
  queryKey,
  queryFn,
  defaultSort,
  perPage = 20,
  filterKeys = [],
  staleTime,
  enabled = true,
}: UseResourceListOptions<T, F>) {
  const navigate = useNavigate();
  const { pathname } = useLocation();
  const [searchParams, setSearchParams] = useSearchParams();

  const search = searchParams.get("search") ?? "";
  const sort = searchParams.get("sort") ?? defaultSort;
  const page = Math.max(1, Number(searchParams.get("page") ?? "1") || 1);

  const filterKeysJoined = filterKeys.join(",");
  const filters = useMemo(() => {
    const f: Partial<F> = {};
    for (const key of filterKeys) {
      const value = searchParams.get(key);
      if (value !== null && value !== "") f[key] = value as F[typeof key];
    }
    return f;
    // eslint-disable-next-line react-hooks/exhaustive-deps -- filterKeysJoined is a stable proxy for filterKeys' contents
  }, [searchParams, filterKeysJoined]);

  const setParams = useCallback(
    (patch: Record<string, string | number | null | undefined>) => {
      const params = new URLSearchParams(searchParams.toString());
      for (const [key, value] of Object.entries(patch)) {
        if (value === null || value === undefined || value === "") params.delete(key);
        else params.set(key, String(value));
      }
      const qs = params.toString();
      navigate(qs ? `${pathname}?${qs}` : pathname, { replace: true });
    },
    [navigate, pathname, searchParams],
  );

  const setSearch = useCallback((value: string) => setParams({ search: value, page: null }), [setParams]);
  const setSort = useCallback((value: string | undefined) => setParams({ sort: value ?? null, page: null }), [setParams]);
  const setPage = useCallback((value: number) => setParams({ page: value > 1 ? value : null }), [setParams]);
  const setFilter = useCallback(
    (key: keyof F & string, value: string | undefined) => setParams({ [key]: value ?? null, page: null }),
    [setParams],
  );
  const resetFilters = useCallback(() => {
    const patch: Record<string, null> = { search: null, page: null };
    for (const key of filterKeys) patch[key] = null;
    setParams(patch);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [setParams, filterKeysJoined]);

  const params: ResourceListParams<F> = useMemo(
    () => ({ search, sort, page, perPage, filters }),
    [search, sort, page, perPage, filters],
  );

  const query = useQuery({
    queryKey: [...queryKey, params],
    queryFn: () => queryFn(params),
    placeholderData: keepPreviousData,
    staleTime,
    enabled,
  });

  const lastPage = query.data?.meta?.last_page ?? 1;
  const total = query.data?.meta?.total ?? query.data?.data?.length ?? 0;

  return {
    search,
    sort,
    page,
    perPage,
    filters,
    data: query.data?.data ?? [],
    meta: query.data?.meta,
    lastPage,
    total,
    isLoading: query.isLoading,
    isFetching: query.isFetching,
    isError: query.isError,
    error: query.error,
    refetch: query.refetch,
    setSearch,
    setSort,
    setPage,
    setFilter,
    resetFilters,
  };
}
