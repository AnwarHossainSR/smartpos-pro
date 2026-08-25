import { useEffect, type RefObject } from "react";
import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";
import { queryKeys } from "@/lib/query-keys";
import { useBusinessStore, type MediaSlot } from "@/stores/business";

/** Calls `onOutside` on any pointerdown outside `ref`'s element, only while `active` is true. */
export function useClickOutside(ref: RefObject<HTMLElement | null>, onOutside: () => void, active: boolean) {
  useEffect(() => {
    if (!active) return;
    function onClick(e: MouseEvent) {
      if (ref.current && !ref.current.contains(e.target as Node)) onOutside();
    }
    document.addEventListener("mousedown", onClick);
    return () => document.removeEventListener("mousedown", onClick);
  }, [ref, onOutside, active]);
}

/**
 * Tenant currency drives formatCurrency() everywhere; fetched once at app-shell level and
 * mirrored into a store so the plain formatCurrency() helper can read it without a hook.
 *
 * `queryKeys.businessSettings` is deliberately the *same* entry Settings → Business info
 * reads and invalidates on save — one request serves both, and saving a new currency
 * refreshes this automatically. Sharing it through the factory rather than by two call
 * sites happening to type the same string is what keeps that true.
 *
 * Also syncs the business logo URL and name into the store for use in the sidebar.
 */
export function useSyncBusinessCurrency(enabled: boolean) {
  const { data } = useQuery({
    queryKey: queryKeys.businessSettings,
    queryFn: () =>
      api<{
        data: {
          currency: string;
          name: string;
          media: { logo: MediaSlot };
        };
      }>("/settings/business"),
    enabled,
    // A business changes its currency roughly never; the invalidation on save is what
    // refreshes this, not polling for it on every remount.
    staleTime: 30 * 60_000,
  });
  const setCurrency = useBusinessStore((s) => s.setCurrency);
  const setLogoUrl = useBusinessStore((s) => s.setLogoUrl);
  const setBusinessName = useBusinessStore((s) => s.setBusinessName);
  useEffect(() => {
    if (!data?.data) return;
    if (data.data.currency) setCurrency(data.data.currency);
    if (data.data.name) setBusinessName(data.data.name);
    setLogoUrl(data.data.media?.logo?.urls?.md ?? data.data.media?.logo?.url ?? null);
  }, [data, setCurrency, setLogoUrl, setBusinessName]);
}
