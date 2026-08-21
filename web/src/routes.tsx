import { Suspense, lazy } from "react";
import { type RouteObject, Navigate } from "react-router";
import { AppShell } from "@/components/layout/app-shell";
import { Spinner } from "@/components/ui/spinner";

function RootRedirect() {
  const hasToken = !!localStorage.getItem("smartpos-auth");
  return <Navigate to={hasToken ? "/dashboard" : "/login"} replace />;
}

function SuspenseWrapper() {
  return (
    <div className="flex min-h-screen items-center justify-center">
      <Spinner className="h-6 w-6 text-[var(--primary)]" />
    </div>
  );
}

function lazyRoute(factory: () => Promise<{ default: React.ComponentType }>) {
  const Component = lazy(factory);
  return (
    <Suspense fallback={<SuspenseWrapper />}>
      <Component />
    </Suspense>
  );
}

export const routes: RouteObject[] = [
  {
    path: "/",
    element: <RootRedirect />,
  },

  // Auth routes (no shell)
  {
    path: "/login",
    lazy: () => import("@/app/(auth)/login/page"),
  },
  {
    path: "/register",
    lazy: () => import("@/app/(auth)/register/page"),
  },
  {
    path: "/forgot-password",
    lazy: () => import("@/app/(auth)/forgot-password/page"),
  },
  {
    path: "/reset-password",
    lazy: () => import("@/app/(auth)/reset-password/page"),
  },
  {
    path: "/verify-email",
    lazy: () => import("@/app/(auth)/verify-email/page"),
  },

  // App routes (inside AppShell)
  {
    path: "/",
    element: <AppShell />,
    children: [
      { path: "dashboard", lazy: () => import("@/app/(app)/dashboard/page") },
      { path: "pos", lazy: () => import("@/app/(app)/pos/page") },
      { path: "customers", lazy: () => import("@/app/(app)/customers/page") },
      { path: "products", lazy: () => import("@/app/(app)/products/page") },
      { path: "products/labels", lazy: () => import("@/app/(app)/products/labels/page") },
      { path: "purchases", lazy: () => import("@/app/(app)/purchases/page") },
      { path: "sales", lazy: () => import("@/app/(app)/sales/page") },
      { path: "expenses", lazy: () => import("@/app/(app)/expenses/page") },
      { path: "accounting", lazy: () => import("@/app/(app)/accounting/page") },
      { path: "inventory", lazy: () => import("@/app/(app)/inventory/page") },
      { path: "stocktakes", lazy: () => import("@/app/(app)/stocktakes/page") },
      { path: "suppliers", lazy: () => import("@/app/(app)/suppliers/page") },
      { path: "catalog", lazy: () => import("@/app/(app)/catalog/page") },
      { path: "reports", lazy: () => import("@/app/(app)/reports/page") },
      { path: "reports/tax", lazy: () => import("@/app/(app)/reports/tax/page") },
      { path: "cash-register", lazy: () => import("@/app/(app)/cash-register/page") },
      { path: "loyalty", lazy: () => import("@/app/(app)/loyalty/page") },
      { path: "tables", lazy: () => import("@/app/(app)/tables/page") },
      { path: "team", lazy: () => import("@/app/(app)/team/page") },
      { path: "notifications", lazy: () => import("@/app/(app)/notifications/page") },
      { path: "settings", lazy: () => import("@/app/(app)/settings/page") },
      { path: "settings/approvals", lazy: () => import("@/app/(app)/settings/approvals/page") },
      { path: "settings/email-templates", lazy: () => import("@/app/(app)/settings/email-templates/page") },
      { path: "settings/locations", lazy: () => import("@/app/(app)/settings/locations/page") },
      { path: "settings/printer", lazy: () => import("@/app/(app)/settings/printer/page") },
      { path: "settings/security", lazy: () => import("@/app/(app)/settings/security/page") },
      { path: "settings/tax", lazy: () => import("@/app/(app)/settings/tax/page") },
      { path: "settings/webhooks", lazy: () => import("@/app/(app)/settings/webhooks/page") },
      { path: "subscription", lazy: () => import("@/app/(app)/subscription/page") },
      { path: "subscription/checkout", lazy: () => import("@/app/(app)/subscription/checkout/page") },
      { path: "support", lazy: () => import("@/app/(app)/support/page") },
      { path: "audit-log", lazy: () => import("@/app/(app)/audit-log/page") },
      { path: "reorder-suggestions", lazy: () => import("@/app/(app)/reorder-suggestions/page") },
      { path: "test-data", lazy: () => import("@/app/(app)/test-data/page") },
    ],
  },

  // Portal routes
  {
    path: "/portal",
    lazy: () => import("@/app/portal/page"),
  },
  {
    path: "/portal/auth",
    lazy: () => import("@/app/portal/auth/page"),
  },
  {
    path: "/portal/dashboard",
    lazy: () => import("@/app/portal/dashboard/page"),
  },
  {
    path: "/portal/invoices/:id",
    lazy: () => import("@/app/portal/invoices/[id]/page"),
  },

  // Order route (public, dynamic)
  {
    path: "/order/:token",
    lazy: () => import("@/app/order/[token]/page"),
  },

  // Catch-all
  { path: "*", element: <Navigate to="/dashboard" replace /> },
];
