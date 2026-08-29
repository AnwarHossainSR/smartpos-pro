import { create } from "zustand";
import { persist } from "zustand/middleware";
import { DEFAULT_PALETTE, isPalette, type Palette } from "@/lib/palettes";

export const THEME_MODES = ["light", "dark", "system"] as const;

export type Theme = (typeof THEME_MODES)[number];
export const THEME_STORAGE_KEY = "smartpos-theme";

/** Wire shape of `users.theme` — mode only. The colour palette is tenant-owned
 *  (served by `GET /settings/business`) rather than a per-user preference. */
export interface ServerTheme {
  mode: Theme;
}

export function isThemeMode(value: unknown): value is Theme {
  return (THEME_MODES as readonly unknown[]).includes(value);
}

interface ThemeState {
  theme: Theme;
  palette: Palette;
  /** A local mode change the API has not been told about yet (see `ThemeSync`). */
  modeDirty: boolean;
  /** A local palette change the API has not been told about yet (see `ThemeSync`). */
  paletteDirty: boolean;
  setTheme: (theme: Theme) => void;
  setPalette: (palette: Palette) => void;
  /**
   * Adopt the mode stored on the signed-in user's account, from the `auth/me` payload.
   *
   * A user who has never picked one comes back as `null`: rather than resetting them to the
   * defaults, we keep whatever this device is already showing and mark it dirty, so their
   * existing local preference is what gets saved to the account.
   */
  applyServerTheme: (theme: ServerTheme | null | undefined) => void;
  /**
   * Adopt the tenant's palette, from the `/settings/business` payload.
   *
   * Falls back to `DEFAULT_PALETTE` when the tenant has none — but a local, unpushed choice
   * wins, so a `me` refetch landing while a palette PATCH is still in flight cannot revert it.
   */
  applyPalette: (palette: Palette | null | undefined) => void;
  /** Called once a mode push to `/auth/preferences` has succeeded. */
  markModePushed: () => void;
  /** Called once a palette push to `/settings/business` has succeeded. */
  markPalettePushed: () => void;
}

export const useThemeStore = create<ThemeState>()(
  persist(
    (set) => ({
      theme: "system",
      palette: DEFAULT_PALETTE,
      modeDirty: false,
      paletteDirty: false,
      setTheme: (theme) => set({ theme, modeDirty: true }),
      setPalette: (palette) => set({ palette, paletteDirty: true }),
      applyServerTheme: (theme) => {
        if (!theme || !isThemeMode(theme.mode)) {
          set({ modeDirty: true });
          return;
        }

        set({ theme: theme.mode, modeDirty: false });
      },
      applyPalette: (palette) => {
        if (!palette || !isPalette(palette)) {
          set({ paletteDirty: true });
          return;
        }

        set({ palette, paletteDirty: false });
      },
      markModePushed: () => set({ modeDirty: false }),
      markPalettePushed: () => set({ paletteDirty: false }),
    }),
    {
      name: THEME_STORAGE_KEY,
      // localStorage stays the offline / pre-auth source of truth: it is what the inline
      // script in `index.html` reads before first paint, so the account's theme (once
      // hydrated here) is already applied on the *next* load with no flash.
      //
      // The dirty flags are per-session bookkeeping rather than a preference — persisting
      // them would make every reload re-push a value the server already has.
      partialize: (state: ThemeState) => ({ theme: state.theme, palette: state.palette }),
    },
  ),
);
