"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { X, ChevronsLeft, ChevronsRight } from "lucide-react";
import { cn } from "@/lib/utils";
import { NAV_GROUPS } from "@/lib/nav";
import { useTranslation } from "@/i18n/provider";
import { useSidebarStore } from "@/stores/sidebar";
import { Logo } from "@/components/ui/logo";

interface SidebarProps {
  open?: boolean;
  onClose?: () => void;
}

function SidebarContent({ onClose, collapsed }: { onClose?: () => void; collapsed: boolean }) {
  const pathname = usePathname();
  const t = useTranslation();
  const { toggle } = useSidebarStore();

  return (
    <div className="flex h-full flex-col text-[var(--sidebar-fg)]">
      <div className={cn("flex h-16 shrink-0 items-center gap-2 px-5", collapsed ? "justify-center px-0" : "justify-between")}>
        <div className={cn("flex items-center gap-2", collapsed && "justify-center")}>
          <Logo size={32} />
          {!collapsed && <span className="text-lg font-semibold">SmartPOS</span>}
        </div>
        {onClose && (
          <button onClick={onClose} className="rounded p-1 hover:bg-white/10 md:hidden">
            <X className="h-5 w-5" />
          </button>
        )}
      </div>
      <nav className="flex-1 overflow-y-auto overflow-x-hidden px-3 py-4">
        {NAV_GROUPS.map((group, i) => (
          <div
            key={group.key}
            className={cn("space-y-0.5", i > 0 && "mt-4 border-t border-white/[0.06] pt-4")}
          >
            {!collapsed && (
              <p className="px-3 pb-1.5 text-[10.5px] font-semibold uppercase tracking-widest text-[var(--sidebar-muted)]/60">
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
                  className={cn(
                    "group relative flex items-center gap-3 rounded-[var(--radius)] py-2 text-sm font-medium transition-colors",
                    collapsed ? "justify-center px-0" : "ps-3 pe-3",
                    active
                      ? "bg-[var(--sidebar-active)] text-[var(--sidebar-active-fg)] shadow-sm"
                      : "text-[var(--sidebar-muted)] hover:bg-white/[0.06] hover:text-[var(--sidebar-fg)]",
                  )}
                >
                  {active && !collapsed && (
                    <span className="absolute inset-y-1 start-0 w-0.5 rounded-full bg-[var(--sidebar-active-fg)]/40" />
                  )}
                  <Icon className={cn("h-4 w-4 shrink-0", !active && "opacity-80 group-hover:opacity-100")} />
                  {!collapsed && <span className="truncate">{label}</span>}
                </Link>
              );
            })}
          </div>
        ))}
      </nav>
      <button
        onClick={toggle}
        title={t(collapsed ? "sidebar.expand" : "sidebar.collapse")}
        className={cn(
          "hidden shrink-0 items-center gap-2 border-t border-white/[0.06] px-5 py-3 text-sm text-[var(--sidebar-muted)] transition-colors hover:bg-white/[0.06] hover:text-[var(--sidebar-fg)] md:flex",
          collapsed && "justify-center px-0",
        )}
      >
        {collapsed ? <ChevronsRight className="h-4 w-4" /> : <ChevronsLeft className="h-4 w-4" />}
        {!collapsed && <span>{t("sidebar.collapse")}</span>}
      </button>
    </div>
  );
}

export function Sidebar({ open, onClose }: SidebarProps) {
  const collapsed = useSidebarStore((s) => s.collapsed);

  return (
    <>
      {/* Desktop sidebar */}
      <aside
        className={cn(
          "hidden shrink-0 bg-[var(--sidebar-bg)] transition-[width] duration-200 md:block",
          collapsed ? "w-[76px]" : "w-64",
        )}
      >
        <SidebarContent collapsed={collapsed} />
      </aside>

      {/* Mobile sidebar */}
      {open && (
        <div className="fixed inset-0 z-40 md:hidden">
          <div className="absolute inset-0 bg-black/40" onClick={onClose} />
          <aside className="relative h-full w-72 bg-[var(--sidebar-bg)] shadow-xl">
            <SidebarContent onClose={onClose} collapsed={false} />
          </aside>
        </div>
      )}
    </>
  );
}
