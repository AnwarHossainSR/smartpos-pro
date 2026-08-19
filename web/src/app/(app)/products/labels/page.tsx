"use client";

import { useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import { ArrowLeft, Plus, Printer, RotateCw, Trash2, TriangleAlert } from "lucide-react";
import { api, type Paginated } from "@/lib/api";
import { MIN_MODULE_MM } from "@/lib/barcode";
import { formatCurrency } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Combobox } from "@/components/ui/combobox";
import { Input, Label as FieldLabel } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Skeleton } from "@/components/ui/skeleton";
import { useAuth } from "@/stores/auth";
import { LabelSheet, narrowestModuleMm, paginateLabels, type LabelOptions } from "./label-sheet";
import {
  DEFAULT_SHEET_ID,
  LABEL_TEMPLATES,
  PAGE_HEIGHT_MM,
  PAGE_WIDTH_MM,
  SHEET_PRESETS,
  expandRows,
  labelsPerSheet,
  sheetPreset,
  type LabelProduct,
  type LabelRow,
  type LabelTemplateId,
} from "./label-templates";

/** Past this many labels the preview gets heavy enough to be worth a word of warning. */
const BUSY_LABEL_COUNT = 500;
const MAX_QUANTITY = 500;
/** Gap between sheets in the on-screen preview only. */
const PREVIEW_GAP_MM = 6;

const ZOOMS = [
  { value: "0.5", label: "50%" },
  { value: "0.75", label: "75%" },
  { value: "1", label: "100%" },
];

export default function LabelPrintingPage() {
  const { can } = useAuth();
  const canView = can("products.view");

  const [rows, setRows] = useState<LabelRow[]>([]);
  const [sheetId, setSheetId] = useState(DEFAULT_SHEET_ID);
  const [template, setTemplate] = useState<LabelTemplateId>("price-tag");
  const [startOffset, setStartOffset] = useState(0);
  const [showName, setShowName] = useState(true);
  const [showPrice, setShowPrice] = useState(true);
  const [showOutline, setShowOutline] = useState(false);
  const [zoom, setZoom] = useState("0.75");

  const preset = sheetPreset(sheetId);
  const perSheet = labelsPerSheet(preset);
  const offset = Math.min(Math.max(startOffset, 0), perSheet - 1);

  // The page geometry has to come from a real @page rule — a printed sheet only lines up with
  // die-cut stock if the paper margins match the stock's. It is injected here rather than in
  // globals.css because it changes with the selected sheet and must not outlive this page.
  useEffect(() => {
    const style = document.createElement("style");
    style.dataset.labelPageRule = "";
    style.textContent = `@page { size: A4 portrait; margin: ${preset.marginTopMm}mm ${preset.marginLeftMm}mm; }`;
    document.head.appendChild(style);
    return () => {
      style.remove();
    };
  }, [preset]);

  const {
    data: recent,
    isLoading: recentLoading,
    isError: recentError,
    refetch: refetchRecent,
  } = useQuery({
    queryKey: ["label-recent-products"],
    queryFn: () => api<Paginated<LabelProduct>>("/products?per_page=6&sort=-created_at"),
    enabled: canView,
    staleTime: 60_000,
  });

  const labels = useMemo(() => expandRows(rows), [rows]);
  const sheets = useMemo(() => paginateLabels(labels, preset, offset), [labels, preset, offset]);
  const narrowest = useMemo(
    () => narrowestModuleMm(labels, preset, template),
    [labels, preset, template],
  );

  const options: LabelOptions = { template, showName, showPrice, showOutline };
  const totalLabels = labels.length;
  const missingBarcode = rows.filter((row) => !row.product.barcode && !row.product.sku).length;

  function addProduct(product: LabelProduct | undefined) {
    if (!product) return;
    setRows((current) => {
      const existing = current.find((row) => row.product.id === product.id);
      if (existing) {
        return current.map((row) =>
          row.product.id === product.id
            ? { ...row, quantity: Math.min(row.quantity + 1, MAX_QUANTITY) }
            : row,
        );
      }
      return [...current, { product, quantity: 1 }];
    });
  }

  function setQuantity(id: string, quantity: number) {
    setRows((current) =>
      current.map((row) =>
        row.product.id === id
          ? { ...row, quantity: Math.min(Math.max(Math.trunc(quantity) || 1, 1), MAX_QUANTITY) }
          : row,
      ),
    );
  }

  function removeRow(id: string) {
    setRows((current) => current.filter((row) => row.product.id !== id));
  }

  if (!canView) {
    return (
      <div className="py-16 text-center text-[var(--muted)]">
        You do not have access to product labels.
      </div>
    );
  }

  const scale = Number(zoom) || 1;
  const previewHeightMm = sheets.length * (PAGE_HEIGHT_MM + PREVIEW_GAP_MM);

  return (
    <div className="space-y-5">
      <div data-print-hide className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <Button variant="ghost" size="icon" className="h-8 w-8" asChild>
            <Link href="/products" aria-label="Back to products">
              <ArrowLeft className="h-4 w-4" />
            </Link>
          </Button>
          <div>
            <h1 className="text-lg font-semibold">Barcode labels</h1>
            <p className="text-sm text-[var(--muted)]">
              Build a sheet of price tags or shelf labels and print it on label stock.
            </p>
          </div>
        </div>
        <div className="flex items-center gap-2">
          <span className="text-sm text-[var(--muted)]">
            {totalLabels} {totalLabels === 1 ? "label" : "labels"} · {sheets.length}{" "}
            {sheets.length === 1 ? "sheet" : "sheets"}
          </span>
          <Button onClick={() => window.print()} disabled={totalLabels === 0}>
            <Printer className="h-4 w-4" />
            Print
          </Button>
        </div>
      </div>

      <div data-print-hide className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_320px]">
        {/* ---- queue ---- */}
        <div className="space-y-4">
          <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)] p-4">
            <FieldLabel>Add a product</FieldLabel>
            <Combobox<LabelProduct>
              endpoint="/products"
              queryKey="label-products"
              value={undefined}
              onChange={(_id, option) => addProduct(option)}
              label={(product) => product.name}
              hint={(product) => product.barcode ?? product.sku ?? "no barcode"}
              placeholder="Search products by name, SKU or barcode…"
              emptyLabel="No products match"
              clearOnSelect
            />

            <div className="mt-4">
              <p className="mb-2 text-xs font-medium uppercase tracking-wide text-[var(--muted)]">
                Recently added products
              </p>
              {recentLoading ? (
                <div className="flex flex-wrap gap-2">
                  {Array.from({ length: 4 }).map((_, index) => (
                    <Skeleton key={index} className="h-8 w-32" />
                  ))}
                </div>
              ) : recentError ? (
                <div className="flex items-center gap-3 text-sm text-[var(--color-danger)]">
                  <span>Could not load products.</span>
                  <Button variant="outline" size="sm" onClick={() => refetchRecent()}>
                    <RotateCw className="h-3.5 w-3.5" />
                    Retry
                  </Button>
                </div>
              ) : (recent?.data.length ?? 0) === 0 ? (
                <p className="text-sm text-[var(--muted)]">No products yet.</p>
              ) : (
                <div className="flex flex-wrap gap-2">
                  {recent?.data.map((product) => (
                    <button
                      key={product.id}
                      type="button"
                      onClick={() => addProduct(product)}
                      className="inline-flex cursor-pointer items-center gap-1.5 rounded-full border border-[var(--border)] bg-[var(--surface-2)] px-3 py-1.5 text-xs transition-colors hover:border-[var(--primary)]"
                    >
                      <Plus className="h-3 w-3" />
                      <span className="max-w-40 truncate">{product.name}</span>
                    </button>
                  ))}
                </div>
              )}
            </div>
          </div>

          <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]">
            {rows.length === 0 ? (
              <p className="px-4 py-10 text-center text-sm text-[var(--muted)]">
                No products queued yet — search above to add the first one.
              </p>
            ) : (
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-[var(--border)] text-xs uppercase tracking-wide text-[var(--muted)]">
                    <th className="px-4 py-2 text-start font-medium">Product</th>
                    <th className="px-4 py-2 text-start font-medium">Barcode</th>
                    <th className="px-4 py-2 text-end font-medium">Price</th>
                    <th className="px-4 py-2 text-end font-medium">Labels</th>
                    <th className="w-10 px-4 py-2" />
                  </tr>
                </thead>
                <tbody>
                  {rows.map((row) => (
                    <tr key={row.product.id} className="border-b border-[var(--border)] last:border-0">
                      <td className="px-4 py-2">
                        <div className="font-medium">{row.product.name}</div>
                        {row.product.sku && (
                          <div className="font-mono text-xs text-[var(--muted)]">{row.product.sku}</div>
                        )}
                      </td>
                      <td className="px-4 py-2 font-mono text-xs">
                        {row.product.barcode ? (
                          row.product.barcode
                        ) : row.product.sku ? (
                          <span className="text-[var(--muted)]">SKU fallback</span>
                        ) : (
                          <span className="text-[var(--color-danger)]">none</span>
                        )}
                      </td>
                      <td className="px-4 py-2 text-end">{formatCurrency(row.product.selling_price)}</td>
                      <td className="px-4 py-2 text-end">
                        <Input
                          type="number"
                          min={1}
                          max={MAX_QUANTITY}
                          value={row.quantity}
                          onChange={(event) => setQuantity(row.product.id, Number(event.target.value))}
                          aria-label={`Labels for ${row.product.name}`}
                          className="ms-auto h-8 w-20 text-end"
                        />
                      </td>
                      <td className="px-4 py-2">
                        <Button
                          variant="ghost"
                          size="icon"
                          className="h-8 w-8 text-[var(--color-danger)]"
                          onClick={() => removeRow(row.product.id)}
                          aria-label={`Remove ${row.product.name}`}
                        >
                          <Trash2 className="h-3.5 w-3.5" />
                        </Button>
                      </td>
                    </tr>
                  ))}
                </tbody>
                <tfoot>
                  <tr className="border-t border-[var(--border)]">
                    <td colSpan={3} className="px-4 py-2 text-xs uppercase tracking-wide text-[var(--muted)]">
                      Total
                    </td>
                    <td className="px-4 py-2 text-end font-medium">{totalLabels}</td>
                    <td className="px-4 py-2 text-end">
                      <Button variant="ghost" size="sm" onClick={() => setRows([])}>
                        Clear
                      </Button>
                    </td>
                  </tr>
                </tfoot>
              </table>
            )}
          </div>
        </div>

        {/* ---- sheet & template settings ---- */}
        <div className="space-y-4 rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)] p-4">
          <div>
            <FieldLabel>Label stock</FieldLabel>
            <Select value={sheetId} onChange={(event) => setSheetId(event.target.value)}>
              {SHEET_PRESETS.map((option) => (
                <option key={option.id} value={option.id}>
                  {option.name}
                </option>
              ))}
            </Select>
            <p className="mt-1 text-xs text-[var(--muted)]">{preset.description}</p>
          </div>

          <div>
            <FieldLabel>Template</FieldLabel>
            <Select
              value={template}
              onChange={(event) => setTemplate(event.target.value as LabelTemplateId)}
            >
              {LABEL_TEMPLATES.map((option) => (
                <option key={option.id} value={option.id}>
                  {option.name}
                </option>
              ))}
            </Select>
            <p className="mt-1 text-xs text-[var(--muted)]">
              {LABEL_TEMPLATES.find((option) => option.id === template)?.description}
            </p>
          </div>

          <div>
            <FieldLabel>Skip used labels on the first sheet</FieldLabel>
            <Input
              type="number"
              min={0}
              max={perSheet - 1}
              value={offset}
              onChange={(event) => setStartOffset(Number(event.target.value) || 0)}
            />
            <p className="mt-1 text-xs text-[var(--muted)]">
              0–{perSheet - 1}. Printing starts at label {offset + 1} of the first sheet.
            </p>
          </div>

          <fieldset className="space-y-2">
            <legend className="mb-1 text-xs font-medium uppercase tracking-wide text-[var(--muted)]">
              Show on label
            </legend>
            {[
              { label: "Product name", checked: showName, set: setShowName },
              { label: "Price", checked: showPrice, set: setShowPrice },
              { label: "Cut outlines", checked: showOutline, set: setShowOutline },
            ].map((toggle) => (
              <label key={toggle.label} className="flex cursor-pointer items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  checked={toggle.checked}
                  onChange={(event) => toggle.set(event.target.checked)}
                  className="h-4 w-4 accent-[var(--primary)]"
                />
                {toggle.label}
              </label>
            ))}
          </fieldset>

          <div>
            <FieldLabel>Preview zoom</FieldLabel>
            <Select value={zoom} onChange={(event) => setZoom(event.target.value)}>
              {ZOOMS.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </Select>
            <p className="mt-1 text-xs text-[var(--muted)]">
              Zoom only affects the screen. Print at 100% scale with default margins.
            </p>
          </div>

          <dl className="space-y-1 border-t border-[var(--border)] pt-3 text-xs text-[var(--muted)]">
            <div className="flex justify-between">
              <dt>Label size</dt>
              <dd>
                {preset.labelWidthMm} × {preset.labelHeightMm} mm
              </dd>
            </div>
            <div className="flex justify-between">
              <dt>Per sheet</dt>
              <dd>{perSheet}</dd>
            </div>
            <div className="flex justify-between">
              <dt>Narrowest bar</dt>
              <dd>{narrowest ? `${narrowest.toFixed(2)} mm` : "—"}</dd>
            </div>
          </dl>
        </div>
      </div>

      {(missingBarcode > 0 ||
        (narrowest !== null && narrowest < MIN_MODULE_MM) ||
        totalLabels > BUSY_LABEL_COUNT) && (
        <div
          data-print-hide
          className="flex flex-col gap-1 rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface-2)] p-3 text-sm"
        >
          {missingBarcode > 0 && (
            <p className="flex items-center gap-2">
              <TriangleAlert className="h-4 w-4 shrink-0 text-[var(--color-danger)]" />
              {missingBarcode} queued {missingBarcode === 1 ? "product has" : "products have"} no
              barcode and no SKU — those labels print without bars.
            </p>
          )}
          {narrowest !== null && narrowest < MIN_MODULE_MM && (
            <p className="flex items-center gap-2">
              <TriangleAlert className="h-4 w-4 shrink-0 text-[var(--color-danger)]" />
              Bars are only {narrowest.toFixed(2)} mm wide — under {MIN_MODULE_MM} mm many scanners
              misread. Use a larger label or a shorter barcode value.
            </p>
          )}
          {totalLabels > BUSY_LABEL_COUNT && (
            <p className="flex items-center gap-2">
              <TriangleAlert className="h-4 w-4 shrink-0" />
              {totalLabels} labels queued — the preview may take a moment to render.
            </p>
          )}
        </div>
      )}

      {/* ---- preview / print surface ---- */}
      {totalLabels === 0 ? (
        <div
          data-print-hide
          className="rounded-[var(--radius)] border border-dashed border-[var(--border)] py-16 text-center text-sm text-[var(--muted)]"
        >
          Add a product to see the sheet preview.
        </div>
      ) : (
        <div
          data-label-preview
          className="overflow-x-auto rounded-[var(--radius)] bg-[var(--surface-2)] p-4"
        >
          <div
            style={{
              width: `${PAGE_WIDTH_MM * scale}mm`,
              height: `${previewHeightMm * scale}mm`,
            }}
          >
            <div data-label-preview-scale style={{ "--label-scale": scale } as React.CSSProperties}>
              {sheets.map((slots, index) => (
                <div
                  key={index}
                  data-label-page
                  className="shadow-sm"
                  style={{
                    width: `${PAGE_WIDTH_MM}mm`,
                    height: `${PAGE_HEIGHT_MM}mm`,
                    padding: `${preset.marginTopMm}mm ${preset.marginLeftMm}mm`,
                    marginBottom: `${PREVIEW_GAP_MM}mm`,
                    boxSizing: "border-box",
                    background: "#ffffff",
                  }}
                >
                  <LabelSheet
                    preset={preset}
                    slots={slots}
                    options={options}
                    pageBreakAfter={index < sheets.length - 1}
                  />
                </div>
              ))}
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
