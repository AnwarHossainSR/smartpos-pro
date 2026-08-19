"use client";

import { cn } from "@/lib/utils";

interface CategoryTabsProps {
  categories: { id: string; name: string }[];
  activeCategoryId: string | null;
  onSelect: (id: string | null) => void;
  favoritesCount?: number;
}

export function CategoryTabs({ categories, activeCategoryId, onSelect, favoritesCount = 0 }: CategoryTabsProps) {
  return (
    <div className="flex gap-1 overflow-x-auto pb-1 scrollbar-none">
      <button
        onClick={() => onSelect(null)}
        className={cn(
          "shrink-0 rounded-full px-3 py-1.5 text-xs font-medium transition-colors cursor-pointer",
          activeCategoryId === null
            ? "bg-[var(--primary)] text-[var(--primary-fg)]"
            : "bg-[var(--surface-2)] text-[var(--muted)] hover:text-[var(--fg)]",
        )}
      >
        All
      </button>
      {favoritesCount > 0 && (
        <button
          onClick={() => onSelect("__favorites__")}
          className={cn(
            "shrink-0 rounded-full px-3 py-1.5 text-xs font-medium transition-colors cursor-pointer",
            activeCategoryId === "__favorites__"
              ? "bg-[var(--primary)] text-[var(--primary-fg)]"
              : "bg-[var(--surface-2)] text-[var(--muted)] hover:text-[var(--fg)]",
          )}
        >
          ★ Favorites ({favoritesCount})
        </button>
      )}
      {categories.map((cat) => (
        <button
          key={cat.id}
          onClick={() => onSelect(cat.id)}
          className={cn(
            "shrink-0 rounded-full px-3 py-1.5 text-xs font-medium transition-colors cursor-pointer",
            activeCategoryId === cat.id
              ? "bg-[var(--primary)] text-[var(--primary-fg)]"
              : "bg-[var(--surface-2)] text-[var(--muted)] hover:text-[var(--fg)]",
          )}
        >
          {cat.name}
        </button>
      ))}
    </div>
  );
}
