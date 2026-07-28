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

const titles: Record<string, string> = {
  "/dashboard": "Dashboard",
  "/pos": "POS Terminal",
  "/products": "Products",
  "/inventory": "Inventory",
  "/sales": "Sales",
  "/purchases": "Purchases",
  "/customers": "Customers",
  "/suppliers": "Suppliers",
  "/expenses": "Expenses",
  "/reports": "Reports",
  "/accounting": "Accounting",
  "/cash-register": "Cash Register",
  "/team": "Team",
  "/loyalty": "Loyalty & Coupons",
  "/subscription": "Subscription",
  "/notifications": "Notifications",
  "/settings": "Settings",
};

export default function AppLayout({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const pathname = usePathname();
  const token = useAuth((s) => s.token);
  const setUser = useAuth((s) => s.setUser);
  const [sidebarOpen, setSidebarOpen] = useState(false);

  useEffect(() => {
    if (!token) router.replace("/login");
  }, [token, router]);

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

  if (!token) {
    return (
      <div className="flex min-h-screen items-center justify-center">
        <Spinner className="h-6 w-6 text-[var(--primary)]" />
      </div>
    );
  }

  const title =
    Object.entries(titles).find(([href]) => pathname.startsWith(href))?.[1] ?? "SmartPOS";

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
