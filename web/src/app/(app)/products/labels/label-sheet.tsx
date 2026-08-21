"use client";

import { useMemo } from "react";
import {
  encodeBarcode,
  layoutBarcode,
  barcodeValueFor,
  type EncodedBarcode,
} from "@/lib/barcode";
import { formatCurrency } from "@/lib/utils";
import {
  labelsPerSheet,
  sheetContentWidthMm,
  unitLabel,
  type LabelProduct,
  type LabelTemplateId,
  type SheetPreset,
} from "./label-templates";

/** Everything below is sized in millimetres so what prints measures what it says. */
const mm = (value: number) => `${Math.round(value * 1000) / 1000}mm`;

interface BarcodeProps {
  code: EncodedBarcode;
  widthMm: number;
  heightMm: number;
  showText?: boolean;
  textMm?: number;
}

/** Renders an encoded symbol as bars positioned in millimetre user units. */
export function BarcodeImage({ code, widthMm, heightMm, showText = true, textMm }: BarcodeProps) {
  const layout = useMemo(
    () => layoutBarcode(code, { widthMm, heightMm, showText, textMm }),
    [code, widthMm, heightMm, showText, textMm],
  );

  return (
    <svg
      width={mm(widthMm)}
      height={mm(heightMm)}
      viewBox={`0 0 ${widthMm} ${heightMm}`}
      shapeRendering="crispEdges"
      role="img"
      aria-label={`Barcode ${code.text}`}
      style={{ display: "block" }}
    >
      {layout.bars.map((bar, index) => (
        <rect
          key={index}
          x={bar.x}
          y={0}
          width={bar.widthMm}
          height={bar.heightMm}
          fill="#000000"
        />
      ))}
      {layout.texts.map((run, index) => (
        <text
          key={index}
          x={run.x}
          y={run.y}
          fontSize={run.sizeMm}
          textAnchor={run.anchor}
          fontFamily="ui-monospace, 'SFMono-Regular', Menlo, monospace"
          fill="#000000"
        >
          {run.text}
        </text>
      ))}
    </svg>
  );
}

export interface LabelOptions {
  template: LabelTemplateId;
  showName: boolean;
  showPrice: boolean;
  showOutline: boolean;
}

interface LabelProps {
  product: LabelProduct;
  preset: SheetPreset;
  options: LabelOptions;
}

/** One physical label. Fonts and paddings scale off the label's own height. */
export function Label({ product, preset, options }: LabelProps) {
  const width = preset.labelWidthMm;
  const height = preset.labelHeightMm;
  const pad = Math.max(1.2, height * 0.055);
  const inner = width - pad * 2;

  const code = useMemo(() => encodeBarcode(barcodeValueFor(product)), [product]);

  const nameSize = Math.min(3.4, Math.max(1.9, height * 0.085));
  const metaSize = Math.min(2.6, Math.max(1.5, height * 0.062));
  const priceSize =
    options.template === "shelf-label"
      ? Math.min(11, Math.max(4, height * 0.26))
      : Math.min(6.5, Math.max(3, height * 0.17));

  const barcodeHeight =
    options.template === "barcode-only"
      ? height * 0.72
      : options.template === "shelf-label"
        ? Math.max(8, height * 0.3)
        : Math.max(8, height * 0.4);

  const unit = unitLabel(product);
  const price = formatCurrency(product.selling_price);

  return (
    <div
      data-label
      style={{
        width: mm(width),
        height: mm(height),
        padding: mm(pad),
        boxSizing: "border-box",
        overflow: "hidden",
        display: "flex",
        flexDirection: "column",
        gap: mm(pad * 0.35),
        justifyContent: "space-between",
        color: "#000000",
        background: "#ffffff",
        outline: options.showOutline ? "0.15mm dashed #b0b0b0" : undefined,
        outlineOffset: "-0.15mm",
        breakInside: "avoid",
      }}
    >
      {options.template === "shelf-label" ? (
        <>
          {options.showName && (
            <div
              style={{
                fontSize: mm(nameSize),
                fontWeight: 600,
                lineHeight: 1.15,
                display: "-webkit-box",
                WebkitLineClamp: 2,
                WebkitBoxOrient: "vertical",
                overflow: "hidden",
              }}
            >
              {product.name}
            </div>
          )}
          <div style={{ display: "flex", alignItems: "baseline", gap: mm(pad), flex: "1 1 auto" }}>
            {options.showPrice && (
              <div style={{ fontSize: mm(priceSize), fontWeight: 700, lineHeight: 1 }}>{price}</div>
            )}
            {unit && (
              <div style={{ fontSize: mm(metaSize), lineHeight: 1.1, opacity: 0.75 }}>{unit}</div>
            )}
          </div>
          <BarcodeArea
            code={code}
            widthMm={inner}
            heightMm={barcodeHeight}
            fallbackSize={metaSize}
          />
        </>
      ) : options.template === "barcode-only" ? (
        <>
          {options.showName && (
            <div
              style={{
                fontSize: mm(metaSize),
                lineHeight: 1.1,
                whiteSpace: "nowrap",
                overflow: "hidden",
                textOverflow: "ellipsis",
              }}
            >
              {product.name}
            </div>
          )}
          <div style={{ flex: "1 1 auto", display: "flex", alignItems: "center" }}>
            <BarcodeArea
              code={code}
              widthMm={inner}
              heightMm={barcodeHeight}
              fallbackSize={metaSize}
            />
          </div>
        </>
      ) : (
        <>
          {options.showName && (
            <div
              style={{
                fontSize: mm(nameSize),
                fontWeight: 600,
                lineHeight: 1.15,
                display: "-webkit-box",
                WebkitLineClamp: 2,
                WebkitBoxOrient: "vertical",
                overflow: "hidden",
              }}
            >
              {product.name}
            </div>
          )}
          {options.showPrice && (
            <div
              style={{
                fontSize: mm(priceSize),
                fontWeight: 700,
                lineHeight: 1,
                whiteSpace: "nowrap",
              }}
            >
              {price}
            </div>
          )}
          <BarcodeArea
            code={code}
            widthMm={inner}
            heightMm={barcodeHeight}
            fallbackSize={metaSize}
          />
        </>
      )}
    </div>
  );
}

function BarcodeArea({
  code,
  widthMm,
  heightMm,
  fallbackSize,
}: {
  code: EncodedBarcode | null;
  widthMm: number;
  heightMm: number;
  fallbackSize: number;
}) {
  if (!code) {
    return (
      <div
        style={{
          height: mm(heightMm),
          fontSize: mm(fallbackSize),
          display: "flex",
          alignItems: "center",
          opacity: 0.6,
        }}
      >
        No barcode or SKU
      </div>
    );
  }
  return <BarcodeImage code={code} widthMm={widthMm} heightMm={heightMm} />;
}

interface SheetProps {
  preset: SheetPreset;
  /** One entry per physical label on this sheet; `null` marks an already-used slot. */
  slots: (LabelProduct | null)[];
  options: LabelOptions;
  /** True for every sheet but the last, which must not force a trailing blank page. */
  pageBreakAfter: boolean;
}

/** One A4 sheet of labels. */
export function LabelSheet({ preset, slots, options, pageBreakAfter }: SheetProps) {
  return (
    <div
      data-label-sheet
      style={{
        display: "grid",
        gridTemplateColumns: `repeat(${preset.columns}, ${mm(preset.labelWidthMm)})`,
        gridAutoRows: mm(preset.labelHeightMm),
        columnGap: mm(preset.gapXMm),
        rowGap: mm(preset.gapYMm),
        width: mm(sheetContentWidthMm(preset)),
        background: "#ffffff",
        breakAfter: pageBreakAfter ? "page" : "auto",
        printColorAdjust: "exact",
        WebkitPrintColorAdjust: "exact",
      }}
    >
      {slots.map((product, index) =>
        product ? (
          <Label key={index} product={product} preset={preset} options={options} />
        ) : (
          <div
            key={index}
            aria-hidden
            style={{
              width: mm(preset.labelWidthMm),
              height: mm(preset.labelHeightMm),
              outline: options.showOutline ? "0.15mm dashed #e0e0e0" : undefined,
              outlineOffset: "-0.15mm",
            }}
          />
        ),
      )}
    </div>
  );
}

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
    template === "barcode-only" ? preset.labelHeightMm * 0.72 : Math.max(8, preset.labelHeightMm * 0.3);

  let narrowest: number | null = null;
  for (const product of labels) {
    const code = encodeBarcode(barcodeValueFor(product));
    if (!code) continue;
    const { moduleMm } = layoutBarcode(code, { widthMm: inner, heightMm });
    if (narrowest === null || moduleMm < narrowest) narrowest = moduleMm;
  }
  return narrowest;
}
