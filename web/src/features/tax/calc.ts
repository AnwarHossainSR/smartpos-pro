import type { PriceDisplay, TaxRate } from "@/features/tax/types";

export interface TaxComponentResult {
  name: string;
  rate: number;
  type: "percentage" | "fixed";
  is_compound: boolean;
  base_amount: number;
  amount: number;
}

export interface LineTaxResult {
  net: number;
  tax: number;
  gross: number;
  components: TaxComponentResult[];
}

const round2 = (n: number) => Math.round((n + Number.EPSILON) * 100) / 100;

/** Ordered rates for a tax group — respects each component's pivot `priority`, since a
 *  compound component (PST-on-GST) must apply after the ones it compounds on. */
export function orderedRates(rates: TaxRate[]): TaxRate[] {
  return [...rates].sort((a, b) => (a.priority ?? 0) - (b.priority ?? 0));
}

function componentsFor(net: number, quantity: number, rates: TaxRate[]): TaxComponentResult[] {
  let running = net;
  const components: TaxComponentResult[] = [];

  for (const rate of rates) {
    let base: number;
    let amount: number;

    if (rate.type === "fixed") {
      // A per-unit duty is independent of price, so it has no percentage base.
      base = net;
      amount = rate.rate * quantity;
    } else {
      base = rate.is_compound ? running : net;
      amount = base * (rate.rate / 100);
    }

    components.push({
      name: rate.name,
      rate: rate.rate,
      type: rate.type,
      is_compound: rate.is_compound,
      base_amount: base,
      amount,
    });
    running += amount;
  }

  return components;
}

/** Recovers the net from a tax-inclusive price — mirrors the backend's netFromGross(). */
function netFromGross(gross: number, quantity: number, rates: TaxRate[]): number {
  const fixedTotal = rates
    .filter((r) => r.type === "fixed")
    .reduce((sum, r) => sum + r.rate * quantity, 0);
  const percentageRates = rates.filter((r) => r.type !== "fixed");
  const remaining = gross - fixedTotal;
  if (percentageRates.length === 0) return remaining;

  let running = 1.0;
  for (const rate of percentageRates) {
    const base = rate.is_compound ? running : 1.0;
    running += base * (rate.rate / 100);
  }

  return running > 0 ? remaining / running : remaining;
}

/**
 * Client-side mirror of the backend's `App\Domain\Tax\TaxCalculator::forLine()` — used for a
 * live cart preview before checkout, where a network round-trip per keystroke isn't practical.
 * The server remains authoritative at checkout; this only has to agree with it to 2dp, the same
 * contract the old single-rate `lineTotals()` helper had with `SaleService::buildLines()`.
 */
export function calculateLineTax(
  amount: number,
  quantity: number,
  rates: TaxRate[],
  priceDisplay: PriceDisplay = "exclusive",
): LineTaxResult {
  if (rates.length === 0) {
    const net = round2(amount);
    return { net, tax: 0, gross: net, components: [] };
  }

  const ordered = orderedRates(rates);
  const net = priceDisplay === "inclusive" ? netFromGross(amount, quantity, ordered) : amount;
  const raw = componentsFor(net, quantity, ordered);
  const tax = raw.reduce((sum, c) => sum + c.amount, 0);

  return {
    net: round2(net),
    tax: round2(tax),
    gross: round2(net + tax),
    components: raw.map((c) => ({
      ...c,
      amount: round2(c.amount),
      base_amount: round2(c.base_amount),
    })),
  };
}

/** Merges per-component tax across every cart line into cart-level totals, e.g.
 *  "CGST 9%: ৳18.00" + "SGST 9%: ৳18.00" instead of one blended number. */
export function mergeTaxComponents(
  lines: TaxComponentResult[][],
): { name: string; rate: number; amount: number }[] {
  const byKey = new Map<string, { name: string; rate: number; amount: number }>();

  for (const line of lines) {
    for (const c of line) {
      const key = `${c.name}@${c.rate}`;
      const existing = byKey.get(key);
      if (existing) existing.amount = round2(existing.amount + c.amount);
      else byKey.set(key, { name: c.name, rate: c.rate, amount: c.amount });
    }
  }

  return Array.from(byKey.values());
}
