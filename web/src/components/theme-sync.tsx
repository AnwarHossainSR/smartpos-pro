"use client";

import { useEffect, useRef } from "react";
import { api } from "@/lib/api";
import { toast } from "@/components/ui/toast";
import { useAuth } from "@/stores/auth";
import { useThemeStore } from "@/stores/theme";
import { useTranslation } from "@/i18n/provider";

/**
 * Wait this long after the *last* change before persisting, so a user cycling through the
 * palette swatches produces one request instead of one per click.
 */
const SYNC_DEBOUNCE_MS = 800;

/**
 * Keeps three things in step: the DOM (theme classes + palette attribute), localStorage
 * (via the store's `persist`, the offline/pre-auth fallback), and the signed-in user's
 * account (`PATCH /auth/preferences`).
 *
 * Mounted inside `AppShell`, so it only ever runs for an authenticated user.
 */
export function ThemeSync() {
  const t = useTranslation();
  const theme = useThemeStore((s) => s.theme);
  const palette = useThemeStore((s) => s.palette);
  const dirty = useThemeStore((s) => s.dirty);
  const applyServerTheme = useThemeStore((s) => s.applyServerTheme);
  const markPushed = useThemeStore((s) => s.markPushed);
  const token = useAuth((s) => s.token);
  const userId = useAuth((s) => s.user?.id);
  // Selected as primitives so a fresh `me` payload with unchanged values doesn't re-render.
  const serverMode = useAuth((s) => s.user?.theme?.mode);
  const serverPalette = useAuth((s) => s.user?.theme?.palette);

  useEffect(() => {
    const root = document.documentElement;
    root.classList.remove("light", "dark");
    if (theme === "light") root.classList.add("light");
    else if (theme === "dark") root.classList.add("dark");
  }, [theme]);

  useEffect(() => {
    document.documentElement.setAttribute("data-theme", palette);
  }, [palette]);

  // ---- Hydrate from the account -------------------------------------------------------
  // `AppShell` already fetches `auth/me` and pushes it into the auth store; we read the
  // theme back out of it rather than issuing a second request. Applying it writes through
  // to localStorage, so the pre-paint script in `app/layout.tsx` has the right value on the
  // next load and the user never sees the wrong theme flash in.
  const appliedRef = useRef<string | null>(null);

  useEffect(() => {
    if (!userId) return;

    const key = `${userId}|${serverMode ?? ""}|${serverPalette ?? ""}`;
    if (appliedRef.current === key) return;

    // An unpushed local change wins over a possibly-stale read of the account (a `me`
    // refetch can land while our own PATCH is still in flight).
    if (useThemeStore.getState().dirty) return;

    appliedRef.current = key;
    applyServerTheme(serverMode && serverPalette ? { mode: serverMode, palette: serverPalette } : null);
  }, [userId, serverMode, serverPalette, applyServerTheme]);

  // ---- Push local changes back --------------------------------------------------------
  // Re-running on every theme/palette change restarts the timer, which is what makes this a
  // debounce. On failure `dirty` stays set, so the next change retries; the choice is still
  // applied locally either way, which is what the toast says.
  useEffect(() => {
    if (!dirty || !token) return;

    const timer = setTimeout(() => {
      api("/auth/preferences", { method: "PATCH", body: { theme: { mode: theme, palette } } })
        .then(() => markPushed())
        .catch(() => toast(t("theme.syncFailed"), "error"));
    }, SYNC_DEBOUNCE_MS);

    return () => clearTimeout(timer);
  }, [dirty, theme, palette, token, markPushed, t]);

  return null;
}
