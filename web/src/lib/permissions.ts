/**
 * Client-side mirror of `app/Support/Rbac/PermissionCatalog::MATRIX`.
 *
 * Keeping the matrix here (rather than only fetching `/permissions` at runtime) buys two
 * things a runtime list cannot: `PermissionSlug` is a literal union, so a typo'd slug in a
 * `can()` call is a compile error instead of UI that silently never renders; and the route
 * guard can decide what a page needs before any request is made.
 *
 * If this drifts from the backend catalog the role editor still renders the server's list —
 * it fetches `/permissions` — but `can()` call sites are checked against this copy.
 */
export const PERMISSION_MATRIX = {
  dashboard: ["view"],
  branches: ["view", "create", "update", "delete"],
  warehouses: ["view", "create", "update", "delete"],
  products: ["view", "create", "update", "delete", "export"],
  inventory: ["view", "create", "update", "adjust", "export", "approve"],
  pos: ["view", "create", "refund", "discount", "price_override", "approve"],
  sales: ["view", "create", "update", "delete", "export", "refund"],
  purchases: ["view", "create", "update", "delete", "approve", "export", "edit", "return"],
  customers: ["view", "create", "update", "delete", "export"],
  suppliers: ["view", "create", "update", "delete", "export"],
  expenses: ["view", "create", "update", "delete", "approve"],
  accounting: ["view", "create", "update", "approve", "export"],
  taxes: ["view", "manage"],
  reports: ["view", "export"],
  coupons: ["view", "create", "update", "delete"],
  loyalty: ["view", "manage"],
  subscriptions: ["view", "manage"],
  shifts: ["view", "manage"],
  users: ["view", "create", "update", "delete"],
  roles: ["view", "create", "update", "delete"],
  settings: ["view", "update"],
  stocktakes: ["view", "create", "update"],
  "reorder-suggestions": ["view", "create"],
  audit: ["view"],
  // `routes/api.php` guards /v1/tables with `permission:tables.*`, but the module is not in
  // PermissionCatalog yet, so no `tables.*` permission row exists and only owner/super-admin
  // pass the middleware. Listed here so the route guard and nav can express the intent.
  tables: ["view", "create", "update"],
} as const;

type Matrix = typeof PERMISSION_MATRIX;

export type PermissionModule = keyof Matrix;

/** Every valid `<module>.<action>` slug, as a literal union. */
export type PermissionSlug = {
  [M in PermissionModule]: `${M}.${Matrix[M][number]}`;
}[PermissionModule];

export const PERMISSION_MODULES = Object.keys(PERMISSION_MATRIX) as PermissionModule[];

export const PERMISSION_SLUGS: PermissionSlug[] = PERMISSION_MODULES.flatMap((module) =>
  (PERMISSION_MATRIX[module] as readonly string[]).map(
    (action) => `${module}.${action}` as PermissionSlug,
  ),
);

const PERMISSION_SLUG_SET = new Set<string>(PERMISSION_SLUGS);

export function isPermissionSlug(value: string): value is PermissionSlug {
  return PERMISSION_SLUG_SET.has(value);
}

/** Splits a slug the backend sent us; returns null for anything malformed. */
export function splitSlug(slug: string): { module: string; action: string } | null {
  const index = slug.indexOf(".");
  if (index <= 0 || index === slug.length - 1) return null;
  return { module: slug.slice(0, index), action: slug.slice(index + 1) };
}

/**
 * Which permission a route needs. `any` means "at least one of these" — a couple of pages
 * are legitimately reachable from more than one grant (Locations is branches *or* warehouses).
 *
 * Ordered longest-prefix-first so `/reports/tax` resolves before `/reports` and
 * `/settings/webhooks` before `/settings`.
 */
interface RoutePermission {
  prefix: string;
  any: PermissionSlug[];
}

const ROUTE_PERMISSIONS: RoutePermission[] = [
  { prefix: "/dashboard", any: ["dashboard.view"] },
  { prefix: "/pos", any: ["pos.view"] },
  { prefix: "/sales", any: ["sales.view"] },
  { prefix: "/cash-register", any: ["accounting.view"] },
  { prefix: "/products/labels", any: ["products.view"] },
  { prefix: "/products", any: ["products.view"] },
  { prefix: "/inventory", any: ["inventory.view"] },
  { prefix: "/stocktakes", any: ["stocktakes.view"] },
  { prefix: "/catalog", any: ["products.view"] },
  { prefix: "/purchases", any: ["purchases.view"] },
  { prefix: "/suppliers", any: ["suppliers.view"] },
  { prefix: "/reorder-suggestions", any: ["reorder-suggestions.view"] },
  { prefix: "/customers", any: ["customers.view"] },
  { prefix: "/team", any: ["users.view", "roles.view", "shifts.view"] },
  { prefix: "/expenses", any: ["expenses.view"] },
  { prefix: "/accounting", any: ["accounting.view"] },
  { prefix: "/reports/tax", any: ["taxes.view"] },
  { prefix: "/reports", any: ["reports.view"] },
  { prefix: "/loyalty", any: ["coupons.view", "loyalty.view"] },
  { prefix: "/tables", any: ["tables.view"] },
  { prefix: "/subscription", any: ["subscriptions.view"] },
  { prefix: "/audit-log", any: ["audit.view"] },
  { prefix: "/settings/tax", any: ["taxes.view"] },
  { prefix: "/settings/locations", any: ["branches.view", "warehouses.view"] },
  // Personal account security (2FA, recovery codes) — every signed-in user owns their own.
  { prefix: "/settings/security", any: [] },
  { prefix: "/settings/email-templates", any: ["settings.view"] },
  { prefix: "/settings/approvals", any: ["settings.view"] },
  { prefix: "/settings/webhooks", any: ["settings.view"] },
  { prefix: "/settings/printer", any: ["settings.view"] },
  { prefix: "/settings", any: ["settings.view"] },
  { prefix: "/test-data", any: ["settings.update"] },
  // Personal to the signed-in user, no module grant involved.
  { prefix: "/notifications", any: [] },
  { prefix: "/support", any: [] },
].sort((a, b) => b.prefix.length - a.prefix.length);

/**
 * The permissions a pathname requires, or `[]` when the route is open to any signed-in user.
 * An unknown route also returns `[]` — the guard fails open for routes nobody mapped rather
 * than locking users out of a page that was just added.
 */
export function permissionsForPathname(pathname: string): PermissionSlug[] {
  const match = ROUTE_PERMISSIONS.find(
    (route) => pathname === route.prefix || pathname.startsWith(`${route.prefix}/`),
  );
  return match ? match.any : [];
}
