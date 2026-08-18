"use client";

import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { onboardingApi } from "./api";

export function useOnboardingState() {
  return useQuery({
    queryKey: ["business-settings"],
    queryFn: onboardingApi.getBusinessSettings,
  });
}

export function useRecordOnboardingStep() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ step, completed }: { step: string; completed: boolean }) =>
      onboardingApi.recordStep(step, completed),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["business-settings"] });
    },
  });
}

export function useCompleteOnboarding() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: onboardingApi.complete,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["business-settings"] });
    },
  });
}
