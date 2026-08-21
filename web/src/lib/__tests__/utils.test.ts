import { describe, it, expect, afterEach } from "vitest";
import { cn, formatCurrency, formatNumber, formatDate } from "../utils";
import { useLocaleStore } from "@/stores/locale";
import { useBusinessStore } from "@/stores/business";

describe("cn", () => {
  it("merges class names", () => {
    expect(cn("a", "b")).toBe("a b");
  });

  it("resolves Tailwind conflicts (last wins)", () => {
    expect(cn("px-2", "px-4")).toBe("px-4");
  });

  it("handles conditional classes", () => {
    const off = false;
    const on = true;
    expect(cn("base", off && "skipped", on && "included")).toBe("base included");
  });
});

describe("formatCurrency", () => {
  afterEach(() => {
    useLocaleStore.setState({ locale: "en" });
    useBusinessStore.setState({ currency: "USD" });
  });

  it("formats USD (default tenant currency)", () => {
    expect(formatCurrency(1234.5)).toBe("$1,234.50");
  });

  it("formats an explicit currency override", () => {
    expect(formatCurrency(99.9, "EUR")).toBe("€99.90");
  });

  it("handles zero", () => {
    expect(formatCurrency(0)).toBe("$0.00");
  });

  it("follows the tenant currency store when no override is passed", () => {
    useBusinessStore.setState({ currency: "BDT" });
    expect(formatCurrency(100)).toContain("100");
    expect(formatCurrency(100)).not.toBe("$100.00");
  });

  it("follows the active locale store for grouping/symbol placement", () => {
    useLocaleStore.setState({ locale: "de" });
    expect(formatCurrency(1234.5)).toBe("1.234,50 $");
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

describe("formatDate", () => {
  afterEach(() => {
    useLocaleStore.setState({ locale: "en" });
  });

  it("formats a date in the active locale", () => {
    expect(formatDate("2026-03-05")).toBe("Mar 5, 2026");
  });

  it("re-formats when the locale store changes", () => {
    useLocaleStore.setState({ locale: "de" });
    expect(formatDate("2026-03-05")).toBe("05.03.2026");
  });
});
