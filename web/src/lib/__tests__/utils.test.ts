import { describe, it, expect } from "vitest";
import { cn, formatCurrency, formatNumber } from "../utils";

describe("cn", () => {
  it("merges class names", () => {
    expect(cn("a", "b")).toBe("a b");
  });

  it("resolves Tailwind conflicts (last wins)", () => {
    expect(cn("px-2", "px-4")).toBe("px-4");
  });

  it("handles conditional classes", () => {
    expect(cn("base", false && "skipped", true && "included")).toBe("base included");
  });
});

describe("formatCurrency", () => {
  it("formats USD", () => {
    expect(formatCurrency(1234.5)).toBe("$1,234.50");
  });

  it("formats EUR", () => {
    expect(formatCurrency(99.9, "EUR")).toBe("€99.90");
  });

  it("handles zero", () => {
    expect(formatCurrency(0)).toBe("$0.00");
  });
});

describe("formatNumber", () => {
  it("formats with thousands separator", () => {
    expect(formatNumber(1000000)).toBe("1,000,000");
  });

  it("formats small numbers", () => {
    expect(formatNumber(42)).toBe("42");
  });
});
