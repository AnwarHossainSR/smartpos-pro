import { cn } from "@/lib/utils";
import { Delete } from "lucide-react";
import { useCallback } from "react";

interface NumpadProps {
  onDigit: (digit: string) => void;
  onBackspace: () => void;
  onConfirm?: () => void;
  confirmLabel?: string;
  className?: string;
}

function haptic() {
  try {
    navigator.vibrate?.(10);
  } catch {
    // vibrate not supported — ignore
  }
}

const keys = [
  ["7", "8", "9"],
  ["4", "5", "6"],
  ["1", "2", "3"],
  [".", "0", "⌫"],
] as const;

export function Numpad({ onDigit, onBackspace, onConfirm, confirmLabel, className }: NumpadProps) {
  const handleKey = useCallback(
    (key: string) => {
      haptic();
      if (key === "⌫") {
        onBackspace();
      } else {
        onDigit(key);
      }
    },
    [onDigit, onBackspace],
  );

  return (
    <div className={cn("flex flex-col gap-1.5", className)}>
      {keys.map((row, ri) => (
        <div key={ri} className="grid grid-cols-3 gap-1.5">
          {row.map((key) => (
            <button
              key={key}
              type="button"
              onClick={() => handleKey(key)}
              className={cn(
                "flex h-12 items-center justify-center rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)] text-lg font-medium transition-colors",
                "active:scale-95 active:bg-[var(--surface-2)]",
                "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--primary)]",
              )}
            >
              {key === "⌫" ? <Delete className="h-5 w-5" /> : key}
            </button>
          ))}
        </div>
      ))}
      {onConfirm && (
        <button
          type="button"
          onClick={() => { haptic(); onConfirm(); }}
          className={cn(
            "mt-1 flex h-12 items-center justify-center rounded-[var(--radius)] bg-[var(--primary)] text-sm font-medium text-[var(--primary-fg)] transition-colors",
            "active:scale-95 active:opacity-90",
            "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--primary)]",
          )}
        >
          {confirmLabel ?? "OK"}
        </button>
      )}
    </div>
  );
}
