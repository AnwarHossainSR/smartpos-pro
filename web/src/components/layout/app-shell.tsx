import { useEffect, useState } from "react";
import { useNavigate, useLocation, Outlet } from "react-router";
import { useQuery } from "@tanstack/react-query";
import { ShieldAlert } from "lucide-react";
import { api, ApiError } from "@/lib/api";
import { useAuth, type AuthUser } from "@/stores/auth";
import { useLocaleStore } from "@/stores/locale";
import { useBusinessStore } from "@/stores/business";
import { useSyncBusinessCurrency } from "@/lib/hooks";
import { navKeyForPathname } from "@/lib/nav";
import { permissionsForPathname } from "@/lib/permissions";
import { RequirePermission } from "@/components/require-permission";
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

function ProfileUnavailableScreen() {
  const logout = useAuth((s) => s.logout);
  return (
    <div className="flex min-h-screen flex-col items-center justify-center gap-4 p-6 text-center">
      <ShieldAlert className="h-10 w-10 text-[var(--color-danger)]" />
      <h1 className="text-lg font-semibold">Could not load your profile</h1>
      <p className="max-w-sm text-sm text-[var(--muted)]">
        We could not reach the server to confirm who you are. Check your connection and reload,
        or sign in again.
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

  if (error && !user) {
    return <ProfileUnavailableScreen />;
  }

  const navKey = navKeyForPathname(pathname);
  const title = navKey ? t(`nav.${navKey}`) : "SmartPOS";

  return (
    <>
      <ThemeSync />
      <OnboardingWizard />
      <div className="flex h-dvh overflow-hidden">
        <Sidebar open={sidebarOpen} onClose={() => setSidebarOpen(false)} />
        <div className="flex min-w-0 flex-1 flex-col overflow-hidden">
          <Header title={title} onMenuClick={() => setSidebarOpen(true)} />
          <VerifyEmailBanner />
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
