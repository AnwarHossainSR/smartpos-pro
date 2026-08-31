import { useCallback, useMemo, useState } from "react";
import { lineTotals, round2, type TaxSummaryRow } from "@/features/pos/totals";
import type { CartItem, Product } from "@/features/pos/types";
import { mergeTaxComponents } from "@/features/tax/calc";
import type { PriceDisplay } from "@/features/tax/types";

export interface Cart {
  items: CartItem[];
  isEmpty: boolean;
  /** Sum of line quantities — the badge on the cart header, not the number of lines. */
  unitCount: number;
  subtotal: number;
  total: number;
  /** Per-component totals across the whole basket, e.g. "CGST 9%" and "SGST 9%" separately. */
  taxComponents: TaxSummaryRow[];
  add: (product: Product, weight?: number | null) => void;
  setQty: (productId: string, qty: number) => void;
  setUnitPrice: (productId: string, unitPrice: number) => void;
  setDiscount: (productId: string, discount: number) => void;
  remove: (productId: string) => void;
  /** Drops the last line — the F8 "void line" shortcut at the till. */
  voidLastLine: () => void;
  clear: () => void;
  /** Replaces the basket wholesale, used when recalling a held order. */
  replace: (items: CartItem[]) => void;
}

/**
 * The basket and everything derived from it. Totals are recomputed from `priceDisplay`
 * (the tenant's tax-inclusive/exclusive setting) rather than stored, so switching a
 * setting can never leave a stale total on screen next to a fresh line.
 */
export function useCart(priceDisplay: PriceDisplay): Cart {
  const [items, setItems] = useState<CartItem[]>([]);

  const add = useCallback((product: Product, weight?: number | null) => {
    setItems((prev) => {
      // A weighed item is a distinct line per weight; everything else stacks by product.
      const existing = prev.find((i) => i.product.id === product.id && i.weight === weight);
      if (existing) {
        return prev.map((i) =>
          i.product.id === product.id && i.weight === weight ? { ...i, qty: i.qty + 1 } : i,
        );
      }
      const unitPrice =
        product.is_weighed && weight && product.price_per_unit
          ? product.price_per_unit
          : product.selling_price;
      return [
        ...prev,
        { product, qty: 1, unit_price: unitPrice, discount: 0, weight: weight ?? null },
      ];
    });
  }, []);

  const setQty = useCallback((productId: string, qty: number) => {
    setItems((prev) =>
      qty <= 0
        ? prev.filter((i) => i.product.id !== productId)
        : prev.map((i) => (i.product.id === productId ? { ...i, qty } : i)),
    );
  }, []);

  const setUnitPrice = useCallback((productId: string, unitPrice: number) => {
    setItems((prev) =>
      prev.map((i) =>
        i.product.id === productId ? { ...i, unit_price: Math.max(0, unitPrice) } : i,
      ),
    );
  }, []);

  const setDiscount = useCallback((productId: string, discount: number) => {
    setItems((prev) =>
      prev.map((i) => (i.product.id === productId ? { ...i, discount: Math.max(0, discount) } : i)),
    );
  }, []);

  const remove = useCallback((productId: string) => {
    setItems((prev) => prev.filter((i) => i.product.id !== productId));
  }, []);

  const voidLastLine = useCallback(() => {
    setItems((prev) => (prev.length === 0 ? prev : prev.slice(0, -1)));
  }, []);

  const clear = useCallback(() => setItems([]), []);

  const totals = useMemo(() => {
    const perLine = items.map((item) => lineTotals(item, priceDisplay));
    const summed = perLine.reduce(
      (acc, { net, tax }) => ({ net: acc.net + net, tax: acc.tax + tax }),
      { net: 0, tax: 0 },
    );

    return {
      subtotal: round2(summed.net),
      total: round2(summed.net + summed.tax),
      taxComponents: mergeTaxComponents(perLine.map((l) => l.components)),
    };
  }, [items, priceDisplay]);

  return {
    items,
    isEmpty: items.length === 0,
    unitCount: items.reduce((sum, i) => sum + i.qty, 0),
    ...totals,
    add,
    setQty,
    setUnitPrice,
    setDiscount,
    remove,
    voidLastLine,
    clear,
    replace: setItems,
  };
}
