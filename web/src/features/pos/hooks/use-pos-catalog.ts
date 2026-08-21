import { useCallback, useEffect, useMemo, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { queryKeys } from "@/lib/query-keys";
import { useCategories } from "@/lib/lookups";
import { useDebouncedValue } from "@/lib/use-debounced-value";
import type { Category } from "@/types/domain";
import { FAVORITES_CATEGORY_ID, posApi } from "@/features/pos/api";
import type { Product } from "@/features/pos/types";

export type PosViewMode = "grid" | "list";

const VIEW_MODE_STORAGE_KEY = "pos-view-mode";
const FAVORITES_STORAGE_KEY = "pos-favorites";

/** The till re-prices constantly; 15s keeps stock counts honest without hammering the API. */
const PRODUCTS_STALE_TIME = 15_000;

export interface PosCatalog {
  search: string;
  setSearch: (value: string) => void;
  categories: Category[];
  activeCategoryId: string | null;
  setActiveCategoryId: (id: string | null) => void;
  products: Product[];
  isLoading: boolean;
  viewMode: PosViewMode;
  setViewMode: (mode: PosViewMode) => void;
  favorites: Set<string>;
  toggleFavorite: (productId: string) => void;
}

function readFavorites(): Set<string> {
  try {
    return new Set<string>(JSON.parse(localStorage.getItem(FAVORITES_STORAGE_KEY) ?? "[]"));
  } catch {
    return new Set();
  }
}

/**
 * What the left half of the till shows: the searchable product list, its category tabs and
 * the two per-terminal preferences (grid/list, starred products) that live in localStorage
 * because they belong to the physical till rather than the user account.
 *
 * The product list shares `queryKeys.products` with the catalogue screen, so a checkout that
 * invalidates `products` refreshes both — before this it was cached separately under
 * `["pos-products"]` and every sale had to invalidate the same endpoint twice.
 */
export function usePosCatalog(): PosCatalog {
  const [search, setSearch] = useState("");
  const [activeCategoryId, setActiveCategoryId] = useState<string | null>(null);
  const debouncedSearch = useDebouncedValue(search);

  const [viewMode, setViewMode] = useState<PosViewMode>(
    () => (localStorage.getItem(VIEW_MODE_STORAGE_KEY) as PosViewMode) ?? "grid",
  );
  const [favorites, setFavorites] = useState<Set<string>>(readFavorites);

  useEffect(() => { localStorage.setItem(VIEW_MODE_STORAGE_KEY, viewMode); }, [viewMode]);
  useEffect(() => { localStorage.setItem(FAVORITES_STORAGE_KEY, JSON.stringify([...favorites])); }, [favorites]);

  const toggleFavorite = useCallback((productId: string) => {
    setFavorites((prev) => {
      const next = new Set(prev);
      if (next.has(productId)) next.delete(productId);
      else next.add(productId);
      return next;
    });
  }, []);

  const { categories } = useCategories();

  // Favorites filter locally over the unfiltered list, so it must not reach the request
  // — or the cache key, which would otherwise store the same response twice.
  const categoryId = activeCategoryId === FAVORITES_CATEGORY_ID ? null : activeCategoryId;

  const { data, isLoading } = useQuery({
    queryKey: queryKeys.products.list({
      search: debouncedSearch,
      category_id: categoryId,
      per_page: 50,
      is_active: true,
    }),
    queryFn: () => posApi.products({ search: debouncedSearch, categoryId }),
    staleTime: PRODUCTS_STALE_TIME,
  });

  const products = useMemo(() => {
    const list = data?.data ?? [];
    if (activeCategoryId === FAVORITES_CATEGORY_ID) return list.filter((p) => favorites.has(p.id));
    return list;
  }, [data, activeCategoryId, favorites]);

  return {
    search,
    setSearch,
    categories,
    activeCategoryId,
    setActiveCategoryId,
    products,
    isLoading,
    viewMode,
    setViewMode,
    favorites,
    toggleFavorite,
  };
}
