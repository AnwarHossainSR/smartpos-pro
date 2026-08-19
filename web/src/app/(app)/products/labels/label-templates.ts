/**
 * Sheet stock and label templates for the barcode label printer.
 *
 * Every dimension is in millimetres and every preset adds up to a real A4 page
 * (210 × 297 mm): `marginLeft × 2 + columns × labelWidth + (columns − 1) × gapX = 210`,
 * and the same for the vertical axis. The print stylesheet turns `marginTop`/`marginLeft`
 * into the `@page` margin, so a sheet lands exactly where the die-cut labels are.
 */

export interface SheetPreset {
  id: string;
  name: string;
  description: string;
  columns: number;
  rows: number;
  labelWidthMm: number;
  labelHeightMm: number;
  /** Distance from the top of the page to the first row of labels. */
  marginTopMm: number;
  /** Distance from the left of the page to the first column. */
  marginLeftMm: number;
  gapXMm: number;
  gapYMm: number;
}

export const PAGE_WIDTH_MM = 210;
export const PAGE_HEIGHT_MM = 297;

export const SHEET_PRESETS: SheetPreset[] = [
  {
    id: "avery-l7160",
    name: "A4 · 3 × 7 · 63.5 × 38.1 mm",
    description: "Avery L7160 / 3475 — 21 labels per sheet",
    columns: 3,
    rows: 7,
    labelWidthMm: 63.5,
    labelHeightMm: 38.1,
    marginTopMm: 15.15,
    marginLeftMm: 7.21,
    gapXMm: 2.54,
    gapYMm: 0,
  },
  {
    id: "avery-l7159",
    name: "A4 · 3 × 8 · 63.5 × 33.9 mm",
    description: "Avery L7159 / 3474 — 24 labels per sheet",
    columns: 3,
    rows: 8,
    labelWidthMm: 63.5,
    labelHeightMm: 33.9,
    marginTopMm: 12.9,
    marginLeftMm: 7.21,
    gapXMm: 2.54,
    gapYMm: 0,
  },
  {
    id: "shelf-100x50",
    name: "A4 · 2 × 5 · 100 × 50 mm",
    description: "Shelf-edge stock — 10 labels per sheet",
    columns: 2,
    rows: 5,
    labelWidthMm: 100,
    labelHeightMm: 50,
    marginTopMm: 15.5,
    marginLeftMm: 3,
    gapXMm: 4,
    gapYMm: 4,
  },
];

export const DEFAULT_SHEET_ID = SHEET_PRESETS[0].id;

export function sheetPreset(id: string): SheetPreset {
  return SHEET_PRESETS.find((preset) => preset.id === id) ?? SHEET_PRESETS[0];
}

export function labelsPerSheet(preset: SheetPreset): number {
  return preset.columns * preset.rows;
}

/** Width of the printable band the labels sit in, i.e. the page minus its margins. */
export function sheetContentWidthMm(preset: SheetPreset): number {
  return preset.columns * preset.labelWidthMm + (preset.columns - 1) * preset.gapXMm;
}

export function sheetContentHeightMm(preset: SheetPreset): number {
  return preset.rows * preset.labelHeightMm + (preset.rows - 1) * preset.gapYMm;
}

export type LabelTemplateId = "price-tag" | "shelf-label" | "barcode-only";

export interface LabelTemplate {
  id: LabelTemplateId;
  name: string;
  description: string;
}

export const LABEL_TEMPLATES: LabelTemplate[] = [
  {
    id: "price-tag",
    name: "Price tag",
    description: "Product name, price and barcode",
  },
  {
    id: "shelf-label",
    name: "Shelf label",
    description: "Large price, unit/size and barcode",
  },
  {
    id: "barcode-only",
    name: "Barcode only",
    description: "Maximum bar height for small stock",
  },
];

/** The product fields a label can print. */
export interface LabelProduct {
  id: string;
  name: string;
  sku: string | null;
  barcode?: string | null;
  selling_price: number;
  category?: { id: string; name: string } | null;
  brand?: { id: string; name: string } | null;
  unit?: { id: string; name: string; short_name?: string | null } | null;
}

/** A product queued for printing, with the number of copies wanted. */
export interface LabelRow {
  product: LabelProduct;
  quantity: number;
}

/** The size/pack descriptor a shelf label carries under the price. */
export function unitLabel(product: LabelProduct): string | null {
  const unit = product.unit?.short_name?.trim() || product.unit?.name?.trim();
  if (unit) return unit;
  return product.brand?.name?.trim() || product.category?.name?.trim() || null;
}

/** Expands the queued rows into one entry per physical label. */
export function expandRows(rows: LabelRow[]): LabelProduct[] {
  const labels: LabelProduct[] = [];
  for (const row of rows) {
    for (let i = 0; i < row.quantity; i++) labels.push(row.product);
  }
  return labels;
}
