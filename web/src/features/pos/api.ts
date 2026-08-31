import type { Customer, Payment, Product, Sale } from "@/features/pos/types";
import type { DiningTable } from "@/features/tables/types";
import type { PriceDisplay } from "@/features/tax/types";
import { api, type Envelope, type Paginated } from "@/lib/api";

/** Typed fetchers for the till. Kept thin — the hooks in `hooks/` own caching and cache keys. */

/** The client-only "★ Favorites" tab — a pseudo category id, filtered locally. */
export const FAVORITES_CATEGORY_ID = "__favorites__";

export interface ProductSearchParams {
  search: string;
  categoryId: string | null;
}

export interface SaleDraft {
  branchId?: string;
  warehouseId?: string;
  customerId: string | null;
  tableId: string | null;
  items: { product_id: string; quantity: number; unit_price: number; discount_amount: number }[];
  payments: Payment[];
  approvalToken?: string;
  tenderCurrency?: string;
  /** Omitted for a completed sale; `"held"` parks the basket instead of taking payment. */
  status?: "held";
}

function saleBody(draft: SaleDraft) {
  return {
    branch_id: draft.branchId,
    warehouse_id: draft.warehouseId,
    customer_id: draft.customerId,
    table_id: draft.tableId,
    ...(draft.status ? { status: draft.status } : {}),
    items: draft.items,
    payments: draft.payments,
    approval_token: draft.approvalToken ?? null,
    ...(draft.status ? {} : { tender_currency: draft.tenderCurrency ?? null }),
  };
}

export const posApi = {
  products: ({ search, categoryId }: ProductSearchParams) => {
    const params = new URLSearchParams({ per_page: "50", is_active: "true" });
    if (search) params.set("search", search);
    // `__favorites__` is a client-side pseudo-category — never send it to the API.
    if (categoryId && categoryId !== FAVORITES_CATEGORY_ID) params.set("category_id", categoryId);
    return api<Paginated<Product>>(`/products?${params.toString()}`);
  },

  /** Exact-match scanner lookup; `parsed_weight` comes back for embedded-weight barcodes. */
  barcode: (code: string) =>
    api<Envelope<Product> & { parsed_weight?: number | null }>(
      `/products/barcode/${encodeURIComponent(code)}`,
    ),

  customers: (search: string) =>
    api<Paginated<Customer>>(`/customers?search=${encodeURIComponent(search)}&per_page=10`),

  createCustomer: (body: { name: string; phone?: string }) =>
    api<Envelope<Customer>>("/customers", { method: "POST", body }),

  loyalty: (customerId: string) =>
    api<Envelope<{ points: number }>>(`/customers/${customerId}/loyalty`),

  // `api()` already targets /api/v1 — a "/v1/…" path here would resolve to /api/v1/v1/….
  tables: () => api<Paginated<DiningTable>>("/tables"),

  businessSettings: () =>
    api<Envelope<{ price_display: PriceDisplay; currency?: string }>>("/settings/business"),

  heldSales: () => api<Paginated<{ id: string }>>("/sales?status=held&per_page=20&sort=-sold_at"),

  /**
   * The Idempotency-Key is what makes a retry safe: a flaky till that resends the same
   * basket gets the original sale back (409) instead of charging twice, so the key is
   * minted once per attempt here and never reused across baskets.
   */
  createSale: (draft: SaleDraft) =>
    api<Envelope<Sale>>("/sales", {
      method: "POST",
      idempotencyKey: crypto.randomUUID(),
      body: saleBody(draft),
    }),
};
