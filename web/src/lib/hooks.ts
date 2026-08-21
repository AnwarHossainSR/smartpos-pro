import { useEffect, type RefObject } from "react";
import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";
import { useBusinessStore } from "@/stores/business";

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

/** Tenant currency drives formatCurrency() everywhere; fetched once here (same query key as
 *  Settings -> Business info, so React Query shares one cached request) and mirrored into a
 *  store so the plain formatCurrency() helper can read it without a hook. */
export function useSyncBusinessCurrency(enabled: boolean) {
  const { data } = useQuery({
    queryKey: ["business-settings"],
    queryFn: () => api<{ data: { currency: string } }>("/settings/business"),
    enabled,
  });
  const setCurrency = useBusinessStore((s) => s.setCurrency);
  useEffect(() => {
    if (data?.data?.currency) setCurrency(data.data.currency);
  }, [data, setCurrency]);
}
