"use client";

import { useEffect, useState } from "react";
import { usePathname, useRouter } from "next/navigation";
import { useQuery } from "@tanstack/react-query";
import { ShieldAlert } from "lucide-react";
import { api, ApiError } from "@/lib/api";
import { useAuth, type AuthUser } from "@/stores/auth";
import { useLocaleStore } from "@/stores/locale";
import { useBusinessStore } from "@/stores/business";
import { useSyncBusinessCurrency } from "@/lib/hooks";
import { navKeyForPathname } from "@/lib/nav";
import { Sidebar } from "@/components/layout/sidebar";
import { Header } from "@/components/layout/header";
import { ThemeSync } from "@/components/theme-sync";
import { Spinner } from "@/components/ui/spinner";
import { Button } from "@/components/ui/button";
import { VerifyEmailBanner } from "@/components/verify-email-banner";
import { OnboardingWizard } from "@/features/onboarding";
import { useTranslation } from "@/i18n/provider";

function isTenantSuspendedError(error: unknown): boolean {
  if (!(error instanceof ApiError) || error.status !== 403) return false;
  const payload = error.payload as { code?: string } | null;
  return payload?.code === "tenant_suspended";
}

function SuspendedScreen() {
  const logout = useAuth((s) => s.logout);
  return (
    <div className="flex min-h-screen flex-col items-center justify-center gap-4 p-6 text-center">
      <ShieldAlert className="h-10 w-10 text-[var(--color-danger)]" />
      <h1 className="text-lg font-semibold">This account has been suspended</h1>
      <p className="max-w-sm text-sm text-[var(--muted)]">
        Access to this business has been suspended. Contact support if you believe this is a
        mistake.
      </p>
      <Button variant="outline" onClick={logout}>
        Log out
      </Button>
    </div>
  );
}

/** The authenticated app chrome (sidebar/header/guards) — split out from `(app)/layout.tsx` so
 *  that file can stay a Server Component and carry the `noindex` metadata every authenticated,
 *  per-tenant route needs (a client component can't export `metadata`). */
export function AppShell({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const pathname = usePathname();
  const t = useTranslation();
  const token = useAuth((s) => s.token);
  const hydrated = useAuth((s) => s.hydrated);
  const setUser = useAuth((s) => s.setUser);
  const locale = useLocaleStore((s) => s.locale);
  const currency = useBusinessStore((s) => s.currency);
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [prevPathname, setPrevPathname] = useState(pathname);

  // Wait for the persisted token to load before deciding to bounce to /login,
  // otherwise a refresh logs the user out.
  useEffect(() => {
    if (hydrated && !token) router.replace("/login");
  }, [hydrated, token, router]);

  // Close the mobile sidebar on route change. Adjusts state during render (React's documented
  // pattern for "derive state from a prop change") instead of an effect, since setState-in-effect
  // here would cause an extra render on every navigation for no benefit.
  if (pathname !== prevPathname) {
    setPrevPathname(pathname);
    setSidebarOpen(false);
  }

  const { data, error } = useQuery({
    queryKey: ["me"],
    queryFn: () => api<{ data: AuthUser }>("/auth/me"),
    enabled: !!token,
    retry: (failureCount, err) => !isTenantSuspendedError(err) && failureCount < 3,
  });

  useEffect(() => {
    if (data?.data) setUser(data.data);
  }, [data, setUser]);

  useSyncBusinessCurrency(!!token);

  if (!hydrated || !token) {
    return (
      <div className="flex min-h-screen items-center justify-center">
        <Spinner className="h-6 w-6 text-[var(--primary)]" />
      </div>
    );
  }

  if (isTenantSuspendedError(error)) {
    return <SuspendedScreen />;
  }

  const navKey = navKeyForPathname(pathname);
  const title = navKey ? t(`nav.${navKey}`) : "SmartPOS";

  return (
    <>
      <ThemeSync />
      <OnboardingWizard />
      {/* Fixed-height shell: the viewport itself never scrolls, so the sidebar and header stay put
          and <main> is the only scroll container. Pages can then size against `h-full` instead of
          re-deriving the header offset with calc(100vh - …). */}
      <div className="flex h-dvh overflow-hidden">
        <Sidebar open={sidebarOpen} onClose={() => setSidebarOpen(false)} />
        <div className="flex min-w-0 flex-1 flex-col overflow-hidden">
          <Header title={title} onMenuClick={() => setSidebarOpen(true)} />
          <VerifyEmailBanner />
          {/* Re-mounting on locale/currency change guarantees every formatCurrency()/formatNumber()
              call in the page tree picks up the new value — those helpers read the stores
              imperatively (via getState()), so pages that don't otherwise subscribe to either
              store would keep rendering stale numbers until an unrelated re-render happened. */}
          <main key={`${locale}:${currency}`} className="min-h-0 flex-1 overflow-y-auto p-4 md:p-6">
            {children}
          </main>
        </div>
      </div>
    </>
  );
}
