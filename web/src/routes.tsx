import { type RouteObject, Navigate } from "react-router";
import { AppShell } from "@/components/layout/app-shell";

function RootRedirect() {
  const hasToken = !!localStorage.getItem("smartpos-auth");
  return <Navigate to={hasToken ? "/dashboard" : "/login"} replace />;
}

async function loadPage(factory: () => Promise<{ default: React.ComponentType }>) {
  const { default: Component } = await factory();
  return { Component };
}

export const routes: RouteObject[] = [
  {
    path: "/",
    element: <RootRedirect />,
  },

  // Auth routes (no shell)
  {
    path: "/login",
    lazy: () => loadPage(() => import("@/app/(auth)/login/page")),
  },
  {
    path: "/register",
    lazy: () => loadPage(() => import("@/app/(auth)/register/page")),
  },
  {
    path: "/forgot-password",
    lazy: () => loadPage(() => import("@/app/(auth)/forgot-password/page")),
  },
  {
    path: "/reset-password",
    lazy: () => loadPage(() => import("@/app/(auth)/reset-password/page")),
  },
  {
    path: "/verify-email",
    lazy: () => loadPage(() => import("@/app/(auth)/verify-email/page")),
  },

  // App routes (inside AppShell)
  {
    path: "/",
    element: <AppShell />,
    children: [
      { path: "dashboard", lazy: () => loadPage(() => import("@/app/(app)/dashboard/page")) },
      { path: "pos", lazy: () => loadPage(() => import("@/app/(app)/pos/page")) },
      { path: "customers", lazy: () => loadPage(() => import("@/app/(app)/customers/page")) },
      { path: "products", lazy: () => loadPage(() => import("@/app/(app)/products/page")) },
      { path: "products/labels", lazy: () => loadPage(() => import("@/app/(app)/products/labels/page")) },
      { path: "purchases", lazy: () => loadPage(() => import("@/app/(app)/purchases/page")) },
      { path: "sales", lazy: () => loadPage(() => import("@/app/(app)/sales/page")) },
      { path: "expenses", lazy: () => loadPage(() => import("@/app/(app)/expenses/page")) },
      { path: "accounting", lazy: () => loadPage(() => import("@/app/(app)/accounting/page")) },
      { path: "inventory", lazy: () => loadPage(() => import("@/app/(app)/inventory/page")) },
      { path: "stocktakes", lazy: () => loadPage(() => import("@/app/(app)/stocktakes/page")) },
      { path: "suppliers", lazy: () => loadPage(() => import("@/app/(app)/suppliers/page")) },
      { path: "catalog", lazy: () => loadPage(() => import("@/app/(app)/catalog/page")) },
      { path: "reports", lazy: () => loadPage(() => import("@/app/(app)/reports/page")) },
      { path: "reports/tax", lazy: () => loadPage(() => import("@/app/(app)/reports/tax/page")) },
      { path: "cash-register", lazy: () => loadPage(() => import("@/app/(app)/cash-register/page")) },
      { path: "loyalty", lazy: () => loadPage(() => import("@/app/(app)/loyalty/page")) },
      { path: "tables", lazy: () => loadPage(() => import("@/app/(app)/tables/page")) },
      { path: "team", lazy: () => loadPage(() => import("@/app/(app)/team/page")) },
      { path: "notifications", lazy: () => loadPage(() => import("@/app/(app)/notifications/page")) },
      { path: "settings", lazy: () => loadPage(() => import("@/app/(app)/settings/page")) },
      { path: "settings/approvals", lazy: () => loadPage(() => import("@/app/(app)/settings/approvals/page")) },
      { path: "settings/email-templates", lazy: () => loadPage(() => import("@/app/(app)/settings/email-templates/page")) },
      { path: "settings/locations", lazy: () => loadPage(() => import("@/app/(app)/settings/locations/page")) },
      { path: "settings/printer", lazy: () => loadPage(() => import("@/app/(app)/settings/printer/page")) },
      { path: "settings/security", lazy: () => loadPage(() => import("@/app/(app)/settings/security/page")) },
      { path: "settings/tax", lazy: () => loadPage(() => import("@/app/(app)/settings/tax/page")) },
      { path: "settings/webhooks", lazy: () => loadPage(() => import("@/app/(app)/settings/webhooks/page")) },
      { path: "subscription", lazy: () => loadPage(() => import("@/app/(app)/subscription/page")) },
      { path: "subscription/checkout", lazy: () => loadPage(() => import("@/app/(app)/subscription/checkout/page")) },
      { path: "support", lazy: () => loadPage(() => import("@/app/(app)/support/page")) },
      { path: "audit-log", lazy: () => loadPage(() => import("@/app/(app)/audit-log/page")) },
      { path: "reorder-suggestions", lazy: () => loadPage(() => import("@/app/(app)/reorder-suggestions/page")) },
      { path: "test-data", lazy: () => loadPage(() => import("@/app/(app)/test-data/page")) },
    ],
  },

  // Portal routes
  {
    path: "/portal",
    lazy: () => loadPage(() => import("@/app/portal/page")),
  },
  {
    path: "/portal/auth",
    lazy: () => loadPage(() => import("@/app/portal/auth/page")),
  },
  {
    path: "/portal/dashboard",
    lazy: () => loadPage(() => import("@/app/portal/dashboard/page")),
  },
  {
    path: "/portal/invoices/:id",
    lazy: () => loadPage(() => import("@/app/portal/invoices/[id]/page")),
  },

  // Order route (public, dynamic)
  {
    path: "/order/:token",
    lazy: () => loadPage(() => import("@/app/order/[token]/page")),
  },

  // Catch-all
  { path: "*", element: <Navigate to="/dashboard" replace /> },
];
