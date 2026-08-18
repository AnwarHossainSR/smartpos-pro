/**
 * Typed TanStack Query key factory.
 *
 * Every module gets `all` (invalidate everything for that resource), `lists()` /
 * `list(params)` (paginated/filterable collections — `params` is whatever shape the
 * page's `useResourceList()` call builds, kept loose here on purpose so each feature's
 * own hook can pass its own filter/sort/search/page bag without this file needing to
 * know every module's filter set), and `detail(id)` for single-record fetches.
 *
 * Keeping this in one place means `qc.invalidateQueries({ queryKey: queryKeys.products.all })`
 * reads the same everywhere and a rename only happens once.
 */

type ListParams = Record<string, unknown> | undefined;

function resource(name: string) {
  return {
    all: [name] as const,
    lists: () => [name, "list"] as const,
    list: (params?: ListParams) => [name, "list", params ?? {}] as const,
    detail: (id: string) => [name, "detail", id] as const,
  };
}

export const queryKeys = {
  products: resource("products"),
  categories: resource("categories"),
  brands: resource("brands"),
  units: resource("units"),
  warehouses: resource("warehouses"),
  sales: resource("sales"),
  purchases: resource("purchases"),
  customers: resource("customers"),
  suppliers: resource("suppliers"),
  inventory: resource("inventory"),

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

  businessSettings: ["business-settings"] as const,

  webhooks: resource("webhooks"),
  webhookDeliveries: resource("webhook-deliveries"),
} as const;
