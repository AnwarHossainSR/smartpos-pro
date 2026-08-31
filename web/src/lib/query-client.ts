import { MutationCache, QueryCache, QueryClient } from "@tanstack/react-query";
import { toast } from "@/components/ui/toast";
import { ApiError, errorMessage } from "@/lib/api";

/**
 * Retrying a 4xx is pointless — the request was rejected on its merits (validation, a
 * missing record, a permission the user does not hold), and it will be rejected the same
 * way next time. 401 is already handled in `api()` by logging out, and 429 has its own
 * toast, so neither should be hammered either. Only network blips and 5xx get a retry.
 */
function retry(failureCount: number, error: unknown): boolean {
  if (error instanceof ApiError && error.status >= 400 && error.status < 500) return false;
  return failureCount < 2;
}

export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      // Reference data and list pages are re-visited constantly during a shift; 30s of
      // freshness makes tabbing between screens instant instead of a refetch per mount.
      staleTime: 30_000,
      gcTime: 5 * 60_000,
      retry,
      refetchOnWindowFocus: false,
    },
    mutations: {
      retry: false,
    },
  },

  /**
   * One place that turns a failed mutation into a toast. Every mutation used to repeat
   * `onError: (e) => toast(e instanceof ApiError ? e.message : "Error", "error")`; now a
   * mutation only writes `onError` when it wants a *specific* message or extra cleanup,
   * and sets `meta.silent` when it handles the failure inline (inline form errors,
   * optimistic rollback) and must not double-report it.
   */
  mutationCache: new MutationCache({
    onError: (error, _vars, _ctx, mutation) => {
      if (mutation.meta?.silent) return;
      if (mutation.options.onError) return;
      toast(errorMessage(error), "error");
    },
  }),

  /**
   * Background refetch failures are not worth a toast — the screen already has data and
   * the query's own error state covers the first load. Surfacing every stale-refetch
   * blip would spam the operator mid-sale.
   */
  queryCache: new QueryCache({
    onError: (error, query) => {
      if (query.meta?.silent) return;
      if (query.state.data === undefined) return;
      console.error(`[query] ${JSON.stringify(query.queryKey)}`, error);
    },
  }),
});

declare module "@tanstack/react-query" {
  interface Register {
    queryMeta: { silent?: boolean };
    mutationMeta: { silent?: boolean };
  }
}
