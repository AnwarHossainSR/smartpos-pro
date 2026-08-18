export const PALETTES = [
  { id: "amber", label: "Amber Retail", swatch: "#ff9f43" },
  { id: "indigo", label: "Indigo", swatch: "#4f46e5" },
  { id: "emerald", label: "Emerald", swatch: "#047857" },
  { id: "ocean", label: "Ocean", swatch: "#0369a1" },
  { id: "violet", label: "Violet", swatch: "#7c3aed" },
  { id: "rose", label: "Rose", swatch: "#e11d48" },
] as const;

export type Palette = (typeof PALETTES)[number]["id"];

export const DEFAULT_PALETTE: Palette = "indigo";

/**
 * Narrows an untrusted value (an API payload, a stale localStorage entry) to a palette id.
 * The API validates the same list server-side — see `App\Support\Theme\ThemeCatalog`.
 */
export function isPalette(value: unknown): value is Palette {
  return PALETTES.some((p) => p.id === value);
}
