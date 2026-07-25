"use client";

import { useEffect } from "react";
import { usePathname, useRouter } from "next/navigation";
import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";
import { useAuth, type AuthUser } from "@/stores/auth";
import { Sidebar } from "@/components/layout/sidebar";
import { Header } from "@/components/layout/header";
import { Spinner } from "@/components/ui/spinner";

const titles: Record<string, string> = {
  "/dashboard": "Dashboard",
  "/pos": "POS Terminal",
  "/products": "Products",
  "/inventory": "Inventory",
  "/sales": "Sales",
  "/customers": "Customers",
  "/suppliers": "Suppliers",
  "/expenses": "Expenses",
  "/reports": "Reports",
  "/settings": "Settings",
};

export default function AppLayout({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const pathname = usePathname();
  const token = useAuth((s) => s.token);
  const setUser = useAuth((s) => s.setUser);

  useEffect(() => {
    if (!token) router.replace("/login");
  }, [token, router]);

  // Refresh the current user + permissions on mount.
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
    <div className="flex min-h-screen">
      <Sidebar />
      <div className="flex min-w-0 flex-1 flex-col">
        <Header title={title} />
        <main className="flex-1 overflow-auto p-6">{children}</main>
      </div>
    </div>
  );
}
