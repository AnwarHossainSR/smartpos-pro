import { api } from "@/lib/api";

export interface OnboardingState {
  business_profile?: boolean;
  first_product?: boolean;
  tax_setup?: boolean;
  register_sale?: boolean;
  current_step: number;
  dismissed?: boolean;
}

export interface BusinessSettingsResponse {
  data: {
    onboarding_state: OnboardingState | null;
    onboarding_completed: boolean;
    name: string;
    currency: string;
    tax_profile_id: string | null;
  };
}

export interface OnboardingStepResponse {
  data: {
    onboarding_state: OnboardingState;
  };
}

export const onboardingApi = {
  getBusinessSettings: () => api<BusinessSettingsResponse>("/settings/business"),

  recordStep: (step: string, completed: boolean) =>
    api<OnboardingStepResponse>("/settings/onboarding/step", {
      method: "POST",
      body: { step, completed },
    }),

  complete: () =>
    api<{ data: { onboarding_completed: boolean } }>("/settings/onboarding/complete", {
      method: "POST",
    }),
};
