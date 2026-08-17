"use client";

import { ArrowLeft, Check, CreditCard, Loader2, Shield } from "lucide-react";
import { useRouter, useSearchParams } from "next/navigation";
import { Suspense } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { api, ApiError } from "@/lib/api";
import { formatCurrency, formatNumber } from "@/lib/utils";
import { toast } from "@/components/ui/toast";
import { useMutation, useQuery } from "@tanstack/react-query";

interface Plan {
  id: string;
  name: string;
  slug: string;
  price: number;
  interval: string;
  limits: Record<string, number | null>;
  features: string[] | null;
  is_active: boolean;
}

const FEATURE_LABELS: Record<string, string> = {
  branches: "Branches",
  warehouses: "Warehouses",
  users: "Team Members",
  products: "Products",
  monthly_sales: "Monthly Sales",
};

function CheckoutContent() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const planId = searchParams.get("plan");

  const { data: plansData, isLoading: plansLoading } = useQuery<{ data: Plan[] }>({
    queryKey: ["subscription-plans"],
    queryFn: () => api<{ data: Plan[] }>("/subscription/plans"),
  });

  const plan = plansData?.data.find((p) => p.id === planId);

  const checkoutMutation = useMutation({
    mutationFn: () =>
      api<{ data: { id: string } }>("/subscription/checkout", {
        method: "POST",
        body: { plan_id: planId },
      }),
    onSuccess: () => {
      toast("Subscription activated! Your plan has been upgraded successfully.");
      router.push("/subscription");
    },
    onError: (error: Error) => {
      const message = error instanceof ApiError ? error.message : "Something went wrong. Please try again.";
      toast(message, "error");
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
        <Button variant="outline" onClick={() => router.push("/subscription")}>
          <ArrowLeft className="mr-2 h-4 w-4" />
          Back to Plans
        </Button>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-2xl space-y-5">
      <Button variant="ghost" size="sm" onClick={() => router.push("/subscription")}>
        <ArrowLeft className="mr-2 h-4 w-4" />
        Back to Plans
      </Button>

      <h1 className="text-xl font-semibold">Checkout</h1>

      <div className="grid gap-4 sm:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <CreditCard className="h-5 w-5 text-[var(--primary)]" />
              Order Summary
            </CardTitle>
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
            <div className="border-t border-[var(--border)] pt-4">
              <div className="flex items-center justify-between">
                <span className="font-medium">Total</span>
                <span className="text-xl font-bold">{formatCurrency(plan.price)}/month</span>
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
              {Object.entries(plan.limits).map(([key, limit]) => (
                <li key={key} className="flex items-center gap-2 text-sm">
                  <Check className="h-4 w-4 text-[var(--primary)]" />
                  <span>
                    {FEATURE_LABELS[key] ?? key.replace(/_/g, " ")}:{" "}
                    {limit === null || limit < 0 ? "Unlimited" : formatNumber(limit)}
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
                Your payment information is encrypted and secure. Payment gateway will be integrated soon.
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
                `Subscribe for ${formatCurrency(plan.price)}/month`
              )}
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}

export default function CheckoutPage() {
  return (
    <Suspense
      fallback={
        <div className="mx-auto max-w-2xl space-y-5">
          <Skeleton className="h-8 w-48 rounded-[var(--radius)]" />
          <Skeleton className="h-64 rounded-[var(--radius)]" />
        </div>
      }
    >
      <CheckoutContent />
    </Suspense>
  );
}
