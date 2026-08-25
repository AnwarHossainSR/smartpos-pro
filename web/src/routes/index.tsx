import { RouteErrorBoundary } from "@/components/error-boundary";
import { AppShell } from "@/components/layout/app-shell";
import { RouteMeta } from "@/components/route-meta";
import { loadPage, seo } from "@/routes/helpers";
import { type RouteObject, Navigate } from "react-router";

export const routes: RouteObject[] = [
  {
    element: <RouteMeta />,
    /**
     * Last line of defence. With no `errorElement` anywhere, React Router shows its own
     * developer screen (stack trace, "Hey developer") to whoever is using the app.
     */
    errorElement: <RouteErrorBoundary />,
    children: [
      {
        path: "/",
        lazy: () => loadPage(() => import("@/app/(app)/home/page")),
        handle: seo(
          "SmartPOS Pro",
          "Run your shop end to end: point of sale, inventory, purchasing, customers and accounting in a single platform.",
          { indexable: true },
        ),
      },

      // Auth routes (no shell)
      {
        path: "/login",
        lazy: () => loadPage(() => import("@/app/(auth)/login/page")),
        handle: seo(
          "Sign in",
          "Sign in to your SmartPOS Pro account to manage sales, stock and reporting for your business.",
          { indexable: true },
        ),
      },
      {
        path: "/register",
        lazy: () => loadPage(() => import("@/app/(auth)/register/page")),
        handle: seo(
          "Create your account",
          "Start selling with SmartPOS Pro — create a business account and set up your point of sale in minutes.",
          { indexable: true },
        ),
      },
      {
        path: "/forgot-password",
        lazy: () => loadPage(() => import("@/app/(auth)/forgot-password/page")),
        handle: seo(
          "Reset your password",
          "Request a password reset link for your SmartPOS Pro account.",
          { indexable: true },
        ),
      },
      {
        path: "/reset-password",
        lazy: () => loadPage(() => import("@/app/(auth)/reset-password/page")),
        handle: seo("Choose a new password", "Set a new password for your SmartPOS Pro account."),
      },
      {
        path: "/verify-email",
        lazy: () => loadPage(() => import("@/app/(auth)/verify-email/page")),
        handle: seo(
          "Verify your email",
          "Confirm your email address to activate your SmartPOS Pro account.",
        ),
      },

      // App routes (inside AppShell)
      {
        path: "/",
        element: <AppShell />,
        children: [
          {
            /**
             * Pathless layout route: a page that throws renders the error panel *inside*
             * the shell's <main>, so the sidebar and header stay usable and the user can
             * navigate somewhere else instead of losing the whole app.
             */
            errorElement: <RouteErrorBoundary />,
            children: [
              {
                path: "dashboard",
                lazy: () => loadPage(() => import("@/app/(app)/dashboard/page")),
                handle: seo("Dashboard", "Sales for the day, revenue trends and stock alerts at a glance."),
              },
              {
                path: "pos",
                lazy: () => loadPage(() => import("@/app/(app)/pos/page")),
                handle: seo("POS Terminal", "Ring up sales, take payments and print receipts."),
              },
              {
                path: "customers",
                lazy: () => loadPage(() => import("@/app/(app)/customers/page")),
                handle: seo("Customers", "Customer directory, purchase history and balances."),
              },
              {
                path: "products",
                lazy: () => loadPage(() => import("@/app/(app)/products/page")),
                handle: seo("Products", "Manage your product catalog, pricing and barcodes."),
              },
              {
                path: "products/labels",
                lazy: () => loadPage(() => import("@/app/(app)/products/labels/page")),
                handle: seo("Product Labels", "Design and print barcode label sheets for your products."),
              },
              {
                path: "purchases",
                lazy: () => loadPage(() => import("@/app/(app)/purchases/page")),
                handle: seo("Purchases", "Purchase orders, goods receipts and supplier bills."),
              },
              {
                path: "sales",
                lazy: () => loadPage(() => import("@/app/(app)/sales/page")),
                handle: seo("Sales", "Browse sales history, receipts, refunds and returns."),
              },
              {
                path: "expenses",
                lazy: () => loadPage(() => import("@/app/(app)/expenses/page")),
                handle: seo("Expenses", "Record and categorise business expenses."),
              },
              {
                path: "accounting",
                lazy: () => loadPage(() => import("@/app/(app)/accounting/page")),
                handle: seo("Accounting", "Chart of accounts, journals and financial statements."),
              },
              {
                path: "inventory",
                lazy: () => loadPage(() => import("@/app/(app)/inventory/page")),
                handle: seo("Inventory", "Stock levels, transfers and movement history by location."),
              },
              {
                path: "stocktakes",
                lazy: () => loadPage(() => import("@/app/(app)/stocktakes/page")),
                handle: seo("Stocktakes", "Count stock, review variances and post adjustments."),
              },
              {
                path: "suppliers",
                lazy: () => loadPage(() => import("@/app/(app)/suppliers/page")),
                handle: seo("Suppliers", "Supplier directory, terms and outstanding payables."),
              },
              {
                path: "catalog",
                lazy: () => loadPage(() => import("@/app/(app)/catalog/page")),
                handle: seo("Catalog Setup", "Categories, brands, units and product attributes."),
              },
              {
                path: "reports",
                lazy: () => loadPage(() => import("@/app/(app)/reports/page")),
                handle: seo("Reports", "Sales, profit, inventory and cash-flow reporting."),
              },
              {
                path: "reports/tax",
                lazy: () => loadPage(() => import("@/app/(app)/reports/tax/page")),
                handle: seo("Tax Report", "Tax collected and paid, broken down by rate and period."),
              },
              {
                path: "cash-register",
                lazy: () => loadPage(() => import("@/app/(app)/cash-register/page")),
                handle: seo(
                  "Cash Register",
                  "Open and close shifts, count the drawer and reconcile cash.",
                ),
              },
              {
                path: "loyalty",
                lazy: () => loadPage(() => import("@/app/(app)/loyalty/page")),
                handle: seo("Loyalty", "Loyalty programme, points balances and rewards."),
              },
              {
                path: "team",
                lazy: () => loadPage(() => import("@/app/(app)/team/page")),
                handle: seo("Team", "Staff accounts, roles and permissions."),
              },
              {
                path: "notifications",
                lazy: () => loadPage(() => import("@/app/(app)/notifications/page")),
                handle: seo("Notifications", "Alerts and activity from across your business."),
              },
              {
                path: "settings",
                lazy: () => loadPage(() => import("@/app/(app)/settings/page")),
                handle: seo("Settings", "Business profile, currency, receipts and preferences."),
              },
              {
                path: "settings/approvals",
                lazy: () => loadPage(() => import("@/app/(app)/settings/approvals/page")),
                handle: seo("Approvals", "Approval rules and thresholds for sensitive actions."),
              },
              {
                path: "settings/email-templates",
                lazy: () => loadPage(() => import("@/app/(app)/settings/email-templates/page")),
                handle: seo("Email Templates", "Customise the emails sent to your customers."),
              },
              {
                path: "settings/locations",
                lazy: () => loadPage(() => import("@/app/(app)/settings/locations/page")),
                handle: seo("Locations", "Stores, warehouses and per-location settings."),
              },
              {
                path: "settings/printer",
                lazy: () => loadPage(() => import("@/app/(app)/settings/printer/page")),
                handle: seo("Printer Settings", "Receipt and label printer profiles."),
              },
              {
                path: "settings/security",
                lazy: () => loadPage(() => import("@/app/(app)/settings/security/page")),
                handle: seo("Security", "Sessions, two-factor authentication and API access."),
              },
              {
                path: "settings/tax",
                lazy: () => loadPage(() => import("@/app/(app)/settings/tax/page")),
                handle: seo("Tax Settings", "Tax rates, groups and how tax is applied at checkout."),
              },
              {
                path: "settings/webhooks",
                lazy: () => loadPage(() => import("@/app/(app)/settings/webhooks/page")),
                handle: seo("Webhooks", "Send business events to your own endpoints."),
              },
              {
                path: "subscription",
                lazy: () => loadPage(() => import("@/app/(app)/subscription/page")),
                handle: seo("Subscription", "Your plan, usage limits and billing history."),
              },
              {
                path: "subscription/checkout",
                lazy: () => loadPage(() => import("@/app/(app)/subscription/checkout/page")),
                handle: seo("Checkout", "Confirm your plan and complete payment."),
              },
              {
                path: "support",
                lazy: () => loadPage(() => import("@/app/(app)/support/page")),
                handle: seo("Support", "Get help with SmartPOS Pro."),
              },
              {
                path: "audit-log",
                lazy: () => loadPage(() => import("@/app/(app)/audit-log/page")),
                handle: seo("Audit Log", "A record of who changed what, and when."),
              },
              {
                path: "reorder-suggestions",
                lazy: () => loadPage(() => import("@/app/(app)/reorder-suggestions/page")),
                handle: seo(
                  "Reorder Suggestions",
                  "Products running low, with suggested order quantities.",
                ),
              },
              {
                path: "super-admin",
                lazy: () => loadPage(() => import("@/app/(app)/super-admin/dashboard/page")),
                handle: seo("Platform Dashboard", "Platform-wide metrics, tenants and revenue at a glance."),
              },
              {
                path: "super-admin/tenants",
                lazy: () => loadPage(() => import("@/app/(app)/super-admin/tenants/page")),
                handle: seo("Tenants", "Manage platform tenants, subscriptions and access."),
              },
              {
                path: "super-admin/plans",
                lazy: () => loadPage(() => import("@/app/(app)/super-admin/plans/page")),
                handle: seo("Plans", "Manage subscription plans and pricing on the platform."),
              },
              {
                path: "super-admin/users",
                lazy: () => loadPage(() => import("@/app/(app)/super-admin/users/page")),
                handle: seo("Users", "Manage users across all tenants on the platform."),
              },
              {
                path: "super-admin/revenue",
                lazy: () => loadPage(() => import("@/app/(app)/super-admin/revenue/page")),
                handle: seo("Revenue", "MRR, plan distribution and subscription revenue metrics."),
              },
              {
                path: "super-admin/impersonations",
                lazy: () => loadPage(() => import("@/app/(app)/super-admin/impersonations/page")),
                handle: seo("Impersonations", "Active and recent impersonation sessions across the platform."),
              },
              {
                path: "super-admin/audit",
                lazy: () => loadPage(() => import("@/app/(app)/super-admin/audit/page")),
                handle: seo("Audit Logs", "Platform-wide audit trail of admin actions."),
              },
              {
                path: "super-admin/health",
                lazy: () => loadPage(() => import("@/app/(app)/super-admin/health/page")),
                handle: seo("System Health", "PHP version, Laravel version, database, queue, cache and disk storage status."),
              },
              {
                path: "super-admin/support-tickets",
                lazy: () => loadPage(() => import("@/app/(app)/super-admin/support-tickets/page")),
                handle: seo("Support Tickets", "View and manage customer support tickets across all tenants."),
              },
              {
                path: "super-admin/backups",
                lazy: () => loadPage(() => import("@/app/(app)/super-admin/backups/page")),
                handle: seo("Database Backups", "View backup history, create new backups and download restore files."),
              },
              {
                path: "super-admin/settings",
                lazy: () => loadPage(() => import("@/app/(app)/super-admin/settings/page")),
                handle: seo("Platform Settings", "Configure global modules and platform behavior."),
              },

            ],
          },
        ],
      },

      {
        path: "/impersonate",
        lazy: () => loadPage(() => import("@/app/(auth)/impersonate/page")),
        handle: seo("Impersonating…", "Signing you in to the tenant account."),
      },

      // Portal routes
      {
        path: "/portal",
        lazy: () => loadPage(() => import("@/app/portal/page")),
        handle: seo("Customer Portal", "View your invoices, statements and order history.", {
          indexable: true,
        }),
      },
      {
        path: "/portal/auth",
        lazy: () => loadPage(() => import("@/app/portal/auth/page")),
        handle: seo("Portal Sign In", "Sign in to view your invoices and orders.", {
          indexable: true,
        }),
      },
      {
        path: "/portal/dashboard",
        lazy: () => loadPage(() => import("@/app/portal/dashboard/page")),
        handle: seo("Portal Dashboard", "Your invoices, payments and recent orders."),
      },
      {
        path: "/portal/invoices/:id",
        lazy: () => loadPage(() => import("@/app/portal/invoices/[id]/page")),
        handle: seo("Invoice", "View and download your invoice."),
      },

      // Order route (public, dynamic)
      {
        path: "/order/:token",
        lazy: () => loadPage(() => import("@/app/order/[token]/page")),
        handle: seo("Order Status", "Track the status of your order in real time."),
      },

      // Catch-all
      {
        path: "*",
        element: <Navigate to="/dashboard" replace />,
        handle: seo("Page not found", "The page you are looking for does not exist."),
      },
    ],
  },
];
