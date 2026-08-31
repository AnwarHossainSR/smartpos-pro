import { describe, expect, it } from "vitest";
import { paginateLabels } from "./label-layout";
import {
  expandRows,
  type LabelProduct,
  labelsPerSheet,
  PAGE_HEIGHT_MM,
  PAGE_WIDTH_MM,
  SHEET_PRESETS,
  sheetContentHeightMm,
  sheetContentWidthMm,
  sheetPreset,
  unitLabel,
} from "./label-templates";

function product(id: string, overrides: Partial<LabelProduct> = {}): LabelProduct {
  return { id, name: `Product ${id}`, sku: `SKU-${id}`, selling_price: 9.99, ...overrides };
}

describe("sheet presets", () => {
  // A sheet only lines up with die-cut stock if its margins, labels and gaps add up to the
  // paper exactly — this is the arithmetic the print stylesheet then reproduces in mm.
  it("adds up to a real A4 page in both directions", () => {
    for (const preset of SHEET_PRESETS) {
      const width = preset.marginLeftMm * 2 + sheetContentWidthMm(preset);
      const height = preset.marginTopMm * 2 + sheetContentHeightMm(preset);
      expect(width, `${preset.id} width`).toBeCloseTo(PAGE_WIDTH_MM, 2);
      expect(height, `${preset.id} height`).toBeCloseTo(PAGE_HEIGHT_MM, 2);
    }
  });

  it("covers both the Avery-style and the 100 × 50 mm stock", () => {
    const ids = SHEET_PRESETS.map((preset) => preset.id);
    expect(ids).toContain("avery-l7160");
    expect(ids).toContain("shelf-100x50");

    const avery = sheetPreset("avery-l7160");
    expect([avery.labelWidthMm, avery.labelHeightMm]).toEqual([63.5, 38.1]);
    expect(labelsPerSheet(avery)).toBe(21);

    const wide = sheetPreset("shelf-100x50");
    expect([wide.labelWidthMm, wide.labelHeightMm]).toEqual([100, 50]);
    expect(labelsPerSheet(wide)).toBe(10);
  });

  it("falls back to the first preset for an unknown id", () => {
    expect(sheetPreset("nope").id).toBe(SHEET_PRESETS[0].id);
  });
});

describe("expandRows", () => {
  it("repeats each product by its quantity, in order", () => {
    const labels = expandRows([
      { product: product("a"), quantity: 2 },
      { product: product("b"), quantity: 1 },
    ]);
    expect(labels.map((item) => item.id)).toEqual(["a", "a", "b"]);
    expect(expandRows([])).toEqual([]);
  });
});

describe("paginateLabels", () => {
  const preset = sheetPreset("shelf-100x50"); // 10 per sheet
  const labels = Array.from({ length: 12 }, (_, index) => product(String(index)));

  it("fills whole sheets and pads the last one with empty slots", () => {
    const sheets = paginateLabels(labels, preset, 0);
    expect(sheets).toHaveLength(2);
    expect(sheets[0].every((slot) => slot !== null)).toBe(true);
    expect(sheets[1].filter((slot) => slot !== null)).toHaveLength(2);
    expect(sheets[1]).toHaveLength(10);
  });

  it("skips already-used labels on the first sheet only", () => {
    const sheets = paginateLabels(labels, preset, 3);
    expect(sheets[0].slice(0, 3)).toEqual([null, null, null]);
    expect(sheets[0][3]?.id).toBe("0");
    expect(sheets).toHaveLength(2);
    // The 4th…12th labels roll onto sheet two, which starts full again.
    expect(sheets[1][0]?.id).toBe("7");
  });

  it("clamps a nonsense offset instead of losing labels", () => {
    expect(paginateLabels(labels, preset, -5)[0][0]?.id).toBe("0");
    expect(paginateLabels(labels, preset, 99)[0].filter((slot) => slot !== null)).toHaveLength(1);
  });

  it("has nothing to paginate without labels", () => {
    expect(paginateLabels([], preset, 0)).toEqual([]);
  });
});

describe("unitLabel", () => {
  it("prefers the unit, then the brand, then the category", () => {
    expect(unitLabel(product("a", { unit: { id: "u", name: "Litre", short_name: "L" } }))).toBe(
      "L",
    );
    expect(unitLabel(product("a", { unit: { id: "u", name: "Litre", short_name: null } }))).toBe(
      "Litre",
    );
    expect(unitLabel(product("a", { brand: { id: "b", name: "Acme" } }))).toBe("Acme");
    expect(unitLabel(product("a", { category: { id: "c", name: "Drinks" } }))).toBe("Drinks");
    expect(unitLabel(product("a"))).toBeNull();
  });
});
