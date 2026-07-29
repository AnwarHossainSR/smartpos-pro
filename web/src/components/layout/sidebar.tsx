"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { X, PanelLeftClose, PanelLeftOpen } from "lucide-react";
import { cn } from "@/lib/utils";
import { NAV_GROUPS } from "@/lib/nav";
import { useTranslation } from "@/i18n/provider";
import { useSidebarStore } from "@/stores/sidebar";
import { useAuth } from "@/stores/auth";
import { Logo } from "@/components/ui/logo";

interface SidebarProps {
  open?: boolean;
  onClose?: () => void;
}

/** Translucent wash of the palette's accent — keeps the active pill tinted rather than solid,
 *  so the label stays light like the rest of the rail instead of flipping to a dark-on-accent pair. */
const ACTIVE_TINT = "color-mix(in oklab, var(--sidebar-active) 16%, transparent)";

function SidebarContent({ onClose, collapsed }: { onClose?: () => void; collapsed: boolean }) {
  const pathname = usePathname();
  const t = useTranslation();
  const toggle = useSidebarStore((s) => s.toggle);
  const user = useAuth((s) => s.user);

  const collapseLabel = t(collapsed ? "sidebar.expand" : "sidebar.collapse");

  return (
    <div className="flex h-full flex-col text-[var(--sidebar-fg)]">
      {/* Brand + collapse toggle. The toggle lives up here rather than at the bottom so it never
          sits under the browser/dev overlays that occupy the bottom-left corner. */}
      <div
        className={cn(
          "flex shrink-0 items-center gap-3",
          collapsed ? "flex-col justify-center gap-2 px-0 py-4" : "h-[72px] px-4",
        )}
      >
        <Logo size={collapsed ? 30 : 34} />
        {!collapsed && (
          <span className="truncate text-[17px] font-semibold tracking-tight">SmartPOS</span>
        )}
        <button
          onClick={toggle}
          title={collapseLabel}
          aria-label={collapseLabel}
          className={cn(
            "hidden shrink-0 rounded-lg p-1.5 text-[var(--sidebar-muted)] transition-colors hover:bg-white/10 hover:text-[var(--sidebar-fg)] md:block",
            !collapsed && "ms-auto",
          )}
        >
          {collapsed ? <PanelLeftOpen className="h-[18px] w-[18px]" /> : <PanelLeftClose className="h-[18px] w-[18px]" />}
        </button>
        {onClose && (
          <button
            onClick={onClose}
            className="ms-auto rounded-lg p-1.5 text-[var(--sidebar-muted)] transition-colors hover:bg-white/10 hover:text-[var(--sidebar-fg)] md:hidden"
          >
            <X className="h-5 w-5" />
          </button>
        )}
      </div>
      <div className="mx-4 border-b border-white/10" />

      {/* Navigation */}
      <nav className="scrollbar-on-hover flex-1 space-y-5 overflow-y-auto overflow-x-hidden px-3 py-4">
        {NAV_GROUPS.map((group) => (
          <div key={group.key} className="space-y-1">
            {collapsed ? (
              <div className="mx-3 mb-2 border-b border-white/[0.07]" />
            ) : (
              <p className="px-3 pb-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-[var(--sidebar-muted)]/70">
                {t(`navGroups.${group.key}`)}
              </p>
            )}
            {group.items.map(({ href, key, icon: Icon }) => {
              const active = pathname === href || pathname.startsWith(href + "/");
              const label = t(`nav.${key}`);
              return (
                <Link
                  key={href}
                  href={href}
                  onClick={onClose}
                  title={collapsed ? label : undefined}
                  aria-current={active ? "page" : undefined}
                  className={cn(
                    "group relative flex items-center gap-3 rounded-xl py-2.5 text-[13.5px] transition-colors",
                    collapsed ? "justify-center px-0" : "ps-3 pe-3",
                    active
                      ? "font-semibold text-[var(--sidebar-fg)]"
                      : "font-medium text-[var(--sidebar-muted)] hover:bg-white/[0.06] hover:text-[var(--sidebar-fg)]",
                  )}
                  style={active ? { backgroundColor: ACTIVE_TINT } : undefined}
                >
                  {active && (
                    <span className="absolute inset-y-1.5 start-0 w-[3px] rounded-full bg-[var(--sidebar-active)]" />
                  )}
                  <Icon
                    className={cn("h-[18px] w-[18px] shrink-0", !active && "opacity-80 group-hover:opacity-100")}
                  />
                  {!collapsed && <span className="truncate">{label}</span>}
                </Link>
              );
            })}
          </div>
        ))}
      </nav>

      {/* User */}
      <div className="mx-4 border-b border-white/10" />
      <div
        className={cn(
          "flex shrink-0 items-center gap-3 px-4 py-4",
          collapsed && "justify-center px-0",
        )}
        title={collapsed ? user?.name : undefined}
      >
        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white/10 text-sm font-semibold">
          {user?.name?.[0]?.toUpperCase() ?? "?"}
        </div>
        {!collapsed && (
          <div className="min-w-0 flex-1 leading-tight">
            <div className="truncate text-sm font-semibold">{user?.name}</div>
            <div className="truncate text-xs text-[var(--sidebar-muted)]">{user?.roles?.[0] ?? "member"}</div>
          </div>
        )}
      </div>
    </div>
  );
}

export function Sidebar({ open, onClose }: SidebarProps) {
  const collapsed = useSidebarStore((s) => s.collapsed);

  return (
    <>
      {/* Desktop sidebar — flush to the viewport edge, full height, square corners. */}
      <aside
        className={cn(
          "sticky top-0 hidden h-screen shrink-0 bg-[var(--sidebar-bg)] transition-[width] duration-200 md:block",
          collapsed ? "w-[76px]" : "w-64",
        )}
      >
        <SidebarContent collapsed={collapsed} />
      </aside>

      {/* Mobile sidebar */}
      {open && (
        <div className="fixed inset-0 z-40 md:hidden">
          <div className="absolute inset-0 bg-black/50" onClick={onClose} />
          <aside className="relative h-full w-72 bg-[var(--sidebar-bg)] shadow-2xl">
            <SidebarContent onClose={onClose} collapsed={false} />
          </aside>
        </div>
      )}
    </>
  );
}
