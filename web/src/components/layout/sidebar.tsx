import { Logo } from "@/components/ui/logo";
import { useTranslation } from "@/i18n/context";
import { NAV_GROUPS, type NavGroup } from "@/lib/nav";
import { permissionsForPathname } from "@/lib/permissions";
import { cn } from "@/lib/utils";
import { hasPermission, useAuth } from "@/stores/auth";
import { useSidebarStore } from "@/stores/sidebar";
import { ChevronRight, Headset, PanelLeftClose, PanelLeftOpen, Sparkles, X } from "lucide-react";
import { Link, useLocation } from "react-router";
import { useEffect, useMemo } from "react";

/**
 * The nav mirrors the route guard: an entry is shown only when its destination would let the
 * user in, using the same `ROUTE_PERMISSIONS` table `AppShell` guards with — so a link can
 * never lead to an access-denied panel. Groups whose every child is hidden drop out entirely
 * rather than leaving a header with nothing under it.
 */
function useVisibleNavGroups(): NavGroup[] {
  const user = useAuth((s) => s.user);

  return useMemo(
    () =>
      NAV_GROUPS.map((group) => ({
        ...group,
        items: group.items.filter((item) => {
          const required = permissionsForPathname(item.href);
          return required.length === 0 || required.some((slug) => hasPermission(user, slug));
        }),
      })).filter((group) => group.items.length > 0),
    [user],
  );
}

interface SidebarProps {
  open?: boolean;
  onClose?: () => void;
}

const ACTIVE_TINT = "color-mix(in oklab, var(--sidebar-active) 16%, transparent)";

function SidebarGroup({
  group,
  pathname,
  collapsed,
  onClose,
}: {
  group: NavGroup;
  pathname: string;
  collapsed: boolean;
  onClose?: () => void;
}) {
  const t = useTranslation();
  const expandedGroup = useSidebarStore((s) => s.expandedGroup);
  const setExpandedGroup = useSidebarStore((s) => s.setExpandedGroup);

  const groupKey = group.key;
  const isExpanded = expandedGroup === groupKey;

  const hasActiveChild = group.items.some(
    (item) => pathname === item.href || pathname.startsWith(item.href + "/"),
  );

  useEffect(() => {
    if (hasActiveChild && !collapsed) {
      setExpandedGroup(groupKey);
    }
  }, [hasActiveChild, collapsed, groupKey, setExpandedGroup]);

  if (collapsed) {
    return (
      <div className="space-y-1">
        <div className="mx-3 mb-2 border-b border-white/[0.07]" />

        {group.items.map(({ href, key, icon: Icon }) => {
          const active = pathname === href || pathname.startsWith(href + "/");
          const label = t(`nav.${key}`);

          return (
              <Link
                key={href}
                to={href}
              onClick={onClose}
              title={label}
              aria-current={active ? "page" : undefined}
              className={cn(
                "group relative flex items-center justify-center rounded-xl py-2.5 text-sm transition-colors",
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
                className={cn(
                  "h-5 w-5 shrink-0",
                  !active && "opacity-80 group-hover:opacity-100",
                )}
              />
            </Link>
          );
        })}
      </div>
    );
  }

  // Single item groups such as Overview → Dashboard
  // are rendered directly without a dropdown.
  if (group.items.length === 1) {
    const { href, key, icon: Icon } = group.items[0];
    const active = pathname === href || pathname.startsWith(href + "/");
    const label = t(`nav.${key}`);

    return (
      <Link
        to={href}
        onClick={onClose}
        aria-current={active ? "page" : undefined}
        className={cn(
          "group relative flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm transition-colors",
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
          className={cn(
            "h-[18px] w-[18px] shrink-0 transition-opacity duration-200",
            !active && "opacity-80 group-hover:opacity-100",
          )}
        />

        <span className="truncate">{label}</span>
      </Link>
    );
  }

  const GroupIcon = group.groupIcon;

  return (
    <div className="space-y-0.5">
      <button
        type="button"
        aria-expanded={isExpanded}
        onClick={() => setExpandedGroup(groupKey)}
        className={cn(
          "flex w-full items-center gap-2.5 rounded-xl px-3 py-2.5 text-sm transition-colors",
          hasActiveChild
            ? "font-semibold text-[var(--sidebar-fg)]"
            : "font-medium text-[var(--sidebar-muted)] hover:bg-white/[0.06] hover:text-[var(--sidebar-fg)]",
        )}
      >
        <GroupIcon
          className={cn(
            "h-4 w-4 shrink-0 transition-colors",
            hasActiveChild
              ? "text-[var(--sidebar-active)]"
              : "text-[var(--sidebar-muted)]/60",
          )}
        />

        <span className="flex-1 text-left text-sm tracking-wide">
          {t(`navGroups.${group.key}`)}
        </span>

        <ChevronRight
          className={cn(
            "h-4 w-4 shrink-0 text-[var(--sidebar-muted)]/40 transition-transform duration-300 ease-out",
            isExpanded && "rotate-90",
          )}
        />
      </button>

      <div
        className={cn(
          "grid transition-[grid-template-rows,opacity] duration-300 ease-out",
          isExpanded
            ? "grid-rows-[1fr] opacity-100"
            : "grid-rows-[0fr] opacity-0",
        )}
      >
        <div className="overflow-hidden">
          <div className="space-y-0.5 border-s border-white/[0.06] ms-[22px] ps-3 pb-1">
            {group.items.map(({ href, key, icon: Icon }) => {
              const active =
                pathname === href || pathname.startsWith(href + "/");
              const label = t(`nav.${key}`);

              return (
                <Link
                  key={href}
                  to={href}
                  onClick={onClose}
                  aria-current={active ? "page" : undefined}
                  className={cn(
                    "group relative flex items-center gap-3 rounded-lg py-2 ps-3 pe-3 text-sm transition-all duration-200",
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
                    className={cn(
                      "h-[18px] w-[18px] shrink-0 transition-opacity duration-200",
                      !active && "opacity-80 group-hover:opacity-100",
                    )}
                  />

                  <span className="truncate">{label}</span>
                </Link>
              );
            })}
          </div>
        </div>
      </div>
    </div>
  );
}

function SidebarContent({ onClose, collapsed }: { onClose?: () => void; collapsed: boolean }) {
  const { pathname } = useLocation();
  const t = useTranslation();
  const toggle = useSidebarStore((s) => s.toggle);
  const expandedGroup = useSidebarStore((s) => s.expandedGroup);
  const setExpandedGroup = useSidebarStore((s) => s.setExpandedGroup);
  const user = useAuth((s) => s.user);
  const groups = useVisibleNavGroups();

  const collapseLabel = t(collapsed ? "sidebar.expand" : "sidebar.collapse");

  useEffect(() => {
    if (collapsed || expandedGroup) return;
    const activeGroup = groups.find((g) =>
      g.items.some((item) => pathname === item.href || pathname.startsWith(item.href + "/")),
    );
    setExpandedGroup(activeGroup?.key ?? groups[0]?.key ?? null);
  }, [collapsed, expandedGroup, groups, pathname, setExpandedGroup]);

  return (
    <div className="flex h-full flex-col text-[var(--sidebar-fg)]">
      <div
        className={cn(
          "flex shrink-0 items-center gap-3",
          collapsed ? "flex-col justify-center gap-2 px-0 py-4" : "h-[72px] px-4",
        )}
      >
        <Logo size={collapsed ? 30 : 34} />
        {!collapsed && (
          <span className="truncate text-lg font-semibold tracking-tight">SmartPOS</span>
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
          {collapsed ? (
            <PanelLeftOpen className="h-[18px] w-[18px]" />
          ) : (
            <PanelLeftClose className="h-[18px] w-[18px]" />
          )}
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

      <nav aria-label={t("nav.primary")} className="scrollbar-on-hover flex-1 space-y-1 overflow-y-auto overflow-x-hidden px-3 py-4">
        {groups.map((group) => (
          <SidebarGroup
            key={group.key}
            group={group}
            pathname={pathname}
            collapsed={collapsed}
            onClose={onClose}
          />
        ))}
      </nav>

      {!collapsed && (
        <div className="mx-3">
          <div className="rounded-xl bg-gradient-to-l from-[var(--sidebar-active)]/20 via-[var(--sidebar-active)]/10 to-transparent p-[1px]">
            <div className="rounded-[11px] bg-[var(--sidebar-bg)] px-3 py-3">
              <Link
                to="/support"
                className="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-[var(--sidebar-fg)] transition-all duration-200 hover:bg-white/[0.08]"
              >
                <Sparkles className="h-3.5 w-3.5 text-[var(--sidebar-active)]/60 transition-transform duration-300 group-hover:scale-110 group-hover:text-[var(--sidebar-active)]" />
                <span className="flex-1 text-right">Support</span>
                <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[var(--sidebar-active)]/20 transition-colors group-hover:bg-[var(--sidebar-active)]/30">
                  <Headset className="h-4 w-4 text-[var(--sidebar-active)]" />
                </span>
              </Link>
            </div>
          </div>
        </div>
      )}

      {collapsed && (
        <div className="mx-3 mb-3">
          <Link
            to="/support"
            title="Support"
            className="group flex items-center justify-center rounded-xl py-2.5 transition-colors hover:bg-white/[0.06]"
          >
            <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-[var(--sidebar-active)]/20 transition-colors group-hover:bg-[var(--sidebar-active)]/30">
              <Headset className="h-4 w-4 text-[var(--sidebar-active)]" />
            </span>
          </Link>
        </div>
      )}

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
      <aside
        className={cn(
          "hidden h-full shrink-0 bg-[var(--sidebar-bg)] transition-[width] duration-200 md:block",
          collapsed ? "w-[76px]" : "w-64",
        )}
      >
        <SidebarContent collapsed={collapsed} />
      </aside>

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
