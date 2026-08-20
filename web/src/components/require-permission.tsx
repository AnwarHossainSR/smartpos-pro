"use client";

import Link from "next/link";
import { ShieldOff } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Spinner } from "@/components/ui/spinner";
import { useTranslation } from "@/i18n/provider";
import { hasPermission, useAuth } from "@/stores/auth";
import type { PermissionSlug } from "@/lib/permissions";

/**
 * The single permission guard for the app. Every route under `(app)` goes through it via
 * `AppShell` (which reads the requirement from `permissionsForPathname`); pages and panels
 * that need a finer-grained gate can wrap a subtree with it directly.
 *
 * The subtlety is the hydration race. `useAuth().can()` reads `user`, and `user` is null on
 * the very first client render — the token cookie has not been read back and `/auth/me` has
 * not resolved. A guard that just called `can()` would deny an authorised user for a frame
 * and, if it redirected, bounce them off the page they asked for. So: nothing is decided
 * until `hydrated` is true *and* a user object exists (persisted or freshly fetched).
 *
 * Denial renders in place rather than redirecting. A redirect races with whatever the user
 * typed in the URL bar and hides the reason; more importantly, rendering a panel instead of
 * `children` means the page's queries never mount, so a user without `expenses.view` never
 * fires the request that would 403.
 */
export function RequirePermission({
  any,
  children,
  fallback,
}: {
  /** Any one of these grants access. An empty list means "any signed-in user". */
  any: readonly PermissionSlug[];
  children: React.ReactNode;
  /** Rendered instead of the default panel when access is denied. */
  fallback?: React.ReactNode;
}) {
  const hydrated = useAuth((s) => s.hydrated);
  const user = useAuth((s) => s.user);

  if (!hydrated || !user) {
    return (
      <div className="flex min-h-[40vh] items-center justify-center" role="status" aria-live="polite">
        <Spinner className="h-6 w-6 text-[var(--primary)]" />
        <span className="sr-only">Loading</span>
      </div>
    );
  }

  const allowed = any.length === 0 || any.some((permission) => hasPermission(user, permission));
  if (allowed) return <>{children}</>;

  return <>{fallback ?? <AccessDenied />}</>;
}

export function AccessDenied() {
  const t = useTranslation();

  return (
    <div className="flex min-h-[50vh] flex-col items-center justify-center gap-3 px-6 text-center">
      <ShieldOff className="h-9 w-9 text-[var(--muted)]" aria-hidden="true" />
      <h1 className="text-base font-semibold">{t("common.accessDenied")}</h1>
      <p className="max-w-sm text-sm text-[var(--muted)]">{t("common.accessDeniedBody")}</p>
      <Button asChild variant="outline" size="sm">
        <Link href="/dashboard">{t("common.backToDashboard")}</Link>
      </Button>
    </div>
  );
}
