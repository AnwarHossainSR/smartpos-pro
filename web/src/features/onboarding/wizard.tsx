"use client";

import { useState, useCallback } from "react";
import { useRouter } from "next/navigation";
import {
  Building2,
  Package,
  Receipt,
  ShoppingCart,
  Check,
  ChevronRight,
  X,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Dialog } from "@/components/ui/dialog";
import { useTranslation } from "@/i18n/provider";
import { type PermissionSlug } from "@/lib/permissions";
import { useAuth } from "@/stores/auth";
import { useOnboardingState, useRecordOnboardingStep, useCompleteOnboarding } from "./hooks";

const STEPS = ["business_profile", "first_product", "tax_setup", "register_sale"] as const;
type StepKey = (typeof STEPS)[number];

const STEP_ICONS: Record<StepKey, React.ReactNode> = {
  business_profile: <Building2 className="h-5 w-5" />,
  first_product: <Package className="h-5 w-5" />,
  tax_setup: <Receipt className="h-5 w-5" />,
  register_sale: <ShoppingCart className="h-5 w-5" />,
};

const STEP_PERMISSIONS: Record<StepKey, PermissionSlug | null> = {
  business_profile: null,
  first_product: "products.create",
  tax_setup: "settings.update",
  register_sale: "pos.create",
};

function useVisibleSteps(): StepKey[] {
  const { can } = useAuth();
  return STEPS.filter((step) => {
    const perm = STEP_PERMISSIONS[step];
    return !perm || can(perm);
  });
}

function ProgressRail({ current, total }: { current: number; total: number }) {
  return (
    <div className="flex items-center gap-1.5">
      {Array.from({ length: total }, (_, i) => (
        <div
          key={i}
          className={`h-1.5 flex-1 rounded-full transition-colors ${
            i < current
              ? "bg-[var(--primary)]"
              : i === current
                ? "bg-[var(--primary)] opacity-60"
                : "bg-[var(--border)]"
          }`}
        />
      ))}
    </div>
  );
}

interface StepContentProps {
  step: StepKey;
  onComplete: () => void;
}

function StepContent({ step, onComplete }: StepContentProps) {
  const t = useTranslation();
  const router = useRouter();

  switch (step) {
    case "business_profile":
      return (
        <div className="space-y-4">
          <p className="text-sm text-[var(--muted)]">{t("onboarding.steps.business_profile.description")}</p>
          <Button onClick={onComplete} className="w-full">
            <Check className="h-4 w-4" />
            {t("onboarding.steps.business_profile.action")}
          </Button>
          <Button
            variant="outline"
            onClick={() => router.push("/settings")}
            className="w-full"
          >
            {t("onboarding.steps.business_profile.configure")}
          </Button>
        </div>
      );

    case "first_product":
      return (
        <div className="space-y-4">
          <p className="text-sm text-[var(--muted)]">{t("onboarding.steps.first_product.description")}</p>
          <Button onClick={onComplete} className="w-full">
            <Check className="h-4 w-4" />
            {t("onboarding.steps.first_product.action")}
          </Button>
          <Button
            variant="outline"
            onClick={() => router.push("/products")}
            className="w-full"
          >
            {t("onboarding.steps.first_product.configure")}
          </Button>
        </div>
      );

    case "tax_setup":
      return (
        <div className="space-y-4">
          <p className="text-sm text-[var(--muted)]">{t("onboarding.steps.tax_setup.description")}</p>
          <Button onClick={onComplete} className="w-full">
            <Check className="h-4 w-4" />
            {t("onboarding.steps.tax_setup.action")}
          </Button>
          <Button
            variant="outline"
            onClick={() => router.push("/settings")}
            className="w-full"
          >
            {t("onboarding.steps.tax_setup.configure")}
          </Button>
        </div>
      );

    case "register_sale":
      return (
        <div className="space-y-4">
          <p className="text-sm text-[var(--muted)]">{t("onboarding.steps.register_sale.description")}</p>
          <Button onClick={onComplete} className="w-full">
            <Check className="h-4 w-4" />
            {t("onboarding.steps.register_sale.action")}
          </Button>
          <Button
            variant="outline"
            onClick={() => router.push("/pos")}
            className="w-full"
          >
            {t("onboarding.steps.register_sale.configure")}
          </Button>
        </div>
      );
  }
}

export function OnboardingWizard() {
  const t = useTranslation();
  const { data, isLoading } = useOnboardingState();
  const recordStep = useRecordOnboardingStep();
  const completeOnboarding = useCompleteOnboarding();
  const visibleSteps = useVisibleSteps();

  const [skipConfirmOpen, setSkipConfirmOpen] = useState(false);

  const onboardingState = data?.data?.onboarding_state;
  const onboardingCompleted = data?.data?.onboarding_completed;

  const isVisible =
    !isLoading &&
    !onboardingCompleted &&
    onboardingState &&
    !onboardingState.dismissed &&
    (onboardingState.current_step ?? 0) < visibleSteps.length;

  const currentStepIndex = onboardingState?.current_step ?? 0;
  const currentStep = visibleSteps[currentStepIndex];

  const handleStepComplete = useCallback(() => {
    if (!currentStep) return;
    recordStep.mutate(
      { step: currentStep, completed: true },
      {
        onSuccess: () => {
          if (currentStepIndex >= visibleSteps.length - 1) {
            completeOnboarding.mutate();
          }
        },
      },
    );
  }, [currentStep, currentStepIndex, visibleSteps.length, recordStep, completeOnboarding]);

  const handleSkipAll = useCallback(() => {
    completeOnboarding.mutate();
    setSkipConfirmOpen(false);
  }, [completeOnboarding]);

  if (!isVisible) return null;

  return (
    <>
      <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
        <div className="relative w-full max-w-lg rounded-[var(--radius)] bg-[var(--surface)] p-6 shadow-xl">
          <button
            onClick={() => setSkipConfirmOpen(true)}
            className="absolute right-3 top-3 rounded-[var(--radius)] p-1 text-[var(--muted)] hover:bg-[var(--surface-2)] hover:text-[var(--fg)]"
          >
            <X className="h-4 w-4" />
          </button>

          <div className="mb-6">
            <h2 className="text-lg font-semibold">{t("onboarding.title")}</h2>
            <p className="mt-1 text-sm text-[var(--muted)]">{t("onboarding.subtitle")}</p>
          </div>

          <ProgressRail current={currentStepIndex} total={visibleSteps.length} />

          <div className="mt-2 flex justify-between text-xs text-[var(--muted)]">
            <span>
              {t("onboarding.stepOf", {
                current: currentStepIndex + 1,
                total: visibleSteps.length,
              })}
            </span>
            <span>{t(`onboarding.steps.${currentStep}.title`)}</span>
          </div>

          <div className="mt-6 flex items-center gap-3">
            {visibleSteps.map((step, i) => {
              const done = i < currentStepIndex;
              const active = i === currentStepIndex;
              return (
                <div key={step} className="flex items-center gap-2">
                  <div
                    className={`flex h-8 w-8 items-center justify-center rounded-full text-sm font-medium transition-colors ${
                      done
                        ? "bg-[var(--primary)] text-[var(--primary-fg)]"
                        : active
                          ? "border-2 border-[var(--primary)] text-[var(--primary)]"
                          : "border border-[var(--border)] text-[var(--muted)]"
                    }`}
                  >
                    {done ? <Check className="h-4 w-4" /> : STEP_ICONS[step]}
                  </div>
                  {i < visibleSteps.length - 1 && (
                    <div
                      className={`h-px w-4 ${
                        done ? "bg-[var(--primary)]" : "bg-[var(--border)]"
                      }`}
                    />
                  )}
                </div>
              );
            })}
          </div>

          <div className="mt-6">
            <StepContent step={currentStep} onComplete={handleStepComplete} />
          </div>

          <div className="mt-4 flex justify-between">
            <Button
              variant="ghost"
              size="sm"
              onClick={() => setSkipConfirmOpen(true)}
            >
              {t("onboarding.skipSetup")}
            </Button>
            <Button
              variant="ghost"
              size="sm"
              onClick={handleStepComplete}
              disabled={recordStep.isPending}
            >
              {t("onboarding.skipStep")}
              <ChevronRight className="h-4 w-4" />
            </Button>
          </div>
        </div>
      </div>

      <Dialog
        open={skipConfirmOpen}
        onClose={() => setSkipConfirmOpen(false)}
        title={t("onboarding.skipConfirmTitle")}
        description={t("onboarding.skipConfirmBody")}
        confirmLabel={t("onboarding.skipConfirmYes")}
        confirmVariant="danger"
        onConfirm={handleSkipAll}
        loading={completeOnboarding.isPending}
      />
    </>
  );
}
