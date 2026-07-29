"use client";

import { useEffect, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { LogOut, Menu, Search, Sun, Moon, Monitor, Bell } from "lucide-react";
import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";
import { useAuth } from "@/stores/auth";
import { useThemeStore, type Theme } from "@/stores/theme";
import { PALETTES } from "@/lib/palettes";
import { Button } from "@/components/ui/button";
import { CommandPalette } from "@/components/ui/command-palette";
import { PalettePicker } from "@/components/ui/palette-picker";

interface NotifResponse {
  data: unknown[];
  unread_count: number;
}

const themeIcons: Record<Theme, React.ElementType> = { light: Sun, dark: Moon, system: Monitor };

interface HeaderProps {
  title: string;
  onMenuClick: () => void;
}

export function Header({ title, onMenuClick }: HeaderProps) {
  const router = useRouter();
  const user = useAuth((s) => s.user);
  const logout = useAuth((s) => s.logout);
  const { theme, setTheme, palette } = useThemeStore();
  const [paletteOpen, setPaletteOpen] = useState(false);
  const [themeMenuOpen, setThemeMenuOpen] = useState(false);
  const [swatchMenuOpen, setSwatchMenuOpen] = useState(false);
  const themeRef = useRef<HTMLDivElement>(null);
  const swatchRef = useRef<HTMLDivElement>(null);

  const { data: notifData } = useQuery<NotifResponse>({
    queryKey: ["notifications"],
    queryFn: () => api<NotifResponse>("/notifications?per_page=1"),
    staleTime: 60_000,
  });
  const unreadCount = notifData?.unread_count ?? 0;

  // ⌘K / Ctrl+K
  useEffect(() => {
    function onKey(e: KeyboardEvent) {
      if ((e.metaKey || e.ctrlKey) && e.key === "k") {
        e.preventDefault();
        setPaletteOpen((v) => !v);
      }
    }
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, []);

  // Close theme menu on outside click
  useEffect(() => {
    function onClick(e: MouseEvent) {
      if (themeRef.current && !themeRef.current.contains(e.target as Node)) {
        setThemeMenuOpen(false);
      }
    }
    if (themeMenuOpen) document.addEventListener("mousedown", onClick);
    return () => document.removeEventListener("mousedown", onClick);
  }, [themeMenuOpen]);

  // Close palette swatch menu on outside click
  useEffect(() => {
    function onClick(e: MouseEvent) {
      if (swatchRef.current && !swatchRef.current.contains(e.target as Node)) {
        setSwatchMenuOpen(false);
      }
    }
    if (swatchMenuOpen) document.addEventListener("mousedown", onClick);
    return () => document.removeEventListener("mousedown", onClick);
  }, [swatchMenuOpen]);

  async function onLogout() {
    try { await api("/auth/logout", { method: "POST" }); } catch {}
    logout();
    router.replace("/login");
  }

  const ThemeIcon = themeIcons[theme];

  return (
    <>
      <header className="flex h-16 shrink-0 items-center justify-between border-b border-[var(--border)] bg-[var(--surface)] px-4 md:px-6">
        <div className="flex items-center gap-3">
          <button onClick={onMenuClick} className="rounded p-1.5 hover:bg-[var(--surface-2)] md:hidden">
            <Menu className="h-5 w-5" />
          </button>
          <h1 className="text-base font-semibold md:text-lg">{title}</h1>
        </div>

        <div className="flex items-center gap-1 md:gap-2">
          {/* Search / command palette */}
          <button
            onClick={() => setPaletteOpen(true)}
            className="hidden items-center gap-2 rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface-2)] px-3 py-1.5 text-sm text-[var(--muted)] transition hover:border-[var(--primary)] sm:flex"
          >
            <Search className="h-3.5 w-3.5" />
            <span>Search…</span>
            <kbd className="ml-2 hidden rounded bg-[var(--border)] px-1 py-0.5 text-xs font-mono lg:inline">⌘K</kbd>
          </button>
          <Button variant="ghost" size="icon" className="sm:hidden" onClick={() => setPaletteOpen(true)} title="Search">
            <Search className="h-4 w-4" />
          </Button>

          {/* Notifications */}
          <Button variant="ghost" size="icon" onClick={() => router.push("/notifications")} title="Notifications" className="relative">
            <Bell className="h-4 w-4" />
            {unreadCount > 0 && (
              <span className="absolute right-1.5 top-1.5 flex h-2 w-2">
                <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-[var(--color-danger)] opacity-75" />
                <span className="relative inline-flex h-2 w-2 rounded-full bg-[var(--color-danger)]" />
              </span>
            )}
          </Button>

          {/* Palette swatch */}
          <div ref={swatchRef} className="relative">
            <Button
              variant="ghost"
              size="icon"
              onClick={() => setSwatchMenuOpen((v) => !v)}
              title="Color palette"
            >
              <span
                className="h-4 w-4 rounded-full ring-1 ring-[var(--border)]"
                style={{ backgroundColor: PALETTES.find((p) => p.id === palette)?.swatch }}
              />
            </Button>
            {swatchMenuOpen && (
              <div className="absolute right-0 top-full z-20 mt-1 w-48 rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)] p-2 shadow-lg">
                <PalettePicker compact />
              </div>
            )}
          </div>

          {/* Theme toggle */}
          <div ref={themeRef} className="relative">
            <Button variant="ghost" size="icon" onClick={() => setThemeMenuOpen((v) => !v)} title="Toggle theme">
              <ThemeIcon className="h-4 w-4" />
            </Button>
            {themeMenuOpen && (
              <div className="absolute right-0 top-full z-20 mt-1 w-36 rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)] shadow-lg py-1">
                {(["light", "dark", "system"] as Theme[]).map((t) => {
                  const Icon = themeIcons[t];
                  return (
                    <button
                      key={t}
                      onClick={() => { setTheme(t); setThemeMenuOpen(false); }}
                      className={`flex w-full items-center gap-2 px-3 py-2 text-sm capitalize transition-colors hover:bg-[var(--surface-2)] ${theme === t ? "text-[var(--primary)] font-medium" : ""}`}
                    >
                      <Icon className="h-3.5 w-3.5" />
                      {t}
                    </button>
                  );
                })}
              </div>
            )}
          </div>

          {/* User */}
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

      <CommandPalette open={paletteOpen} onClose={() => setPaletteOpen(false)} />
    </>
  );
}
