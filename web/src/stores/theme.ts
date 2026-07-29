"use client";

import { create } from "zustand";
import { persist } from "zustand/middleware";
import { DEFAULT_PALETTE, type Palette } from "@/lib/palettes";

export type Theme = "light" | "dark" | "system";
export const THEME_STORAGE_KEY = "smartpos-theme";

interface ThemeState {
  theme: Theme;
  palette: Palette;
  setTheme: (theme: Theme) => void;
  setPalette: (palette: Palette) => void;
}

export const useThemeStore = create<ThemeState>()(
  persist(
    (set) => ({
      theme: "system",
      palette: DEFAULT_PALETTE,
      setTheme: (theme) => set({ theme }),
      setPalette: (palette) => set({ palette }),
    }),
    { name: THEME_STORAGE_KEY },
  ),
);
