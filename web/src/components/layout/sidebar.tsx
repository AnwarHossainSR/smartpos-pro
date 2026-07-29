"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { X } from "lucide-react";
import { cn } from "@/lib/utils";
import { NAV_GROUPS } from "@/lib/nav";
import { useTranslation } from "@/i18n/provider";

interface SidebarProps {
  open?: boolean;
  onClose?: () => void;
}

export function Sidebar({ open, onClose }: SidebarProps) {
  const pathname = usePathname();
  const t = useTranslation();

  const content = (
    <div className="flex h-full flex-col text-[var(--sidebar-fg)]">
      <div className="flex h-16 shrink-0 items-center justify-between gap-2 px-5">
        <div className="flex items-center gap-2">
          <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-[var(--primary)] font-bold text-[var(--primary-fg)]">
            S
          </div>
          <span className="text-lg font-semibold">SmartPOS</span>
        </div>
        {onClose && (
          <button onClick={onClose} className="md:hidden rounded p-1 hover:bg-white/10">
            <X className="h-5 w-5" />
          </button>
        )}
      </div>
      <nav className="flex-1 overflow-y-auto px-3 py-4">
        {NAV_GROUPS.map((group, i) => (
          <div
            key={group.key}
            className={cn("space-y-0.5", i > 0 && "mt-4 border-t border-white/[0.06] pt-4")}
          >
            <p className="px-3 pb-1.5 text-[10.5px] font-semibold uppercase tracking-widest text-[var(--sidebar-muted)]/60">
              {t(`navGroups.${group.key}`)}
            </p>
            {group.items.map(({ href, key, icon: Icon }) => {
              const active = pathname === href || pathname.startsWith(href + "/");
              return (
                <Link
                  key={href}
                  href={href}
                  onClick={onClose}
                  className={cn(
                    "group relative flex items-center gap-3 rounded-[var(--radius)] py-2 ps-3 pe-3 text-sm font-medium transition-colors",
                    active
                      ? "bg-[var(--sidebar-active)] text-[var(--sidebar-active-fg)] shadow-sm"
                      : "text-[var(--sidebar-muted)] hover:bg-white/[0.06] hover:text-[var(--sidebar-fg)]",
                  )}
                >
                  {active && (
                    <span className="absolute inset-y-1 start-0 w-0.5 rounded-full bg-[var(--sidebar-active-fg)]/40" />
                  )}
                  <Icon className={cn("h-4 w-4 shrink-0", !active && "opacity-80 group-hover:opacity-100")} />
                  <span className="truncate">{t(`nav.${key}`)}</span>
                </Link>
              );
            })}
          </div>
        ))}
      </nav>
    </div>
  );

  return (
    <>
      {/* Desktop sidebar */}
      <aside className="hidden w-60 shrink-0 bg-[var(--sidebar-bg)] md:block">{content}</aside>

      {/* Mobile sidebar */}
      {open && (
        <div className="fixed inset-0 z-40 md:hidden">
          <div className="absolute inset-0 bg-black/40" onClick={onClose} />
          <aside className="relative h-full w-64 bg-[var(--sidebar-bg)] shadow-xl">
            {content}
          </aside>
        </div>
      )}
    </>
  );
}
