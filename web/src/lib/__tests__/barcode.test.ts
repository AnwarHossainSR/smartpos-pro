import { describe, expect, it } from "vitest";
import {
  BarcodeError,
  barcodeSvg,
  barcodeValueFor,
  code128Checksum,
  code128Pattern,
  code128Values,
  ean13CheckDigit,
  encodeBarcode,
  encodeCode128,
  encodeEan13,
  isValidEan13,
  layoutBarcode,
} from "@/lib/barcode";

/* The Code 128 symbols the spec pins down by name — if the table drifted, these move. */
const START_A = "11010000100";
const START_B = "11010010000";
const START_C = "11010011100";
const STOP = "1100011101011";

describe("code 128 symbol table", () => {
  it("has 107 rows that are all 11 modules wide with even bar parity", () => {
    for (let value = 0; value <= 106; value++) {
      const bits = code128Pattern(value);
      expect(bits.length, `value ${value}`).toBe(11);
      // Every Code 128 symbol is three bars and three spaces, and the bar modules always
      // sum to an even number — the property the symbology is self-checking on.
      const barModules = bits.split("").filter((bit) => bit === "1").length;
      expect(barModules % 2, `value ${value} bar parity`).toBe(0);
      expect(bits.startsWith("1"), `value ${value} starts with a bar`).toBe(true);
      expect(bits.endsWith("0"), `value ${value} ends with a space`).toBe(true);
    }
    expect(() => code128Pattern(107)).toThrow(BarcodeError);
  });

  it("has no duplicate symbols", () => {
    const seen = new Set<string>();
    for (let value = 0; value <= 106; value++) seen.add(code128Pattern(value));
    expect(seen.size).toBe(107);
  });

  it("matches the documented start and stop patterns", () => {
    expect(code128Pattern(0)).toBe("11011001100");
    expect(code128Pattern(103)).toBe(START_A);
    expect(code128Pattern(104)).toBe(START_B);
    expect(code128Pattern(105)).toBe(START_C);
    expect(code128Pattern(106)).toBe("1100011101011".slice(0, 11));
  });
});

describe("encodeCode128", () => {
  it('encodes "AB" in set B, bit for bit', () => {
    // Start B, "A" (value 33), "B" (value 34), checksum 102, stop + termination bar.
    const expected = START_B + "10100011000" + "10001011000" + "11110101110" + STOP;
    const encoded = encodeCode128("AB");
    expect(encoded.symbology).toBe("code128");
    expect(encoded.modules).toBe(expected);
    expect(encoded.modules.length).toBe(4 * 11 + 13);
    expect(encoded.text).toBe("AB");
    expect(encoded.quietLeft).toBe(10);
    expect(encoded.quietRight).toBe(10);
  });

  it('encodes "123456" in set C, bit for bit', () => {
    // Start C, 12, 34, 56, checksum 44, stop.
    const expected = START_C + "10110011100" + "10001011000" + "11100010110" + "10001101110" + STOP;
    expect(encodeCode128("123456").modules).toBe(expected);
  });

  it("computes the modulo-103 checksum from the start code and positional weights", () => {
    // Start B(104) + 1×33 + 2×34 = 205, 205 mod 103 = 102.
    expect(code128Checksum([104, 33, 34])).toBe(102);
    // Start C(105) + 1×12 + 2×34 + 3×56 = 353, 353 mod 103 = 44.
    expect(code128Checksum([105, 12, 34, 56])).toBe(44);
    expect(code128Checksum([103])).toBe(103 % 103);
  });

  it("starts in set C for an even run of leading digits", () => {
    expect(code128Values("123456")).toEqual([105, 12, 34, 56]);
    expect(code128Values("12345678")[0]).toBe(105);
  });

  it("starts in set B for text and switches to C for a long digit run", () => {
    // Start B, "A", Code C, then digit pairs.
    expect(code128Values("A12345678")).toEqual([104, 33, 99, 12, 34, 56, 78]);
  });

  it("leaves set C when the digits run out", () => {
    // Start C, 12, 34, Code B, "X".
    expect(code128Values("1234X")).toEqual([105, 12, 34, 100, 56]);
  });

  it("switches to set A for control characters", () => {
    const values = code128Values("A\tB");
    expect(values[0]).toBe(104); // Start B
    expect(values).toContain(101); // Code A switch
    expect(values.at(-1)).toBe(34); // "B" in set A is still 34
  });

  it("keeps an odd leading digit in set B before switching", () => {
    // "1" in set B is value 17, then Code C for the even remainder.
    expect(code128Values("1234567890")).toEqual([105, 12, 34, 56, 78, 90]);
    expect(code128Values("123456789")).toEqual([104, 17, 99, 23, 45, 67, 89]);
  });

  it("rejects non-ASCII input", () => {
    expect(() => encodeCode128("café")).toThrow(BarcodeError);
    expect(() => encodeCode128("")).toThrow(BarcodeError);
  });
});

describe("EAN-13 tables", () => {
  // R is the bitwise complement of L, and G is R reversed. Re-deriving the three tables from
  // one another catches a transcription slip in any of the thirty patterns.
  const L = [
    "0001101", "0011001", "0010011", "0111101", "0100011",
    "0110001", "0101111", "0111011", "0110111", "0001011",
  ];

  it("derives the encoder's own G and R patterns from the L patterns", () => {
    for (let digit = 0; digit <= 9; digit++) {
      const d = String(digit).repeat(11);
      const r = L[digit].split("").map((bit) => (bit === "1" ? "0" : "1")).join("");
      const g = r.split("").reverse().join("");

      // Lead digit 0 means parity LLLLLL, so the first left-hand group is the L pattern and
      // the first right-hand group (module 50) is the R pattern.
      const allL = encodeEan13(`0${d}`).modules;
      expect(allL.slice(3, 10), `L${digit}`).toBe(L[digit]);
      expect(allL.slice(50, 57), `R${digit}`).toBe(r);

      // Lead digit 5 means parity LGGLLG, so the *second* left-hand group is the G pattern.
      const withG = encodeEan13(`5${d}`).modules;
      expect(withG.slice(10, 17), `G${digit}`).toBe(g);
    }
  });

  it("applies the parity table for the lead digit", () => {
    // Lead digit 7 is LGLGLG: groups 1, 3 and 5 are L, groups 2, 4 and 6 are G.
    const modules = encodeEan13("7111111111111".slice(0, 12)).modules;
    const group = (n: number) => modules.slice(3 + n * 7, 10 + n * 7);
    const l1 = L[1];
    const r1 = l1.split("").map((bit) => (bit === "1" ? "0" : "1")).join("");
    const g1 = r1.split("").reverse().join("");
    expect([group(0), group(1), group(2), group(3), group(4), group(5)]).toEqual([
      l1, g1, l1, g1, l1, g1,
    ]);
  });
});

describe("encodeEan13", () => {
  it("computes the modulo-10 check digit", () => {
    expect(ean13CheckDigit("590123412345")).toBe(7);
    expect(ean13CheckDigit("400638133393")).toBe(1);
    expect(ean13CheckDigit("978020137962")).toBe(4);
    expect(ean13CheckDigit("000000000000")).toBe(0);
    expect(() => ean13CheckDigit("12345")).toThrow(BarcodeError);
  });

  it("validates check digits", () => {
    expect(isValidEan13("5901234123457")).toBe(true);
    expect(isValidEan13("5901234123456")).toBe(false);
    expect(isValidEan13("590123412345")).toBe(false);
    expect(isValidEan13("59012341234A7")).toBe(false);
  });

  it("encodes 5901234123457 bit for bit", () => {
    // 101 | 9L 0G 1G 2L 3L 4G | 01010 | 1R 2R 3R 4R 5R 7R | 101 — parity LGGLLG for lead digit 5.
    const expected =
      "101" +
      "0001011" + "0100111" + "0110011" + "0010011" + "0111101" + "0011101" +
      "01010" +
      "1100110" + "1101100" + "1000010" + "1011100" + "1001110" + "1000100" +
      "101";
    const encoded = encodeEan13("5901234123457");
    expect(encoded.modules).toBe(expected);
    expect(encoded.modules.length).toBe(95);
    expect(encoded.symbology).toBe("ean13");
    expect(encoded.value).toBe("5901234123457");
  });

  it("appends the check digit when given twelve digits", () => {
    const twelve = encodeEan13("590123412345");
    expect(twelve.value).toBe("5901234123457");
    expect(twelve.modules).toBe(encodeEan13("5901234123457").modules);
  });

  it("keeps the guard bars where the spec puts them", () => {
    const { modules } = encodeEan13("4006381333931");
    expect(modules.slice(0, 3)).toBe("101");
    expect(modules.slice(45, 50)).toBe("01010");
    expect(modules.slice(92, 95)).toBe("101");
    expect(modules.length).toBe(95);
  });

  it("asks for the quiet zones EAN-13 requires", () => {
    const encoded = encodeEan13("5901234123457");
    expect(encoded.quietLeft).toBe(11);
    expect(encoded.quietRight).toBe(7);
  });

  it("rejects malformed input", () => {
    expect(() => encodeEan13("5901234123456")).toThrow(BarcodeError);
    expect(() => encodeEan13("59012341")).toThrow(BarcodeError);
  });
});

describe("encodeBarcode", () => {
  it("picks EAN-13 for 12 and 13 digit values", () => {
    expect(encodeBarcode("5901234123457")?.symbology).toBe("ean13");
    expect(encodeBarcode("590123412345")?.symbology).toBe("ean13");
    expect(encodeBarcode("590123412345")?.value).toBe("5901234123457");
  });

  it("falls back to Code 128 (with a warning) for a bad EAN-13 check digit", () => {
    const encoded = encodeBarcode("5901234123456");
    expect(encoded?.symbology).toBe("code128");
    expect(encoded?.warning).toMatch(/check digit/i);
  });

  it("picks Code 128 for anything else", () => {
    expect(encodeBarcode("SKU-0042")?.symbology).toBe("code128");
    expect(encodeBarcode("12345")?.symbology).toBe("code128");
    expect(encodeBarcode("12345678901234")?.symbology).toBe("code128");
  });

  it("trims and returns null when there is nothing to print", () => {
    expect(encodeBarcode("  5901234123457  ")?.value).toBe("5901234123457");
    expect(encodeBarcode("")).toBeNull();
    expect(encodeBarcode("   ")).toBeNull();
    expect(encodeBarcode(null)).toBeNull();
    expect(encodeBarcode(undefined)).toBeNull();
    expect(encodeBarcode("café")).toBeNull();
  });
});

describe("barcodeValueFor", () => {
  it("prefers the barcode, falls back to the SKU, then gives up", () => {
    expect(barcodeValueFor({ barcode: "5901234123457", sku: "SKU-1" })).toBe("5901234123457");
    expect(barcodeValueFor({ barcode: null, sku: "SKU-1" })).toBe("SKU-1");
    expect(barcodeValueFor({ barcode: "  ", sku: " SKU-2 " })).toBe("SKU-2");
    expect(barcodeValueFor({ barcode: null, sku: null })).toBeNull();
    expect(barcodeValueFor({})).toBeNull();
  });

  it("encodes a SKU fallback as Code 128", () => {
    const value = barcodeValueFor({ barcode: null, sku: "TSHIRT-BLK-M" });
    expect(encodeBarcode(value)?.symbology).toBe("code128");
    expect(encodeBarcode(value)?.text).toBe("TSHIRT-BLK-M");
  });
});

describe("layoutBarcode", () => {
  const ean = encodeEan13("5901234123457");

  it("fills the requested physical width, quiet zones included", () => {
    const layout = layoutBarcode(ean, { widthMm: 37.29, heightMm: 12 });
    const totalModules = 11 + 95 + 7;
    expect(layout.moduleMm).toBeCloseTo(37.29 / totalModules, 6);
    expect(layout.widthMm).toBe(37.29);
    const last = layout.bars[layout.bars.length - 1];
    // The right quiet zone stays empty.
    expect(last.x + last.widthMm).toBeLessThanOrEqual(37.29 - 7 * layout.moduleMm + 1e-9);
    expect(layout.bars[0].x).toBeCloseTo(11 * layout.moduleMm, 9);
  });

  it("prints guard bars longer than data bars and keeps everything inside the box", () => {
    const layout = layoutBarcode(ean, { widthMm: 40, heightMm: 14 });
    const guard = layout.bars[0].heightMm;
    const data = layout.bars[3].heightMm;
    expect(guard).toBeGreaterThan(data);
    for (const bar of layout.bars) {
      expect(bar.heightMm).toBeLessThanOrEqual(14);
      expect(bar.x + bar.widthMm).toBeLessThanOrEqual(40 + 1e-9);
    }
  });

  it("splits the human-readable digits the way EAN-13 does", () => {
    const layout = layoutBarcode(ean, { widthMm: 40, heightMm: 14 });
    expect(layout.texts.map((t) => t.text)).toEqual(["5", "901234", "123457"]);
    expect(layout.texts.every((t) => t.y <= 14)).toBe(true);
  });

  it("centres a single caption under a Code 128 symbol, and can omit it", () => {
    const code = encodeCode128("SKU-0042");
    const layout = layoutBarcode(code, { widthMm: 50, heightMm: 12 });
    expect(layout.texts).toHaveLength(1);
    expect(layout.texts[0].text).toBe("SKU-0042");
    expect(layout.texts[0].x).toBe(25);
    expect(layoutBarcode(code, { widthMm: 50, heightMm: 12, showText: false }).texts).toHaveLength(0);
  });

  it("reports the module width so callers can refuse to print an unscannable symbol", () => {
    expect(layoutBarcode(ean, { widthMm: 10, heightMm: 8 }).moduleMm).toBeLessThan(0.25);
    expect(layoutBarcode(ean, { widthMm: 45, heightMm: 8 }).moduleMm).toBeGreaterThan(0.25);
  });
});

describe("barcodeSvg", () => {
  it("emits an SVG sized in millimetres with black bars", () => {
    const svg = barcodeSvg(encodeEan13("5901234123457"), { widthMm: 37.3, heightMm: 12 });
    expect(svg).toContain('width="37.3mm"');
    expect(svg).toContain('height="12mm"');
    expect(svg).toContain('viewBox="0 0 37.3 12"');
    expect(svg).toContain('shape-rendering="crispEdges"');
    expect(svg).toContain('fill="#000"');
    expect(svg).toContain(">901234<");
  });

  it("escapes text that would otherwise break the markup", () => {
    const svg = barcodeSvg(encodeCode128("A&B<C"), { widthMm: 50, heightMm: 12 });
    expect(svg).toContain("A&amp;B&lt;C");
  });
});

/* ---------------------------------------------------------------------------
   Round trip: decode the module string back the way a scanner does. Encoding is only
   worth anything if a reader gets the original value out again, so these walk the bars
   with an independent decoder rather than re-using the encoder's own logic.
   --------------------------------------------------------------------------- */

const EAN_L_TABLE = [
  "0001101", "0011001", "0010011", "0111101", "0100011",
  "0110001", "0101111", "0111011", "0110111", "0001011",
];
const EAN_R_TABLE = EAN_L_TABLE.map((bits) =>
  bits.split("").map((bit) => (bit === "1" ? "0" : "1")).join(""),
);
const EAN_G_TABLE = EAN_R_TABLE.map((bits) => bits.split("").reverse().join(""));
const EAN_PARITY_TABLE = [
  "LLLLLL", "LLGLGG", "LLGGLG", "LLGGGL", "LGLLGG",
  "LGGLLG", "LGGGLL", "LGLGLG", "LGLGGL", "LGGLGL",
];

function decodeEan13(modules: string): string {
  if (modules.length !== 95) throw new Error("wrong length");
  if (modules.slice(0, 3) !== "101") throw new Error("bad start guard");
  if (modules.slice(45, 50) !== "01010") throw new Error("bad centre guard");
  if (modules.slice(92) !== "101") throw new Error("bad end guard");

  let parity = "";
  let digits = "";
  for (let i = 0; i < 6; i++) {
    const chunk = modules.slice(3 + i * 7, 10 + i * 7);
    const asL = EAN_L_TABLE.indexOf(chunk);
    const asG = EAN_G_TABLE.indexOf(chunk);
    if (asL >= 0) {
      parity += "L";
      digits += asL;
    } else if (asG >= 0) {
      parity += "G";
      digits += asG;
    } else {
      throw new Error(`undecodable left chunk ${chunk}`);
    }
  }

  const first = EAN_PARITY_TABLE.indexOf(parity);
  if (first < 0) throw new Error(`unknown parity ${parity}`);

  let right = "";
  for (let i = 0; i < 6; i++) {
    const chunk = modules.slice(50 + i * 7, 57 + i * 7);
    const value = EAN_R_TABLE.indexOf(chunk);
    if (value < 0) throw new Error(`undecodable right chunk ${chunk}`);
    right += value;
  }

  const value = `${first}${digits}${right}`;
  if (!isValidEan13(value)) throw new Error("check digit failed");
  return value;
}

function decodeCode128(modules: string): string {
  const lookup = new Map<string, number>();
  for (let value = 0; value <= 106; value++) lookup.set(code128Pattern(value), value);

  if (!modules.endsWith(`${code128Pattern(106)}11`)) throw new Error("missing stop");
  const body = modules.slice(0, -13);
  if (body.length % 11 !== 0) throw new Error("ragged symbol");

  const values: number[] = [];
  for (let i = 0; i < body.length; i += 11) {
    const value = lookup.get(body.slice(i, i + 11));
    if (value === undefined) throw new Error(`undecodable symbol at ${i}`);
    values.push(value);
  }

  const checksum = values.pop();
  if (checksum !== code128Checksum(values)) throw new Error("checksum failed");

  const start = values.shift();
  let mode = start === 103 ? "A" : start === 104 ? "B" : start === 105 ? "C" : null;
  if (!mode) throw new Error("no start code");

  let text = "";
  for (const value of values) {
    if (value === 99 && mode !== "C") {
      mode = "C";
      continue;
    }
    if (value === 100 && mode !== "B") {
      mode = "B";
      continue;
    }
    if (value === 101 && mode !== "A") {
      mode = "A";
      continue;
    }
    if (mode === "C") {
      text += String(value).padStart(2, "0");
    } else if (mode === "B") {
      text += String.fromCharCode(value + 32);
    } else {
      text += value < 64 ? String.fromCharCode(value + 32) : String.fromCharCode(value - 64);
    }
  }
  return text;
}

describe("round trip", () => {
  it("decodes every EAN-13 back to the value that was encoded", () => {
    for (const value of [
      "5901234123457",
      "4006381333931",
      "9780201379624",
      "0000000000000",
      "1234567890128",
    ]) {
      expect(decodeEan13(encodeEan13(value).modules)).toBe(value);
    }
  });

  it("decodes Code 128 back through every mode switch", () => {
    for (const value of [
      "AB",
      "123456",
      "SKU-0042",
      "A12345678",
      "1234X",
      "abc-123456789-XYZ",
      "0",
      "The quick brown fox 42",
    ]) {
      expect(decodeCode128(encodeCode128(value).modules)).toBe(value);
    }
  });

  it("decodes what encodeBarcode() picked, whichever symbology that was", () => {
    const ean = encodeBarcode("5901234123457");
    expect(ean && decodeEan13(ean.modules)).toBe("5901234123457");

    const fallback = encodeBarcode("5901234123456");
    expect(fallback && decodeCode128(fallback.modules)).toBe("5901234123456");

    const sku = encodeBarcode(barcodeValueFor({ barcode: null, sku: "TSHIRT-BLK-M" }));
    expect(sku && decodeCode128(sku.modules)).toBe("TSHIRT-BLK-M");
  });
});
