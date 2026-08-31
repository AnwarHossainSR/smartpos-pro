import { useCallback, useEffect, useRef, useState } from "react";
import { Numpad } from "@/components/ui/numpad";
import { useIsTouchDevice } from "@/components/ui/use-is-touch-device";
import { cn } from "@/lib/utils";

interface QuantityPopoverProps {
  value: number;
  onChange: (qty: number) => void;
  min?: number;
}

export function QuantityPopover({ value, onChange, min = 1 }: QuantityPopoverProps) {
  const isTouch = useIsTouchDevice();
  const [open, setOpen] = useState(false);
  const [buffer, setBuffer] = useState(String(value));
  const ref = useRef<HTMLDivElement>(null);

  const handleDigit = useCallback((d: string) => {
    setBuffer((prev) => {
      if (d === "." && prev.includes(".")) return prev;
      if (prev === "0" && d !== ".") return d;
      return prev + d;
    });
  }, []);

  const handleBackspace = useCallback(() => {
    setBuffer((prev) => (prev.length > 1 ? prev.slice(0, -1) : ""));
  }, []);

  const confirm = useCallback(() => {
    const parsed = parseFloat(buffer);
    if (!Number.isNaN(parsed) && parsed >= min) {
      onChange(Math.round(parsed));
    }
    setOpen(false);
  }, [buffer, min, onChange]);

  useEffect(() => {
    if (!open) return;
    function handleClickOutside(e: MouseEvent) {
      if (ref.current && !ref.current.contains(e.target as Node)) {
        confirm();
      }
    }
    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, [open, confirm]);

  return (
    <div className="relative" ref={ref}>
      <button
        type="button"
        onClick={() => {
          setBuffer(String(value));
          setOpen(true);
        }}
        className="w-8 text-center text-sm tabular-nums cursor-pointer hover:bg-[var(--surface-2)] rounded px-1 py-0.5 transition-colors"
      >
        {value}
      </button>

      {open && (
        <div
          className={cn(
            "absolute left-0 top-full z-50 mt-1 w-48 rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)] p-2 shadow-lg",
          )}
        >
          {isTouch ? (
            <Numpad
              onDigit={handleDigit}
              onBackspace={handleBackspace}
              onConfirm={confirm}
              confirmLabel="✓"
              className="text-xs"
            />
          ) : (
            <input
              type="number"
              min={min}
              value={buffer}
              onChange={(e) => setBuffer(e.target.value)}
              onKeyDown={(e) => {
                if (e.key === "Enter") confirm();
                if (e.key === "Escape") setOpen(false);
              }}
              autoFocus
              className="h-10 w-full rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)] px-2 text-center text-sm outline-none focus:border-[var(--primary)]"
            />
          )}
        </div>
      )}
    </div>
  );
}
