/**
 * Client-side barcode encoding — Code 128 (auto A/B/C) and EAN-13.
 *
 * Written in-repo rather than pulled from npm: the label printer needs exact control over
 * module geometry (bars are laid out in millimetres so a printed sheet is physically to
 * spec) and the encoders are small enough to unit-test against known bit patterns.
 *
 * Nothing here touches the DOM, so it runs in tests and on the server as well as in the
 * browser. `layoutBarcode()` returns plain geometry that React can render as <rect>s;
 * `barcodeSvg()` serialises the same geometry to a standalone SVG string.
 */

export type Symbology = "ean13" | "code128";

export interface EncodedBarcode {
  symbology: Symbology;
  /** The value actually encoded — for EAN-13 this includes the (computed) check digit. */
  value: string;
  /** Human-readable interpretation printed beneath the bars. */
  text: string;
  /** One character per module, "1" = bar, "0" = space. Excludes quiet zones. */
  modules: string;
  /** Quiet zone required left of the symbol, in modules. */
  quietLeft: number;
  /** Quiet zone required right of the symbol, in modules. */
  quietRight: number;
  /** Set when the input had to be re-interpreted (e.g. a bad EAN-13 check digit). */
  warning?: string;
}

export class BarcodeError extends Error {
  constructor(message: string) {
    super(message);
    this.name = "BarcodeError";
  }
}

/* ------------------------------------------------------------------ Code 128 */

/**
 * The canonical Code 128 symbol table, one row per value 0…106, each row six element
 * widths (bar, space, bar, space, bar, space) that always sum to 11 modules. Row 103/104/105
 * are Start A/B/C and 106 is the stop character (which carries two extra termination bars).
 */
const CODE128_WIDTHS = [
  "212222", "222122", "222221", "121223", "121322", "131222", "122213", "122312", "132212", "221213",
  "221312", "231212", "112232", "122132", "122231", "113222", "123122", "123221", "223211", "221132",
  "221231", "213212", "223112", "312131", "311222", "321122", "321221", "312212", "322112", "322211",
  "212123", "212321", "232121", "111323", "131123", "131321", "112313", "132113", "132311", "211313",
  "231113", "231311", "112133", "112331", "132131", "113123", "113321", "133121", "313121", "211331",
  "231131", "213113", "213311", "213131", "311123", "311321", "331121", "312113", "312311", "332111",
  "314111", "221411", "431111", "111224", "111422", "121124", "121421", "141122", "141221", "112214",
  "112412", "122114", "122411", "142112", "142211", "241211", "221114", "413111", "241112", "134111",
  "111242", "121142", "121241", "114212", "124112", "124211", "411212", "421112", "421211", "212141",
  "214121", "412121", "111143", "111341", "131141", "114113", "114311", "411113", "411311", "113141",
  "114131", "311141", "411131", "211412", "211214", "211232", "233111",
] as const;

const CODE_C = 99;
const CODE_B = 100;
const CODE_A = 101;
const START_A = 103;
const START_B = 104;
const START_C = 105;
const STOP = 106;

/** Code 128 needs ten quiet modules either side of the symbol. */
const CODE128_QUIET = 10;

/** Expands one table row into its module bits — widths alternate bar, space, bar, … */
export function code128Pattern(value: number): string {
  const widths = CODE128_WIDTHS[value];
  if (!widths) throw new BarcodeError(`No Code 128 pattern for value ${value}`);
  let bits = "";
  for (let i = 0; i < widths.length; i++) {
    bits += (i % 2 === 0 ? "1" : "0").repeat(Number(widths[i]));
  }
  return bits;
}

/** The stop character plus its two-module termination bar. */
const STOP_MODULES = `${code128Pattern(STOP)}11`;

type Code128Set = "A" | "B" | "C";

function isDigit(char: string | undefined): boolean {
  return char !== undefined && char >= "0" && char <= "9";
}

/** How many consecutive digits start at `index`. */
function digitRun(data: string, index: number): number {
  let n = 0;
  while (isDigit(data[index + n])) n += 1;
  return n;
}

function valueInA(char: string): number | null {
  const code = char.charCodeAt(0);
  if (code >= 32 && code <= 95) return code - 32;
  if (code < 32) return code + 64;
  return null;
}

function valueInB(char: string): number | null {
  const code = char.charCodeAt(0);
  if (code >= 32 && code <= 127) return code - 32;
  return null;
}

/**
 * Picks the code values for `data`, switching between sets A/B/C as the data warrants:
 * set C (two digits per symbol) for numeric runs, set B for printable ASCII and set A only
 * where control characters force it.
 */
export function code128Values(data: string): number[] {
  if (data.length === 0) throw new BarcodeError("Cannot encode an empty barcode");
  for (const char of data) {
    if (char.charCodeAt(0) > 127) {
      throw new BarcodeError(`Code 128 cannot encode "${char}" (non-ASCII)`);
    }
  }

  const codes: number[] = [];
  const lead = digitRun(data, 0);
  let mode: Code128Set;

  if (lead >= 4 && lead % 2 === 0) {
    mode = "C";
    codes.push(START_C);
  } else if (valueInB(data[0]) === null) {
    mode = "A";
    codes.push(START_A);
  } else {
    mode = "B";
    codes.push(START_B);
  }

  let i = 0;
  while (i < data.length) {
    const ahead = digitRun(data, i);

    if (mode === "C") {
      if (ahead >= 2) {
        codes.push(Number(data.slice(i, i + 2)));
        i += 2;
        continue;
      }
      // A single trailing digit or a non-digit: leave set C.
      const next = valueInB(data[i]) === null ? "A" : "B";
      codes.push(next === "A" ? CODE_A : CODE_B);
      mode = next;
      continue;
    }

    // In A or B: a long numeric run is worth switching to C for, but only on an even boundary.
    const atEnd = i + ahead === data.length;
    if ((ahead >= 6 || (ahead >= 4 && atEnd)) && ahead % 2 === 0) {
      codes.push(CODE_C);
      mode = "C";
      continue;
    }

    const char = data[i];
    const value = mode === "A" ? valueInA(char) : valueInB(char);
    if (value === null) {
      // The character isn't in the active set (a control char in B, or lowercase in A).
      const next: Code128Set = mode === "A" ? "B" : "A";
      codes.push(next === "A" ? CODE_A : CODE_B);
      mode = next;
      continue;
    }

    codes.push(value);
    i += 1;
  }

  return codes;
}

/** Modulo-103 weighted checksum: start value + Σ (position × value). */
export function code128Checksum(codes: number[]): number {
  return codes.reduce((sum, value, index) => sum + value * (index === 0 ? 1 : index), 0) % 103;
}

export function encodeCode128(data: string): EncodedBarcode {
  const codes = code128Values(data);
  codes.push(code128Checksum(codes));

  const modules = codes.map(code128Pattern).join("") + STOP_MODULES;

  return {
    symbology: "code128",
    value: data,
    text: data,
    modules,
    quietLeft: CODE128_QUIET,
    quietRight: CODE128_QUIET,
  };
}

/* -------------------------------------------------------------------- EAN-13 */

// Left-hand odd parity (L), left-hand even parity (G) and right-hand (R) digit patterns.
const EAN_L = [
  "0001101", "0011001", "0010011", "0111101", "0100011",
  "0110001", "0101111", "0111011", "0110111", "0001011",
] as const;
const EAN_G = [
  "0100111", "0110011", "0011011", "0100001", "0011101",
  "0111001", "0000101", "0010001", "0001001", "0010111",
] as const;
const EAN_R = [
  "1110010", "1100110", "1101100", "1000010", "1011100",
  "1001110", "1010000", "1000100", "1001000", "1110100",
] as const;

/** Which of the first six digits use even (G) parity, keyed by the leading digit. */
const EAN_PARITY = [
  "LLLLLL", "LLGLGG", "LLGGLG", "LLGGGL", "LGLLGG",
  "LGGLLG", "LGGGLL", "LGLGLG", "LGLGGL", "LGGLGL",
] as const;

const EAN_START_GUARD = "101";
const EAN_CENTRE_GUARD = "01010";
const EAN_END_GUARD = "101";

/** Module ranges (relative to the symbol, quiet zone excluded) that print as tall guard bars. */
export const EAN13_GUARDS: ReadonlyArray<readonly [number, number]> = [
  [0, 3],
  [45, 50],
  [92, 95],
];

/** Weighted (1,3,1,3…) modulo-10 check digit for the first twelve digits of an EAN-13. */
export function ean13CheckDigit(twelveDigits: string): number {
  if (!/^\d{12}$/.test(twelveDigits)) {
    throw new BarcodeError("EAN-13 check digit needs exactly 12 digits");
  }
  let sum = 0;
  for (let i = 0; i < 12; i++) {
    sum += Number(twelveDigits[i]) * (i % 2 === 0 ? 1 : 3);
  }
  return (10 - (sum % 10)) % 10;
}

export function isValidEan13(value: string): boolean {
  return /^\d{13}$/.test(value) && ean13CheckDigit(value.slice(0, 12)) === Number(value[12]);
}

/** Encodes 12 digits (check digit computed) or 13 digits (check digit verified). */
export function encodeEan13(input: string): EncodedBarcode {
  let value: string;
  if (/^\d{12}$/.test(input)) {
    value = input + String(ean13CheckDigit(input));
  } else if (/^\d{13}$/.test(input)) {
    if (!isValidEan13(input)) throw new BarcodeError(`"${input}" has an invalid EAN-13 check digit`);
    value = input;
  } else {
    throw new BarcodeError("EAN-13 needs 12 or 13 digits");
  }

  const parity = EAN_PARITY[Number(value[0])];
  let modules = EAN_START_GUARD;
  for (let i = 0; i < 6; i++) {
    const digit = Number(value[i + 1]);
    modules += parity[i] === "L" ? EAN_L[digit] : EAN_G[digit];
  }
  modules += EAN_CENTRE_GUARD;
  for (let i = 7; i < 13; i++) {
    modules += EAN_R[Number(value[i])];
  }
  modules += EAN_END_GUARD;

  return {
    symbology: "ean13",
    value,
    text: value,
    modules,
    // EAN-13 asks for 11 modules of quiet zone on the left (the lead digit sits there) and 7 right.
    quietLeft: 11,
    quietRight: 7,
  };
}

/* ------------------------------------------------------------------- dispatch */

/**
 * Chooses a symbology from the raw value: 12–13 numeric digits become an EAN-13, anything
 * else Code 128. A 13-digit value whose check digit doesn't validate falls back to Code 128
 * (with a warning) rather than silently printing a different number than the one on file.
 * Returns `null` when there is nothing printable.
 */
export function encodeBarcode(raw: string | null | undefined): EncodedBarcode | null {
  const value = (raw ?? "").trim();
  if (!value) return null;

  if (/^\d{12}$/.test(value)) return encodeEan13(value);

  if (/^\d{13}$/.test(value)) {
    if (isValidEan13(value)) return encodeEan13(value);
    try {
      return {
        ...encodeCode128(value),
        warning: "Invalid EAN-13 check digit — printed as Code 128",
      };
    } catch {
      return null;
    }
  }

  try {
    return encodeCode128(value);
  } catch {
    return null;
  }
}

/** The value a product's label should carry: its barcode, else its SKU. */
export function barcodeValueFor(product: {
  barcode?: string | null;
  sku?: string | null;
}): string | null {
  const barcode = product.barcode?.trim();
  if (barcode) return barcode;
  const sku = product.sku?.trim();
  return sku ? sku : null;
}

/* --------------------------------------------------------------- mm geometry */

export interface BarcodeBar {
  /** Millimetres from the left edge of the drawing (quiet zone included). */
  x: number;
  widthMm: number;
  heightMm: number;
}

export interface BarcodeTextRun {
  text: string;
  /** Millimetres from the left edge of the drawing. */
  x: number;
  /** Baseline, in millimetres from the top. */
  y: number;
  anchor: "start" | "middle" | "end";
  sizeMm: number;
}

export interface BarcodeLayout {
  widthMm: number;
  heightMm: number;
  /** Width of a single module — below ~0.25mm most scanners start to struggle. */
  moduleMm: number;
  bars: BarcodeBar[];
  texts: BarcodeTextRun[];
}

export interface LayoutOptions {
  /** Total drawing width including quiet zones. */
  widthMm: number;
  /** Total drawing height including the human-readable line. */
  heightMm: number;
  /** Print the human-readable digits under the bars (default true). */
  showText?: boolean;
  /** Font size for the human-readable line; defaults to a fraction of the height. */
  textMm?: number;
}

/** The narrowest module width scanners handle reliably in practice. */
export const MIN_MODULE_MM = 0.25;

function guardRangesFor(code: EncodedBarcode): ReadonlyArray<readonly [number, number]> {
  return code.symbology === "ean13" ? EAN13_GUARDS : [];
}

/**
 * Lays a symbol out in millimetres so the caller can render it at a guaranteed physical
 * size — bars are positioned in mm user units inside a mm-sized viewBox, which is what
 * makes a printed label measure what it claims to.
 */
export function layoutBarcode(code: EncodedBarcode, options: LayoutOptions): BarcodeLayout {
  const { widthMm, heightMm } = options;
  const showText = options.showText ?? true;
  const textMm = options.textMm ?? Math.min(2.6, Math.max(1.6, heightMm * 0.24));

  const totalModules = code.quietLeft + code.modules.length + code.quietRight;
  const moduleMm = widthMm / totalModules;

  const textBand = showText ? textMm * 1.2 : 0;
  const barHeight = Math.max(heightMm - textBand, heightMm * 0.4);
  // EAN guard bars drop into the text band, between the two digit groups.
  const guardHeight = showText && code.symbology === "ean13" ? barHeight + textMm * 0.65 : barHeight;

  const guards = guardRangesFor(code);
  const bars: BarcodeBar[] = [];

  let index = 0;
  while (index < code.modules.length) {
    if (code.modules[index] !== "1") {
      index += 1;
      continue;
    }
    let run = 1;
    while (code.modules[index + run] === "1") run += 1;
    const isGuard = guards.some(([from, to]) => index >= from && index < to);
    bars.push({
      x: (code.quietLeft + index) * moduleMm,
      widthMm: run * moduleMm,
      heightMm: isGuard ? guardHeight : barHeight,
    });
    index += run;
  }

  const texts: BarcodeTextRun[] = [];
  if (showText) {
    const baseline = heightMm - textMm * 0.15;
    if (code.symbology === "ean13") {
      texts.push(
        { text: code.value[0], x: moduleMm * 1, y: baseline, anchor: "start", sizeMm: textMm },
        {
          text: code.value.slice(1, 7),
          x: (code.quietLeft + 24) * moduleMm,
          y: baseline,
          anchor: "middle",
          sizeMm: textMm,
        },
        {
          text: code.value.slice(7),
          x: (code.quietLeft + 71) * moduleMm,
          y: baseline,
          anchor: "middle",
          sizeMm: textMm,
        },
      );
    } else {
      texts.push({
        text: code.text,
        x: widthMm / 2,
        y: baseline,
        anchor: "middle",
        sizeMm: textMm,
      });
    }
  }

  return { widthMm, heightMm, moduleMm, bars, texts };
}

function escapeXml(value: string): string {
  return value
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}

const round = (value: number): string => String(Math.round(value * 1000) / 1000);

/** Serialises a layout to a standalone SVG sized in millimetres. */
export function barcodeSvg(code: EncodedBarcode, options: LayoutOptions): string {
  const layout = layoutBarcode(code, options);
  const rects = layout.bars
    .map(
      (bar) =>
        `<rect x="${round(bar.x)}" y="0" width="${round(bar.widthMm)}" height="${round(bar.heightMm)}" fill="#000"/>`,
    )
    .join("");
  const texts = layout.texts
    .map(
      (run) =>
        `<text x="${round(run.x)}" y="${round(run.y)}" font-size="${round(run.sizeMm)}" text-anchor="${run.anchor}" font-family="monospace" fill="#000">${escapeXml(run.text)}</text>`,
    )
    .join("");

  return (
    `<svg xmlns="http://www.w3.org/2000/svg" width="${round(layout.widthMm)}mm" height="${round(layout.heightMm)}mm" ` +
    `viewBox="0 0 ${round(layout.widthMm)} ${round(layout.heightMm)}" shape-rendering="crispEdges">` +
    `<rect x="0" y="0" width="${round(layout.widthMm)}" height="${round(layout.heightMm)}" fill="#fff"/>` +
    `${rects}${texts}</svg>`
  );
}
