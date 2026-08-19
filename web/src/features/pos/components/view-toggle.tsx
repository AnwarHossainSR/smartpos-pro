"use client";

import { LayoutGrid, List } from "lucide-react";
import { cn } from "@/lib/utils";

type ViewMode = "grid" | "list";

interface ViewToggleProps {
  value: ViewMode;
  onChange: (mode: ViewMode) => void;
}

export function ViewToggle({ value, onChange }: ViewToggleProps) {
  return (
    <div className="flex rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]">
      <button
        onClick={() => onChange("grid")}
        className={cn(
          "flex h-8 w-8 items-center justify-center rounded-l-[var(--radius)] transition-colors cursor-pointer",
          value === "grid"
            ? "bg-[var(--primary)] text-[var(--primary-fg)]"
            : "text-[var(--muted)] hover:text-[var(--fg)]",
        )}
        title="Grid view"
      >
        <LayoutGrid className="h-4 w-4" />
      </button>
      <button
        onClick={() => onChange("list")}
        className={cn(
          "flex h-8 w-8 items-center justify-center rounded-r-[var(--radius)] transition-colors cursor-pointer",
          value === "list"
            ? "bg-[var(--primary)] text-[var(--primary-fg)]"
            : "text-[var(--muted)] hover:text-[var(--fg)]",
        )}
        title="List view"
      >
        <List className="h-4 w-4" />
      </button>
    </div>
  );
}
