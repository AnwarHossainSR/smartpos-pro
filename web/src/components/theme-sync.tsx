import { useEffect, useRef } from "react";
import { toast } from "@/components/ui/toast";
import { useTranslation } from "@/i18n/context";
import { api } from "@/lib/api";
import { queryClient } from "@/lib/query-client";
import { queryKeys } from "@/lib/query-keys";
import { useAuth } from "@/stores/auth";
import { useThemeStore } from "@/stores/theme";

/**
 * Wait this long after the *last* change before persisting, so a user cycling through the
 * palette swatches produces one request instead of one per click.
 */
const SYNC_DEBOUNCE_MS = 800;

/**
 * Keeps three things in step: the DOM (theme classes + palette attribute), localStorage
 * (via the store's `persist`, the offline / pre-auth fallback), and the signed-in user's
 * account or tenant.
 *
 * Two preferences now live in two different places:
 *  - `mode` (light/dark/system) is personal, kept on the user via `PATCH /auth/preferences`.
 *  - `palette` (the colour scheme) is a brand-level choice, kept on the business via
 *    `PUT /settings/business` so every member of the tenant shares it.
 *
 * Mounted inside `AppShell`, so it only ever runs for an authenticated user.
 */
export function ThemeSync() {
  const t = useTranslation();
  const theme = useThemeStore((s) => s.theme);
  const palette = useThemeStore((s) => s.palette);
  const modeDirty = useThemeStore((s) => s.modeDirty);
  const paletteDirty = useThemeStore((s) => s.paletteDirty);
  const applyServerTheme = useThemeStore((s) => s.applyServerTheme);
  const markModePushed = useThemeStore((s) => s.markModePushed);
  const markPalettePushed = useThemeStore((s) => s.markPalettePushed);
  const token = useAuth((s) => s.token);
  const userId = useAuth((s) => s.user?.id);
  // Selected as primitives so a fresh `me` payload with unchanged values doesn't re-render.
  const serverMode = useAuth((s) => s.user?.theme?.mode);

  useEffect(() => {
    const root = document.documentElement;
    root.classList.remove("light", "dark");
    if (theme === "light") root.classList.add("light");
    else if (theme === "dark") root.classList.add("dark");
  }, [theme]);

  // The palette attribute is tenant-owned; the effective value lives in the theme store and
  // is hydrated from `/settings/business` by `useSyncBusinessSettings`.
  useEffect(() => {
    document.documentElement.setAttribute("data-theme", palette);
  }, [palette]);

  // ---- Hydrate mode from the account -------------------------------------------------
  // `AppShell` already fetches `auth/me` and pushes it into the auth store; we read the
  // mode back out of it rather than issuing a second request. Applying it writes through
  // to localStorage, so the pre-paint script in `index.html` has the right value on the
  // next load and the user never sees the wrong theme flash in.
  const appliedRef = useRef<string | null>(null);

  useEffect(() => {
    if (!userId) return;

    const key = `${userId}|${serverMode ?? ""}`;
    if (appliedRef.current === key) return;

    // An unpushed local change wins over a possibly-stale read of the account (a `me`
    // refetch can land while our own PATCH is still in flight).
    if (modeDirty) return;

    appliedRef.current = key;
    applyServerTheme(serverMode ? { mode: serverMode } : null);
  }, [userId, serverMode, applyServerTheme, modeDirty]);

  // ---- Push local mode back (per-user) ------------------------------------------------
  // Re-running on every mode change restarts the timer, which is what makes this a
  // debounce. On failure `modeDirty` stays set, so the next change retries; the choice is
  // still applied locally either way, which is what the toast says.
  useEffect(() => {
    if (!modeDirty || !token) return;

    const timer = setTimeout(() => {
      api("/auth/preferences", { method: "PATCH", body: { theme: { mode: theme } } })
        .then(() => markModePushed())
        .catch(() => toast(t("theme.syncFailed"), "error"));
    }, SYNC_DEBOUNCE_MS);

    return () => clearTimeout(timer);
  }, [modeDirty, theme, token, markModePushed, t]);

  // ---- Push local palette back (tenant) ----------------------------------------------
  // A palette change is a business-level setting, so it is persisted through
  // `/settings/business`. The shared `businessSettings` cache is invalidated on success so
  // every reader of the business document (settings page, the sync hook, onboarding)
  // converges on the new value.
  useEffect(() => {
    if (!paletteDirty || !token) return;

    const timer = setTimeout(() => {
      api("/settings/business", { method: "PUT", body: { theme: { palette: palette } } })
        .then(() => {
          markPalettePushed();
          queryClient.invalidateQueries({ queryKey: queryKeys.businessSettings });
        })
        .catch(() => toast(t("theme.syncFailed"), "error"));
    }, SYNC_DEBOUNCE_MS);

    return () => clearTimeout(timer);
  }, [paletteDirty, palette, token, markPalettePushed, t]);

  return null;
}
