import { create } from "zustand";
import { persist } from "zustand/middleware";
import { DEFAULT_PALETTE, isPalette, type Palette } from "@/lib/palettes";

export const THEME_MODES = ["light", "dark", "system"] as const;

export type Theme = (typeof THEME_MODES)[number];
export const THEME_STORAGE_KEY = "smartpos-theme";

/** Wire shape of `users.theme` — mirrored server-side by `App\Support\Theme\ThemeCatalog`. */
export interface ServerTheme {
  mode: Theme;
  palette: Palette;
}

export function isThemeMode(value: unknown): value is Theme {
  return (THEME_MODES as readonly unknown[]).includes(value);
}

interface ThemeState {
  theme: Theme;
  palette: Palette;
  /** A local change the API has not been told about yet (see `ThemeSync`). */
  dirty: boolean;
  setTheme: (theme: Theme) => void;
  setPalette: (palette: Palette) => void;
  /**
   * Adopt the theme stored on the signed-in user's account, from the `auth/me` payload.
   *
   * A user who has never picked one comes back as `null`: rather than resetting them to the
   * defaults, we keep whatever this device is already showing and mark it dirty, so their
   * existing local preference is what gets saved to the account.
   */
  applyServerTheme: (theme: ServerTheme | null | undefined) => void;
  /** Called once a push to the API has succeeded. */
  markPushed: () => void;
}

export const useThemeStore = create<ThemeState>()(
  persist(
    (set) => ({
      theme: "system",
      palette: DEFAULT_PALETTE,
      dirty: false,
      setTheme: (theme) => set({ theme, dirty: true }),
      setPalette: (palette) => set({ palette, dirty: true }),
      applyServerTheme: (theme) => {
        if (!theme || !isThemeMode(theme.mode) || !isPalette(theme.palette)) {
          set({ dirty: true });
          return;
        }

        set({ theme: theme.mode, palette: theme.palette, dirty: false });
      },
      markPushed: () => set({ dirty: false }),
    }),
    {
      name: THEME_STORAGE_KEY,
      // localStorage stays the offline / pre-auth source of truth: it is what the inline
      // script in `app/layout.tsx` reads before first paint, so the account's theme (once
      // hydrated here) is already applied on the *next* load with no flash.
      //
      // `dirty` is per-session bookkeeping rather than a preference — persisting it would
      // make every reload re-push a value the server already has.
      partialize: (state: ThemeState) => ({ theme: state.theme, palette: state.palette }),
    },
  ),
);
