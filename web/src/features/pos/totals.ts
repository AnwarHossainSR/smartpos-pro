import { calculateLineTax, type TaxComponentResult } from "@/features/tax/calc";
import type { PriceDisplay } from "@/features/tax/types";
import type { CartItem } from "@/features/pos/types";

export const round2 = (n: number) => Math.round(n * 100) / 100;

/** One row of a basket-level tax breakdown, as produced by `mergeTaxComponents`. */
export type TaxSummaryRow = { name: string; rate: number; amount: number };

/**
 * Per-line tax, with the full per-component breakdown (e.g. CGST 9% + SGST 9% shown
 * separately) — mirrors the backend's `App\Domain\Tax\TaxCalculator::forLine()`
 * (see `src/features/tax/calc.ts`) so the amount tendered matches the server's total to 2dp.
 *
 * Falls back to the legacy single blended rate when a product hasn't got a `tax_group`
 * yet (today's `/products` response) — see the `Product` type for why that's optional.
 */
export function lineTotals(
  { product, qty, unit_price, discount }: CartItem,
  priceDisplay: PriceDisplay,
): { net: number; tax: number; components: TaxComponentResult[] } {
  const taxable = Math.max(0, unit_price * qty - discount * qty);

  if (product.tax_group?.rates?.length) {
    const result = calculateLineTax(taxable, qty, product.tax_group.rates, priceDisplay);
    return { net: result.net, tax: result.tax, components: result.components as TaxComponentResult[] };
  }

  const rate = product.tax_rate ?? 0;
  const tax = product.tax_inclusive
    ? round2(taxable - taxable / (1 + rate / 100))
    : round2(taxable * (rate / 100));
  const net = product.tax_inclusive ? round2(taxable - tax) : round2(taxable);
  const components: TaxComponentResult[] =
    tax > 0 ? [{ name: "Tax", rate, type: "percentage", is_compound: false, base_amount: net, amount: tax }] : [];

  return { net, tax, components };
}
