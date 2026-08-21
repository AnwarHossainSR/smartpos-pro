import { useEffect, useRef, useState } from "react";
import { useNavigate } from "react-router";
import { LogOut, Menu, Search, Sun, Moon, Monitor, Bell, Clock, LogIn, LogOut as LogOutIcon } from "lucide-react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { api, errorMessage } from "@/lib/api";
import { useAuth } from "@/stores/auth";
import { useThemeStore, type Theme } from "@/stores/theme";
import { useClickOutside } from "@/lib/hooks";
import { PALETTES } from "@/lib/palettes";
import { Button } from "@/components/ui/button";
import { CommandPalette } from "@/components/ui/command-palette";
import { PalettePicker } from "@/components/ui/palette-picker";
import { useTranslation } from "@/i18n/context";
import { toast } from "@/components/ui/toast";
import type { NotificationResponse } from "@/lib/notifications";
import { queryKeys } from "@/lib/query-keys";

const themeIcons: Record<Theme, React.ElementType> = { light: Sun, dark: Moon, system: Monitor };

interface CurrentShift {
  id: string;
  status: string;
  clock_in: string;
  elapsed_minutes: number;
  branch_name?: string;
}

interface HeaderProps {
  title: string;
  onMenuClick: () => void;
}

function formatElapsed(minutes: number): string {
  const h = Math.floor(minutes / 60);
  const m = minutes % 60;
  return `${h}h ${m.toString().padStart(2, "0")}m`;
}

export function Header({ title, onMenuClick }: HeaderProps) {
  const navigate = useNavigate();
  const t = useTranslation();
  const qc = useQueryClient();
  const logout = useAuth((s) => s.logout);
  const { theme, setTheme, palette } = useThemeStore();
  const [paletteOpen, setPaletteOpen] = useState(false);
  const [themeMenuOpen, setThemeMenuOpen] = useState(false);
  const [swatchMenuOpen, setSwatchMenuOpen] = useState(false);
  const themeRef = useRef<HTMLDivElement>(null);
  const swatchRef = useRef<HTMLDivElement>(null);
  const [elapsed, setElapsed] = useState(0);

  // Only `unread_count` is used, so this asks for one row — a different fetch from the
  // Notifications page's `per_page=50`, and therefore a different cache entry. Sharing one
  // key meant whichever mounted first won, and opening the page while this copy was fresh
  // rendered a single notification.
  const { data: notifData } = useQuery<NotificationResponse>({
    queryKey: queryKeys.notifications.badge(),
    queryFn: () => api<NotificationResponse>("/notifications?per_page=1"),
    staleTime: 60_000,
  });
  const unreadCount = notifData?.unread_count ?? 0;

  const { data: shiftData } = useQuery<{ data: CurrentShift | null }>({
    queryKey: queryKeys.shifts.me,
    queryFn: () => api<{ data: CurrentShift | null }>("/shifts/me"),
    staleTime: 30_000,
  });
  const currentShift = shiftData?.data;

  useEffect(() => {
    if (!currentShift || currentShift.status !== "open") return;
    const base = currentShift.elapsed_minutes;
    const start = Date.now();
    const tick = () => setElapsed(base + Math.floor((Date.now() - start) / 60000));
    tick();
    const id = setInterval(tick, 30_000);
    return () => clearInterval(id);
  }, [currentShift]);

  const clockInMutation = useMutation({
    mutationFn: () => api("/shifts/clock-in", { method: "POST", body: {} }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: queryKeys.shifts.me }); toast(t("header.clockInSuccess")); },
    onError: (e: unknown) => toast(errorMessage(e, t("header.clockInError")), "error"),
  });

  const clockOutMutation = useMutation({
    mutationFn: () => api("/shifts/clock-out", { method: "POST", body: {} }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: queryKeys.shifts.me }); toast(t("header.clockOutSuccess")); },
    onError: (e: unknown) => toast(errorMessage(e, t("header.clockOutError")), "error"),
  });

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

  useClickOutside(themeRef, () => setThemeMenuOpen(false), themeMenuOpen);
  useClickOutside(swatchRef, () => setSwatchMenuOpen(false), swatchMenuOpen);

  async function onLogout() {
    try { await api("/auth/logout", { method: "POST" }); } catch {}
    logout();
    navigate("/login", { replace: true });
  }

  const ThemeIcon = themeIcons[theme];
  const isClockedIn = currentShift?.status === "open";

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
          {/* Clock in/out pill */}
          <button
            onClick={() => isClockedIn ? clockOutMutation.mutate() : clockInMutation.mutate()}
            disabled={clockInMutation.isPending || clockOutMutation.isPending}
            className={`flex items-center gap-2 rounded-full px-3 py-1.5 text-sm font-medium transition ${
              isClockedIn
                ? "bg-green-100 text-green-800 hover:bg-green-200 dark:bg-green-900/30 dark:text-green-400"
                : "border border-[var(--border)] bg-[var(--surface-2)] text-[var(--muted)] hover:border-[var(--primary)] hover:text-[var(--primary)]"
            }`}
            title={isClockedIn ? t("header.clockOut") : t("header.clockIn")}
          >
            {isClockedIn ? (
              <>
                <span className="relative flex h-2 w-2">
                  <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75" />
                  <span className="relative inline-flex h-2 w-2 rounded-full bg-green-500" />
                </span>
                <Clock className="h-3.5 w-3.5" />
                <span className="hidden sm:inline">{formatElapsed(elapsed)}</span>
                <LogOutIcon className="h-3.5 w-3.5 sm:hidden" />
              </>
            ) : (
              <>
                <LogIn className="h-3.5 w-3.5" />
                <span className="hidden sm:inline">{t("header.clockIn")}</span>
              </>
            )}
          </button>

          {/* Search / command palette */}
          <button
            onClick={() => setPaletteOpen(true)}
            className="hidden items-center gap-2 rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface-2)] px-3 py-1.5 text-sm text-[var(--muted)] transition hover:border-[var(--primary)] sm:flex"
          >
            <Search className="h-3.5 w-3.5" />
            <span>{t("header.searchPlaceholder")}</span>
            <kbd className="ms-2 hidden rounded bg-[var(--border)] px-1 py-0.5 text-xs font-mono lg:inline">⌘K</kbd>
          </button>
          <Button variant="ghost" size="icon" className="sm:hidden" onClick={() => setPaletteOpen(true)} title={t("header.searchPlaceholder")}>
            <Search className="h-4 w-4" />
          </Button>

          {/* Notifications */}
          <Button variant="ghost" size="icon" onClick={() => navigate("/notifications")} title={t("header.notifications")} className="relative">
            <Bell className="h-4 w-4" />
            {unreadCount > 0 && (
              <span className="absolute end-1.5 top-1.5 flex h-2 w-2">
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
              title={t("header.colorPalette")}
            >
              <span
                className="h-4 w-4 rounded-full ring-1 ring-[var(--border)]"
                style={{ backgroundColor: PALETTES.find((p) => p.id === palette)?.swatch }}
              />
            </Button>
            {swatchMenuOpen && (
              <div className="absolute end-0 top-full z-20 mt-1 w-48 rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)] p-2 shadow-lg">
                <PalettePicker compact />
              </div>
            )}
          </div>

          {/* Theme toggle */}
          <div ref={themeRef} className="relative">
            <Button variant="ghost" size="icon" onClick={() => setThemeMenuOpen((v) => !v)} title={t("header.toggleTheme")}>
              <ThemeIcon className="h-4 w-4" />
            </Button>
            {themeMenuOpen && (
              <div className="absolute end-0 top-full z-20 mt-1 w-36 rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)] shadow-lg py-1">
                {(["light", "dark", "system"] as Theme[]).map((option) => {
                  const Icon = themeIcons[option];
                  return (
                    <button
                      key={option}
                      onClick={() => { setTheme(option); setThemeMenuOpen(false); }}
                      className={`flex w-full items-center gap-2 px-3 py-2 text-sm transition-colors hover:bg-[var(--surface-2)] ${theme === option ? "text-[var(--primary)] font-medium" : ""}`}
                    >
                      <Icon className="h-3.5 w-3.5" />
                      {t(`header.${option}`)}
                    </button>
                  );
                })}
              </div>
            )}
          </div>

          <Button variant="ghost" size="icon" onClick={onLogout} title={t("header.signOut")}>
            <LogOut className="h-4 w-4" />
          </Button>
        </div>
      </header>

      <CommandPalette open={paletteOpen} onClose={() => setPaletteOpen(false)} />
    </>
  );
}
