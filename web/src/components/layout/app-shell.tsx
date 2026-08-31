import { useQuery } from "@tanstack/react-query";
import { LogOut, ShieldAlert } from "lucide-react";
import { useEffect, useState } from "react";
import { Outlet, useLocation, useNavigate } from "react-router";
import { Header } from "@/components/layout/header";
import { Sidebar } from "@/components/layout/sidebar";
import { RequirePermission } from "@/components/require-permission";
import { ThemeSync } from "@/components/theme-sync";
import { Button } from "@/components/ui/button";
import { Spinner } from "@/components/ui/spinner";
import { VerifyEmailBanner } from "@/components/verify-email-banner";
import { OnboardingWizard } from "@/features/onboarding";
import { useTranslation } from "@/i18n/context";
import { ApiError, api } from "@/lib/api";
import { useSyncBusinessSettings } from "@/lib/hooks";
import { navKeyForPathname } from "@/lib/nav";
import { permissionsForPathname } from "@/lib/permissions";
import { queryKeys } from "@/lib/query-keys";
import { type AuthUser, useAuth } from "@/stores/auth";
import { useBusinessStore } from "@/stores/business";
import { useLocaleStore } from "@/stores/locale";
import { useModulesStore } from "@/stores/modules";

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

function ProfileUnavailableScreen() {
  const logout = useAuth((s) => s.logout);
  return (
    <div className="flex min-h-screen flex-col items-center justify-center gap-4 p-6 text-center">
      <ShieldAlert className="h-10 w-10 text-[var(--color-danger)]" />
      <h1 className="text-lg font-semibold">Could not load your profile</h1>
      <p className="max-w-sm text-sm text-[var(--muted)]">
        We could not reach the server to confirm who you are. Check your connection and reload, or
        sign in again.
      </p>
      <div className="flex gap-2">
        <Button variant="outline" onClick={() => window.location.reload()}>
          Reload
        </Button>
        <Button variant="outline" onClick={logout}>
          Log out
        </Button>
      </div>
    </div>
  );
}

function ImpersonationBanner() {
  const stopImpersonation = useAuth((s) => s.stopImpersonation);
  const user = useAuth((s) => s.user);

  // Only show when the impersonate cookie is active (i.e. this tab is impersonating)
  if (typeof document === "undefined") return null;
  const hasImpersonateCookie = document.cookie.includes("smartpos_impersonate_token=");
  if (!hasImpersonateCookie) return null;

  return (
    <div className="flex items-center justify-between gap-3 border-b border-purple-500/30 bg-purple-500/10 px-4 py-2 text-sm text-purple-700 dark:text-purple-300 md:px-6">
      <div className="flex items-center gap-2">
        <ShieldAlert className="h-4 w-4 shrink-0" />
        <span>
          Impersonating <strong>{user?.name ?? "a user"}</strong> ({user?.email})
        </span>
      </div>
      <Button variant="ghost" size="sm" onClick={stopImpersonation}>
        <LogOut className="mr-1.5 h-3.5 w-3.5" />
        Stop impersonation
      </Button>
    </div>
  );
}

export function AppShell() {
  const navigate = useNavigate();
  const { pathname } = useLocation();
  const t = useTranslation();
  const token = useAuth((s) => s.token);
  const user = useAuth((s) => s.user);
  const hydrated = useAuth((s) => s.hydrated);
  const setUser = useAuth((s) => s.setUser);
  const locale = useLocaleStore((s) => s.locale);
  const currency = useBusinessStore((s) => s.currency);
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [prevPathname, setPrevPathname] = useState(pathname);

  useEffect(() => {
    if (hydrated && !token) navigate("/login", { replace: true });
  }, [hydrated, token, navigate]);

  if (pathname !== prevPathname) {
    setPrevPathname(pathname);
    setSidebarOpen(false);
  }

  const { data, error } = useQuery({
    queryKey: queryKeys.me,
    queryFn: () => api<{ data: AuthUser }>("/auth/me"),
    enabled: !!token,
    retry: (failureCount, err) => !isTenantSuspendedError(err) && failureCount < 3,
  });

  useEffect(() => {
    if (data?.data) setUser(data.data);
  }, [data, setUser]);

  useSyncBusinessSettings(!!token);

  const fetchModules = useModulesStore((s) => s.fetchModules);
  useEffect(() => {
    fetchModules();
  }, [fetchModules]);

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

  if (error && !user) {
    return <ProfileUnavailableScreen />;
  }

  const navKey = navKeyForPathname(pathname);
  const title = navKey ? t(`nav.${navKey}`) : "SmartPOS";

  const authLoaded = !!user;

  return (
    <>
      <ThemeSync />
      <OnboardingWizard />
      <div className="flex h-dvh overflow-hidden">
        {authLoaded ? (
          <Sidebar open={sidebarOpen} onClose={() => setSidebarOpen(false)} />
        ) : (
          <aside className="hidden h-full w-64 shrink-0 bg-[var(--sidebar-bg)] md:block">
            <div className="flex h-full flex-col px-3 py-4">
              <div className="flex h-[72px] items-center gap-3 px-1">
                <div className="h-[34px] w-[34px] animate-pulse rounded-lg bg-white/10" />
                <div className="h-5 w-24 animate-pulse rounded bg-white/10" />
              </div>
              <div className="mx-4 border-b border-white/10" />
              <div className="space-y-1 px-0 py-4">
                {Array.from({ length: 8 }).map((_, i) => (
                  <div key={i} className="flex items-center gap-3 rounded-xl px-3 py-2.5">
                    <div className="h-[18px] w-[18px] animate-pulse rounded bg-white/10" />
                    <div
                      className="h-4 flex-1 animate-pulse rounded bg-white/10"
                      style={{ maxWidth: `${60 + (i % 3) * 15}%` }}
                    />
                  </div>
                ))}
              </div>
            </div>
          </aside>
        )}
        <div className="flex min-w-0 flex-1 flex-col overflow-hidden">
          <Header title={title} onMenuClick={() => setSidebarOpen(true)} />
          <VerifyEmailBanner />
          <ImpersonationBanner />
          <main key={`${locale}:${currency}`} className="min-h-0 flex-1 overflow-y-auto p-4 md:p-6">
            <RequirePermission any={permissionsForPathname(pathname)}>
              <Outlet />
            </RequirePermission>
          </main>
        </div>
      </div>
    </>
  );
}
