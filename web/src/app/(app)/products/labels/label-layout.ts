/**
 * Pure sheet-layout maths for the label printer.
 *
 * These live apart from `label-sheet.tsx` because that module exports React components:
 * a file that exports both components and plain helpers cannot be Fast-Refreshed, so Vite
 * re-executes it on every edit and any module-level value it created goes stale.
 * Keeping the maths component-free also means it unit-tests without a DOM.
 */

import { barcodeValueFor, encodeBarcode, layoutBarcode } from "@/lib/barcode";
import {
  type LabelProduct,
  type LabelTemplateId,
  labelsPerSheet,
  type SheetPreset,
} from "./label-templates";

/** Splits the queued labels into sheets, skipping `startOffset` slots on the first one. */
export function paginateLabels(
  labels: LabelProduct[],
  preset: SheetPreset,
  startOffset: number,
): (LabelProduct | null)[][] {
  const perSheet = labelsPerSheet(preset);
  const offset = Math.min(Math.max(Math.trunc(startOffset), 0), perSheet - 1);
  const slots: (LabelProduct | null)[] = [...Array<null>(offset).fill(null), ...labels];

  const sheets: (LabelProduct | null)[][] = [];
  for (let i = 0; i < slots.length; i += perSheet) {
    const sheet = slots.slice(i, i + perSheet);
    while (sheet.length < perSheet) sheet.push(null);
    sheets.push(sheet);
  }
  return sheets;
}

/**
 * Smallest module width any queued label will print at — under ~0.25 mm scanners start to
 * misread, which is worth telling the user before they burn a sheet of stock.
 */
export function narrowestModuleMm(
  labels: LabelProduct[],
  preset: SheetPreset,
  template: LabelTemplateId,
): number | null {
  const pad = Math.max(1.2, preset.labelHeightMm * 0.055);
  const inner = preset.labelWidthMm - pad * 2;
  const heightMm =
    template === "barcode-only"
      ? preset.labelHeightMm * 0.72
      : Math.max(8, preset.labelHeightMm * 0.3);

  let narrowest: number | null = null;
  for (const product of labels) {
    const code = encodeBarcode(barcodeValueFor(product));
    if (!code) continue;
    const { moduleMm } = layoutBarcode(code, { widthMm: inner, heightMm });
    if (narrowest === null || moduleMm < narrowest) narrowest = moduleMm;
  }
  return narrowest;
}
