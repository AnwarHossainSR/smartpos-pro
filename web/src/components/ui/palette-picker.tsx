import { PALETTES } from "@/lib/palettes";
import { cn } from "@/lib/utils";
import { useThemeStore } from "@/stores/theme";

export function PalettePicker({ className, compact }: { className?: string; compact?: boolean }) {
  const palette = useThemeStore((s) => s.palette);
  const setPalette = useThemeStore((s) => s.setPalette);

  return (
    <div
      className={cn(
        "grid gap-2",
        compact ? "grid-cols-3" : "grid-cols-3 sm:grid-cols-6",
        className,
      )}
    >
      {PALETTES.map((p) => {
        const active = palette === p.id;
        return (
          <button
            key={p.id}
            type="button"
            title={p.label}
            aria-pressed={active}
            onClick={() => setPalette(p.id)}
            className={cn(
              "flex flex-col items-center gap-1.5 rounded-[var(--radius)] border p-2 text-xs transition-colors",
              active
                ? "border-[var(--primary)] bg-[var(--surface-2)]"
                : "border-[var(--border)] hover:bg-[var(--surface-2)]",
            )}
          >
            <span
              className={cn(
                "h-7 w-7 rounded-full ring-2 ring-offset-2 ring-offset-[var(--surface)] transition",
                active ? "ring-[var(--fg)]" : "ring-transparent",
              )}
              style={{ backgroundColor: p.swatch }}
            />
            {!compact && <span className="font-medium text-[var(--fg)]">{p.label}</span>}
          </button>
        );
      })}
    </div>
  );
}
