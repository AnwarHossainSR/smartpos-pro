import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";
import { queryKeys } from "@/lib/query-keys";

export interface MediaCollection {
  key: string;
  label: string;
  max_kb: number;
  mimes: string[];
  accept: string;
  variants: Record<string, { width: number; height: number }>;
}

export function useMediaCollections() {
  return useQuery<{ data: MediaCollection[] }>({
    queryKey: queryKeys.mediaCollections,
    queryFn: () => api<{ data: MediaCollection[] }>("/media/collections"),
    staleTime: Infinity,
    gcTime: Infinity,
  });
}
