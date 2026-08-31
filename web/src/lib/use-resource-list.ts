import { keepPreviousData, type QueryKey, useQuery } from "@tanstack/react-query";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { useLocation, useNavigate, useSearchParams } from "react-router";
import type { Paginated } from "@/lib/api";
import { useDebouncedValue } from "@/lib/use-debounced-value";

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
  /** Milliseconds to wait after the last keystroke before the search reaches the URL/API. */
  searchDelay?: number;
}

export function buildListQuery<F extends Record<string, string>>(
  params: ResourceListParams<F>,
): string {
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

/**
 * The one list-screen hook: URL-backed search / sort / page / filters, a debounced search
 * box, `keepPreviousData` so paging doesn't blank the table, and a query key derived from
 * the same params the fetcher receives.
 *
 * Holding the state in the URL (rather than `useState` per page, which is what every list
 * screen here used to do) is what makes a filtered list shareable, bookmarkable and
 * survivable across a back-navigation — and it means the browser Back button steps through
 * pages of a table the way users expect instead of leaving the screen.
 *
 * `search` is deliberately two values: `searchInput` updates on every keystroke so the
 * text box stays responsive, while the debounced copy is what reaches the URL and the API.
 * Binding an input straight to a URL param fires a request per character.
 */
export function useResourceList<T, F extends Record<string, string> = Record<string, string>>({
  queryKey,
  queryFn,
  defaultSort,
  perPage = 20,
  filterKeys = [],
  staleTime,
  enabled = true,
  searchDelay = 300,
}: UseResourceListOptions<T, F>) {
  const navigate = useNavigate();
  const { pathname } = useLocation();
  const [searchParams] = useSearchParams();

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

  // Typed text is local and immediate; the debounced copy is pushed to the URL.
  const [searchInput, setSearchInput] = useState(search);
  const debouncedSearch = useDebouncedValue(searchInput, searchDelay);

  // A URL change from elsewhere (Back, a "clear filters" link, a fresh mount with a
  // deep-linked ?search=) must win over the local box; guard on the value actually
  // differing so this doesn't fight the debounce on every render.
  const lastPushed = useRef(search);
  useEffect(() => {
    if (search !== lastPushed.current) {
      lastPushed.current = search;
      setSearchInput(search);
    }
  }, [search]);

  useEffect(() => {
    if (debouncedSearch === lastPushed.current) return;
    lastPushed.current = debouncedSearch;
    setParams({ search: debouncedSearch, page: null });
  }, [debouncedSearch, setParams]);

  const setSort = useCallback(
    (value: string | undefined) => setParams({ sort: value ?? null, page: null }),
    [setParams],
  );
  const setPage = useCallback(
    (value: number) => setParams({ page: value > 1 ? value : null }),
    [setParams],
  );
  const setFilter = useCallback(
    (key: keyof F & string, value: string | undefined) =>
      setParams({ [key]: value ?? null, page: null }),
    [setParams],
  );
  const resetFilters = useCallback(() => {
    const patch: Record<string, null> = { search: null, page: null };
    for (const key of filterKeys) patch[key] = null;
    lastPushed.current = "";
    setSearchInput("");
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
  const rows = query.data?.data ?? [];

  return {
    /** Bind this to the search input's `value` — it updates on every keystroke. */
    searchInput,
    /** What actually reached the URL and the API (debounced). */
    search,
    sort,
    page,
    perPage,
    filters,
    data: rows,
    meta: query.data?.meta,
    lastPage,
    total,
    isLoading: query.isLoading,
    isFetching: query.isFetching,
    isError: query.isError,
    error: query.error,
    /** True once loading finished and the (possibly filtered) result set came back empty. */
    isEmpty: !query.isLoading && rows.length === 0,
    /** True when the empty result is the effect of a search/filter rather than no data at all. */
    isFiltered: !!search || Object.keys(filters).length > 0,
    refetch: query.refetch,
    setSearch: setSearchInput,
    setSort,
    setPage,
    setFilter,
    resetFilters,
  };
}
