import { create } from "zustand";
import { persist } from "zustand/middleware";

interface SidebarState {
  collapsed: boolean;
  expandedGroup: string | null;
  toggle: () => void;
  setExpandedGroup: (key: string | null) => void;
}

export const useSidebarStore = create<SidebarState>()(
  persist(
    (set) => ({
      collapsed: false,
      expandedGroup: null,
      toggle: () => set((s) => ({ collapsed: !s.collapsed })),
      setExpandedGroup: (key) =>
        set((s) => ({ expandedGroup: s.expandedGroup === key ? null : key })),
    }),
    { name: "smartpos-sidebar" },
  ),
);
