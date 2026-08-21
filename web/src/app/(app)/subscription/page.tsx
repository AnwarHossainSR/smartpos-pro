import { ArrowRight, Check, Crown, CreditCard, HeadphonesIcon, Lock, RefreshCw, ShieldCheck, TrendingUp, Rocket, Briefcase, X, Zap } from "lucide-react";
import { useNavigate } from "react-router";
import { useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { api, ApiError } from "@/lib/api";
import { formatCurrency, formatDate } from "@/lib/utils";
import { useQuery } from "@tanstack/react-query";

interface PlanFeature {
  key: string;
  label: string;
  value: string;
  included: boolean;
}

interface Plan {
  id: string;
  name: string;
  slug: string;
  price: number;
  interval: string;
  limits: Record<string, number | null>;
  features: PlanFeature[];
  is_active: boolean;
}

interface Subscription {
  id: string;
  plan: Plan;
  status: string;
  current_period_ends_at: string | null;
  trial_ends_at: string | null;
}

interface SubscriptionResponse {
  subscription: Subscription | null;
  usage: Record<string, { used: number; limit: number }>;
}

const PLAN_ICONS: Record<string, typeof Rocket> = {
  starter: Rocket,
  professional: TrendingUp,
  business: Briefcase,
};

const PLAN_COLORS: Record<string, { bg: string; text: string; button: string }> = {
  starter: { bg: "bg-emerald-100", text: "text-emerald-600", button: "bg-emerald-500 hover:bg-emerald-600" },
  professional: { bg: "bg-violet-100", text: "text-violet-600", button: "bg-violet-500 hover:bg-violet-600" },
  business: { bg: "bg-orange-100", text: "text-orange-600", button: "bg-orange-500 hover:bg-orange-600" },
};

const PLAN_DESCRIPTIONS: Record<string, string> = {
  starter: "Perfect for new shops getting started.",
  professional: "For growing businesses needing more.",
  business: "Everything for established businesses.",
};

const TRUST_BADGES = [
  { icon: CreditCard, text: "Secure payment" },
  { icon: Zap, text: "Instant activation" },
  { icon: RefreshCw, text: "Upgrade or cancel anytime" },
  { icon: ShieldCheck, text: "Secure & reliable" },
];

const BOTTOM_TRUST = [
  { icon: HeadphonesIcon, title: "24/7 Customer Support", desc: "We're here to help anytime." },
  { icon: Lock, title: "Secure & Reliable", desc: "Your data is always safe." },
  { icon: RefreshCw, title: "Flexible Plans", desc: "Upgrade, downgrade or cancel anytime." },
  { icon: ShieldCheck, title: "Trusted by Businesses", desc: "Join thousands of happy users." },
];

export default function SubscriptionPage() {
  const navigate = useNavigate();
  const [isYearly, setIsYearly] = useState(false);

  const { data: plansData, isLoading: plansLoading, error: plansError } = useQuery<{ data: Plan[] }>({
    queryKey: ["subscription-plans"],
    queryFn: () => api<{ data: Plan[] }>("/subscription/plans"),
  });

  const { data: subData, isLoading: subLoading, error: subError } = useQuery<{ data: SubscriptionResponse }>({
    queryKey: ["subscription"],
    queryFn: () => api<{ data: SubscriptionResponse }>("/subscription"),
    retry: (failureCount, err) => !(err instanceof ApiError && err.status === 403) && failureCount < 3,
  });

  const sub = subData?.data.subscription;
  const allPlans = plansData?.data ?? [];
  const paidPlans = allPlans.filter((p) => p.price > 0);
  const freePlan = allPlans.find((p) => p.price === 0);
  // `sub` is undefined while /subscription is still in flight, so gating on it alone
  // flashes "You're on the Free Plan" at paying customers on every page load.
  const isOnFreePlan = !subLoading && !sub && freePlan;

  const statusColor: Record<string, "success" | "warning" | "danger" | "default"> = {
    active: "success",
    trialing: "warning",
    past_due: "danger",
    canceled: "default",
  };

  if (subError instanceof ApiError && subError.status === 403) {
    return (
      <div className="space-y-5">
        <h1 className="text-xl font-semibold">Subscription</h1>
        <Card>
          <CardContent className="flex flex-col items-center gap-2 py-10 text-center text-[var(--muted)]">
            <Crown className="h-6 w-6" />
            <p>Verify your email address to view billing and subscription details.</p>
          </CardContent>
        </Card>
      </div>
    );
  }

  if (plansError) {
    return (
      <div className="space-y-5">
        <h1 className="text-xl font-semibold">Subscription</h1>
        <Card>
          <CardContent className="py-8 text-center text-[var(--muted)]">
            Failed to load subscription plans. Please try again later.
          </CardContent>
        </Card>
      </div>
    );
  }

  return (
    <div className="space-y-8 pb-4">
      {/* Current plan banner */}
      {sub && (
        <div className="rounded-xl border border-[var(--border)] bg-[var(--surface)] p-4 shadow-sm transition-shadow duration-300 hover:shadow-md">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-3">
              <div className="flex h-10 w-10 items-center justify-center rounded-full bg-[var(--surface-2)]">
                <Crown className="h-5 w-5 text-[var(--muted)]" />
              </div>
              <div>
                <p className="font-medium">Current Plan: {sub.plan.name}</p>
                <p className="text-sm text-[var(--muted)]">
                  {sub.status === "trialing"
                    ? `Trial ends ${formatDate(sub.trial_ends_at)}`
                    : `Active until ${formatDate(sub.current_period_ends_at)}`}
                </p>
              </div>
            </div>
            <Badge tone={statusColor[sub.status] ?? "default"}>{sub.status}</Badge>
          </div>
        </div>
      )}

      {/* Free plan banner */}
      {isOnFreePlan && (
        <div className="rounded-xl border border-[var(--border)] bg-[var(--surface)] p-4 shadow-sm transition-shadow duration-300 hover:shadow-md">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-3">
              <div className="flex h-10 w-10 items-center justify-center rounded-full bg-[var(--surface-2)]">
                <Crown className="h-5 w-5 text-[var(--muted)]" />
              </div>
              <div>
                <p className="font-medium">You&apos;re on the Free Plan</p>
                <p className="text-sm text-[var(--muted)]">
                  Upgrade to a paid plan for higher limits and the full feature set.
                </p>
              </div>
            </div>
            <Button variant="ghost" size="sm" className="text-[var(--primary)]">
              Compare all features <ArrowRight className="ml-1 h-4 w-4" />
            </Button>
          </div>
        </div>
      )}

      {/* Header */}
      <div className="text-center">
        <h1 className="text-3xl font-bold">
          Upgrade{" "}
          <span className="text-[var(--primary)]">Your</span>{" "}
          Plan
        </h1>
        <p className="mt-2 text-[var(--muted)]">
          Unlock more features, higher limits, and the tools you need to grow.
        </p>
      </div>

      {/* Trust badges */}
      <div className="flex flex-wrap items-center justify-center gap-6 text-sm text-[var(--muted)]">
        {TRUST_BADGES.map((badge) => (
          <div key={badge.text} className="flex items-center gap-2">
            <badge.icon className="h-4 w-4" />
            <span>{badge.text}</span>
          </div>
        ))}
      </div>

      {/* Monthly / Yearly toggle */}
      <div className="flex items-center justify-center gap-3">
        <span className={`text-sm ${!isYearly ? "font-medium text-[var(--fg)]" : "text-[var(--muted)]"}`}>
          Billed monthly
        </span>
        <button
          onClick={() => setIsYearly(!isYearly)}
          className={`relative h-6 w-11 rounded-full transition-colors duration-200 ${
            isYearly ? "bg-[var(--primary)]" : "bg-[var(--surface-2)]"
          }`}
        >
          <div
            className={`absolute top-0.5 h-5 w-5 rounded-full bg-white shadow-sm transition-transform duration-200 ${
              isYearly ? "translate-x-5" : "translate-x-0.5"
            }`}
          />
        </button>
        <span className={`text-sm ${isYearly ? "font-medium text-[var(--fg)]" : "text-[var(--muted)]"}`}>
          Billed yearly
        </span>
        {isYearly && (
          <Badge tone="success" className="ml-1 text-xs">
            Save 17%
          </Badge>
        )}
      </div>

      {/* Plan cards */}
      {plansLoading ? (
        <div className="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
          {Array.from({ length: 3 }).map((_, i) => (
            <Skeleton key={i} className="h-[550px] rounded-2xl" />
          ))}
        </div>
      ) : (
        <div className="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
          {paidPlans.map((plan) => {
            const isCurrent = sub?.plan.id === plan.id;
            const isDowngrade = !!sub && plan.price < sub.plan.price;
            const isPopular = plan.slug === "professional";
            const Icon = PLAN_ICONS[plan.slug] ?? Rocket;
            const colors = PLAN_COLORS[plan.slug] ?? PLAN_COLORS.starter;
            const displayPrice = isYearly ? plan.price * 10 : plan.price;

            return (
              <div
                key={plan.id}
                className={`group relative flex flex-col rounded-2xl border bg-[var(--surface)] transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl ${
                  isPopular
                    ? "border-violet-300 shadow-lg shadow-violet-100 dark:shadow-violet-900/20"
                    : "border-[var(--border)] shadow-sm"
                } ${isCurrent ? "ring-2 ring-[var(--primary)]" : ""}`}
              >
                {isPopular && (
                  <div className="absolute -top-3 left-1/2 -translate-x-1/2">
                    <span className="rounded-full bg-violet-500 px-4 py-1 text-xs font-semibold text-white shadow-md">
                      Most Popular
                    </span>
                  </div>
                )}

                <div className="p-6">
                  <div className={`mb-4 inline-flex h-12 w-12 items-center justify-center rounded-xl ${colors.bg} ${colors.text} transition-transform duration-300 group-hover:scale-110`}>
                    <Icon className="h-6 w-6" />
                  </div>

                  <h3 className="text-xl font-bold">{plan.name}</h3>
                  <p className="mt-1 text-sm text-[var(--muted)]">
                    {PLAN_DESCRIPTIONS[plan.slug] ?? ""}
                  </p>

                  <div className="mt-5 flex items-baseline gap-1">
                    <span className="text-3xl font-bold">
                      {formatCurrency(displayPrice)}
                    </span>
                    <span className="text-sm text-[var(--muted)]">/month</span>
                  </div>

                  <Button
                    className={`mt-5 w-full rounded-xl py-2.5 text-sm font-semibold text-white transition-all duration-200 hover:scale-[1.02] hover:shadow-lg ${colors.button}`}
                    disabled={isCurrent || isDowngrade}
                    onClick={() => navigate(`/subscription/checkout?plan=${plan.id}`)}
                  >
                    {isCurrent
                      ? "Current Plan"
                      : isDowngrade
                        ? "Downgrade"
                        : sub
                          ? "Upgrade Now"
                          : "Get Started"}
                    {!isCurrent && !isDowngrade && <ArrowRight className="ml-1.5 h-4 w-4" />}
                  </Button>
                </div>

                <div className="flex-1 space-y-3 border-t border-[var(--border)] p-6">
                  {plan.features.map((feature) => (
                    <div key={feature.key} className="flex items-center gap-2.5 text-sm">
                      {feature.included ? (
                        <Check className="h-4 w-4 shrink-0 text-emerald-500" />
                      ) : (
                        <X className="h-4 w-4 shrink-0 text-red-400" />
                      )}
                      <span className={feature.included ? "" : "text-[var(--muted)]"}>
                        {feature.label}
                        {feature.value ? `: ${feature.value}` : ""}
                      </span>
                    </div>
                  ))}
                </div>
              </div>
            );
          })}
        </div>
      )}

      {/* Bottom trust section */}
      <div className="grid grid-cols-2 gap-4 rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-6 shadow-sm lg:grid-cols-4">
        {BOTTOM_TRUST.map((item) => (
          <div key={item.title} className="flex items-start gap-3">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[var(--surface-2)] text-[var(--muted)]">
              <item.icon className="h-5 w-5" />
            </div>
            <div>
              <p className="text-sm font-medium">{item.title}</p>
              <p className="text-xs text-[var(--muted)]">{item.desc}</p>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
