"use client";

import { useEffect, useState } from "react";
import { usePathname, useRouter } from "next/navigation";
import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";
import { useAuth, type AuthUser } from "@/stores/auth";
import { Sidebar } from "@/components/layout/sidebar";
import { Header } from "@/components/layout/header";
import { ThemeSync } from "@/components/theme-sync";
import { Spinner } from "@/components/ui/spinner";
import { useTranslation } from "@/i18n/provider";

const titleKeys: Record<string, string> = {
  "/dashboard": "nav.dashboard",
  "/pos": "nav.pos",
  "/products": "nav.products",
  "/inventory": "nav.inventory",
  "/sales": "nav.sales",
  "/purchases": "nav.purchases",
  "/customers": "nav.customers",
  "/suppliers": "nav.suppliers",
  "/expenses": "nav.expenses",
  "/reports": "nav.reports",
  "/accounting": "nav.accounting",
  "/cash-register": "nav.cashRegister",
  "/team": "nav.team",
  "/loyalty": "nav.loyalty",
  "/subscription": "nav.subscription",
  "/notifications": "nav.notifications",
  "/settings": "nav.settings",
};

export default function AppLayout({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const pathname = usePathname();
  const t = useTranslation();
  const token = useAuth((s) => s.token);
  const hydrated = useAuth((s) => s.hydrated);
  const setUser = useAuth((s) => s.setUser);
  const [sidebarOpen, setSidebarOpen] = useState(false);

  // Wait for the persisted token to load before deciding to bounce to /login,
  // otherwise a refresh logs the user out.
  useEffect(() => {
    if (hydrated && !token) router.replace("/login");
  }, [hydrated, token, router]);

  // Close mobile sidebar on route change
  useEffect(() => {
    setSidebarOpen(false);
  }, [pathname]);

  const { data } = useQuery({
    queryKey: ["me"],
    queryFn: () => api<{ data: AuthUser }>("/auth/me"),
    enabled: !!token,
  });

  useEffect(() => {
    if (data?.data) setUser(data.data);
  }, [data, setUser]);

  if (!hydrated || !token) {
    return (
      <div className="flex min-h-screen items-center justify-center">
        <Spinner className="h-6 w-6 text-[var(--primary)]" />
      </div>
    );
  }

  const titleKey = Object.entries(titleKeys).find(([href]) => pathname.startsWith(href))?.[1];
  const title = titleKey ? t(titleKey) : "SmartPOS";

  return (
    <>
      <ThemeSync />
      <div className="flex min-h-screen">
        <Sidebar open={sidebarOpen} onClose={() => setSidebarOpen(false)} />
        <div className="flex min-w-0 flex-1 flex-col">
          <Header title={title} onMenuClick={() => setSidebarOpen(true)} />
          <main className="flex-1 overflow-auto p-4 md:p-6">{children}</main>
        </div>
      </div>
    </>
  );
}
