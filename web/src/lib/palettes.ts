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
