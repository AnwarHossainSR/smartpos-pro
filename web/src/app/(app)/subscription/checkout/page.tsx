import { useMutation, useQueryClient } from "@tanstack/react-query";
import { ArrowLeft, Check, Loader2, Shield, X } from "lucide-react";
import { useNavigate, useSearchParams } from "react-router";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { toast } from "@/components/ui/toast";
import { api } from "@/lib/api";
import { usePlans } from "@/lib/lookups";
import { queryKeys } from "@/lib/query-keys";
import { formatCurrency } from "@/lib/utils";

function CheckoutContent() {
  const navigate = useNavigate();
  const qc = useQueryClient();
  const [searchParams] = useSearchParams();
  const planId = searchParams.get("plan");

  // Arriving from the plans grid, this is already cached — the page renders without a fetch.
  const { plans, isLoading: plansLoading } = usePlans();

  const plan = plans.find((p) => p.id === planId);

  const checkoutMutation = useMutation({
    mutationFn: () =>
      api<{ data: { id: string } }>("/subscription/checkout", {
        method: "POST",
        body: { plan_id: planId },
      }),
    onSuccess: () => {
      // The plan banner on /subscription is stale the moment this succeeds.
      qc.invalidateQueries({ queryKey: queryKeys.subscription.current() });
      toast("Subscription activated. Your plan is live.");
      navigate("/subscription");
    },
  });

  if (plansLoading) {
    return (
      <div className="mx-auto max-w-2xl space-y-5">
        <Skeleton className="h-8 w-48 rounded-[var(--radius)]" />
        <Skeleton className="h-64 rounded-[var(--radius)]" />
      </div>
    );
  }

  if (!planId || !plan) {
    return (
      <div className="mx-auto max-w-2xl space-y-5">
        <h1 className="text-xl font-semibold">Checkout</h1>
        <Card>
          <CardContent className="py-8 text-center text-[var(--muted)]">
            No plan selected. Please go back and choose a plan.
          </CardContent>
        </Card>
        <Button variant="outline" onClick={() => navigate("/subscription")}>
          <ArrowLeft className="mr-2 h-4 w-4" />
          Back to Plans
        </Button>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-2xl space-y-5">
      <Button variant="ghost" size="sm" onClick={() => navigate("/subscription")}>
        <ArrowLeft className="mr-2 h-4 w-4" />
        Back to Plans
      </Button>

      <h1 className="text-xl font-semibold">Checkout</h1>

      <div className="grid gap-4 sm:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>Order Summary</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="flex items-center justify-between">
              <span className="text-[var(--muted)]">Plan</span>
              <Badge>{plan.name}</Badge>
            </div>
            <div className="flex items-center justify-between">
              <span className="text-[var(--muted)]">Billing</span>
              <span className="text-sm">Monthly</span>
            </div>
            <div className="flex items-center justify-between">
              <span className="text-[var(--muted)]">Renews</span>
              <span className="text-sm">{formatCurrency(plan.price)}/month</span>
            </div>
            <div className="border-t border-[var(--border)] pt-4">
              <div className="flex items-center justify-between">
                <span className="font-medium">Due today</span>
                <span className="text-xl font-bold">{formatCurrency(plan.price)}</span>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Plan Features</CardTitle>
          </CardHeader>
          <CardContent>
            <ul className="space-y-2">
              {plan.features.slice(0, 8).map((feature) => (
                <li key={feature.key} className="flex items-center gap-2 text-sm">
                  {feature.included ? (
                    <Check className="h-4 w-4 shrink-0 text-emerald-500" />
                  ) : (
                    <X className="h-4 w-4 shrink-0 text-[var(--color-danger)]" />
                  )}
                  <span className={feature.included ? "" : "text-[var(--muted)]"}>
                    {feature.label}
                    {feature.value ? `: ${feature.value}` : ""}
                  </span>
                </li>
              ))}
            </ul>
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardContent className="py-6">
          <div className="flex flex-col items-center gap-4 text-center">
            <Shield className="h-8 w-8 text-[var(--primary)]" />
            <div>
              <p className="font-medium">Secure Checkout</p>
              <p className="text-sm text-[var(--muted)]">
                Your plan activates immediately and renews at {formatCurrency(plan.price)}/month.
                Upgrade or cancel anytime.
              </p>
            </div>
            <Button
              size="lg"
              className="w-full max-w-sm"
              onClick={() => checkoutMutation.mutate()}
              disabled={checkoutMutation.isPending}
            >
              {checkoutMutation.isPending ? (
                <>
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                  Processing...
                </>
              ) : (
                `Pay ${formatCurrency(plan.price)}`
              )}
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}

export default CheckoutContent;
