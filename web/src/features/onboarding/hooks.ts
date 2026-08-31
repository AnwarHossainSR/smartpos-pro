import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { onboardingApi } from "@/features/onboarding/api";
import { queryKeys } from "@/lib/query-keys";

/**
 * Deliberately the same key as Settings -> Business info and `useSyncBusinessSettings()`:
 * all three read `/settings/business`, so sharing the key means one request feeds the
 * wizard, the settings form and the currency formatter.
 */
export function useOnboardingState() {
  return useQuery({
    queryKey: queryKeys.businessSettings,
    queryFn: onboardingApi.getBusinessSettings,
  });
}

export function useRecordOnboardingStep() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ step, completed }: { step: string; completed: boolean }) =>
      onboardingApi.recordStep(step, completed),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.businessSettings });
    },
  });
}

export function useCompleteOnboarding() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: onboardingApi.complete,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.businessSettings });
    },
  });
}
