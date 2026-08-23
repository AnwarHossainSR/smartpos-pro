/**
 * Typed TanStack Query key factory — the single source of truth for every cache key.
 *
 * Every module gets `all` (invalidate everything for that resource), `lists()` /
 * `list(params)` (paginated/filterable collections — `params` is whatever shape the
 * page's `useResourceList()` call builds, kept loose here on purpose so each feature's
 * own hook can pass its own filter/sort/search/page bag without this file needing to
 * know every module's filter set), and `detail(id)` for single-record fetches.
 *
 * **Never write an inline key array.** Two call sites that spell the same resource
 * differently (`["products"]` vs `["pos-products"]`) fetch and store it twice; two that
 * spell it the *same* but fetch different shapes (`/notifications?per_page=1` vs
 * `?per_page=50`) collide in one cache entry and clobber each other. Both bugs existed
 * here before this file was adopted everywhere.
 */

type ListParams = Record<string, unknown> | undefined;

interface ResourceKeys {
  readonly all: readonly [string];
  lists: () => readonly [string, "list"];
  list: (params?: ListParams) => readonly [string, "list", Record<string, unknown>];
  /** Unpaginated variant used to fill selects/comboboxes — cached apart from paged lists. */
  options: (params?: ListParams) => readonly [string, "options", Record<string, unknown>];
  detail: (id: string | undefined) => readonly [string, "detail", string];
}

function resource(name: string): ResourceKeys {
  return {
    all: [name] as const,
    lists: () => [name, "list"] as const,
    list: (params?: ListParams) => [name, "list", params ?? {}] as const,
    options: (params?: ListParams) => [name, "options", params ?? {}] as const,
    detail: (id: string | undefined) => [name, "detail", id ?? ""] as const,
  };
}

export const queryKeys = {
  /* ---- catalog ---- */
  products: resource("products"),
  categories: resource("categories"),
  brands: resource("brands"),
  units: resource("units"),

  /* ---- locations & stock ---- */
  warehouses: resource("warehouses"),
  branches: resource("branches"),
  inventory: resource("inventory"),
  stocktakes: resource("stocktakes"),
  reorderSuggestions: resource("reorder-suggestions"),

  /* ---- trading ---- */
  sales: resource("sales"),
  heldSales: resource("held-sales"),
  purchases: resource("purchases"),
  customers: resource("customers"),
  /** A sub-resource of one customer — kept under "customers" so `customers.all` clears it too. */
  customerLoyalty: (customerId: string | undefined) =>
    ["customers", "loyalty", customerId ?? ""] as const,
  suppliers: resource("suppliers"),
  expenses: resource("expenses"),
  expenseCategories: resource("expense-categories"),
  coupons: resource("coupons"),
  diningTables: resource("dining-tables"),

  /* ---- accounting ---- */
  accounts: resource("accounts"),
  journals: resource("journals"),
  trialBalance: resource("trial-balance"),

  /* ---- tax ---- */
  taxRates: resource("tax-rates"),
  taxGroups: resource("tax-groups"),
  taxZones: resource("tax-zones"),
  taxProfiles: resource("tax-profiles"),

  /** Not paginated lists — parameterised report queries, keyed by their filter bag. */
  taxReports: {
    all: ["tax-reports"] as const,
    summary: (params: { from: string; to: string; branch_id?: string | null }) =>
      ["tax-reports", "summary", params] as const,
    taxReturn: (params: { from: string; to: string; branch_id?: string | null }) =>
      ["tax-reports", "return", params] as const,
  },

  reports: {
    all: ["reports"] as const,
    run: (params?: ListParams) => ["reports", "run", params ?? {}] as const,
    zReport: (params?: ListParams) => ["reports", "z-report", params ?? {}] as const,
  },

  dashboard: {
    all: ["dashboard"] as const,
    summary: () => ["dashboard", "summary"] as const,
  },

  /* ---- account & session ---- */
  me: ["me"] as const,
  businessSettings: ["business-settings"] as const,
  twoFactorStatus: ["two-factor-status"] as const,
  authSessions: ["auth-sessions"] as const,
  lockoutStatus: ["lockout-status"] as const,
  approvalPolicy: ["approval-policy"] as const,
  emailTemplates: {
    ...resource("email-templates"),
    /**
     * The server-rendered preview of an *unsaved* draft, so the draft itself is the key.
     * Feed it a debounced draft — keying on raw editor state fires a POST per keystroke.
     */
    preview: (params: { key: string; subject: string; body: string }) =>
      ["email-templates", "preview", params] as const,
  },
  team: resource("team"),
  roles: resource("roles"),
  /** `GET /permissions` — the server's static permission catalog, not a paginated resource. */
  permissionMatrix: ["permissions", "matrix"] as const,
  auditLogs: resource("audit-logs"),

  /**
   * The badge count (`per_page=1`) and the full page (`per_page=50`) are different fetches
   * of the same endpoint — they must not share one cache entry.
   */
  notifications: {
    all: ["notifications"] as const,
    badge: () => ["notifications", "badge"] as const,
    list: (params?: ListParams) => ["notifications", "list", params ?? {}] as const,
  },

  shifts: {
    all: ["shifts"] as const,
    me: ["shifts", "me"] as const,
    list: (params?: ListParams) => ["shifts", "list", params ?? {}] as const,
    report: (params?: ListParams) => ["shifts", "report", params ?? {}] as const,
  },

  registers: {
    all: ["registers"] as const,
    list: () => ["registers", "list"] as const,
    session: (registerId: string | undefined) => ["registers", "session", registerId ?? ""] as const,
    sessionDetail: (sessionId: string | undefined) =>
      ["registers", "session-detail", sessionId ?? ""] as const,
  },

  subscription: {
    all: ["subscription"] as const,
    current: () => ["subscription", "current"] as const,
    plans: () => ["subscription", "plans"] as const,
  },

  webhooks: resource("webhooks"),
  webhookDeliveries: resource("webhook-deliveries"),
  printerProfiles: resource("printer-profiles"),
  labels: resource("labels"),

  /** Server-declared upload limits behind every `MediaField`. Config, not data. */
  mediaCollections: ["media-collections"] as const,

  /** Public/portal surfaces — a separate token, so a separate namespace. */
  portal: {
    all: ["portal"] as const,
    me: () => ["portal", "me"] as const,
    invoices: (params?: ListParams) => ["portal", "invoices", params ?? {}] as const,
    invoice: (id: string | undefined) => ["portal", "invoice", id ?? ""] as const,
  },
  orderStatus: (token: string | undefined) => ["order-status", token ?? ""] as const,

  /* ---- support (tenant-scoped) ---- */
  support: {
    all: ["support"] as const,
    tickets: (params?: ListParams) => ["support", "tickets", params ?? {}] as const,
    ticket: (id: string | undefined) => ["support", "ticket", id ?? ""] as const,
  },

  /* ---- super admin ---- */
  superAdmin: {
    all: ["super-admin"] as const,
    tenants: (params?: ListParams) => ["super-admin", "tenants", params ?? {}] as const,
    tenantDetail: (id: string | undefined) => ["super-admin", "tenant", id ?? ""] as const,
    revenueMetrics: (days?: number) => ["super-admin", "revenue", days ?? 30] as const,
    impersonations: () => ["super-admin", "impersonations"] as const,
    users: (params?: ListParams) => ["super-admin", "users", params ?? {}] as const,
    userDetail: (id: string | undefined) => ["super-admin", "user", id ?? ""] as const,
    audit: (params?: ListParams) => ["super-admin", "audit", params ?? {}] as const,
    health: () => ["super-admin", "health"] as const,
    plans: (params?: ListParams) => ["super-admin", "plans", params ?? {}] as const,
  },
} as const;
