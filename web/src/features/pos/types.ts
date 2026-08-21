import type { TaxGroup } from "@/features/tax/types";

/** Entity shapes the till works with. Shared by the page, its panels and its hooks. */

export interface Product {
  id: string;
  name: string;
  sku: string | null;
  selling_price: number;
  purchase_price: number;
  /** Legacy single-rate fields — still what `/products` returns today. Kept as the fallback
   *  path in `lineTotals()` so the cart keeps working even before the API adds `tax_group`. */
  tax_rate: number;
  tax_inclusive: boolean;
  /** Not yet returned by `/products` (only `/tax-groups` has it so far) — read defensively so
   *  the multi-component breakdown activates automatically once the API adds this relation. */
  tax_group?: TaxGroup | null;
  stock: number;
  min_stock_level?: number;
  image_url?: string | null;
  is_active: boolean;
  category: { id: string; name: string } | null;
  is_weighed?: boolean;
  weight_unit?: string | null;
  price_per_unit?: number | null;
}

export interface Customer {
  id: string;
  name: string;
  phone: string | null;
  balance_due: number;
  loyalty_points: number;
}

export interface CartItem {
  product: Product;
  qty: number;
  unit_price: number;
  discount: number;
  weight?: number | null;
}

export interface Payment {
  method: string;
  amount: number;
}

export interface Sale {
  id: string;
  invoice_number: string;
  subtotal: number;
  tax_amount: number;
  total: number;
  paid_amount: number;
  change_amount: number;
  due_amount: number;
  sold_at: string;
  customer?: { id: string; name: string };
  items: {
    name: string;
    quantity: number;
    unit_price: number;
    line_total: number;
    tax_amount?: number;
    /** Not yet returned by `/sales` (only reversed on `/sale-returns` so far) — rendered when
     *  present, falls back to the blended `tax_amount` line on the receipt when absent. */
    taxes?: { name: string; rate: number; amount: number }[];
  }[];
  payments: Payment[];
}
