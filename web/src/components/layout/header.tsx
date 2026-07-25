"use client";

import { useRouter } from "next/navigation";
import { LogOut, Search } from "lucide-react";
import { api } from "@/lib/api";
import { useAuth } from "@/stores/auth";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";

export function Header({ title }: { title: string }) {
  const router = useRouter();
  const user = useAuth((s) => s.user);
  const logout = useAuth((s) => s.logout);

  async function onLogout() {
    try {
      await api("/auth/logout", { method: "POST" });
    } catch {
      /* ignore network errors on logout */
    }
    logout();
    router.replace("/login");
  }

  return (
    <header className="flex h-16 items-center justify-between border-b border-[var(--border)] bg-[var(--surface)] px-6">
      <h1 className="text-lg font-semibold">{title}</h1>
      <div className="flex items-center gap-3">
        <div className="relative hidden sm:block">
          <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--muted)]" />
          <Input placeholder="Search…" className="w-64 pl-9" />
        </div>
        <div className="flex items-center gap-2">
          <div className="flex h-9 w-9 items-center justify-center rounded-full bg-[var(--surface-2)] text-sm font-medium">
            {user?.name?.[0]?.toUpperCase() ?? "?"}
          </div>
          <div className="hidden text-sm leading-tight sm:block">
            <div className="font-medium">{user?.name}</div>
            <div className="text-xs text-[var(--muted)]">{user?.roles?.[0] ?? "member"}</div>
          </div>
        </div>
        <Button variant="ghost" size="icon" onClick={onLogout} title="Sign out">
          <LogOut className="h-4 w-4" />
        </Button>
      </div>
    </header>
  );
}
