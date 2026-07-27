"use client";

import { cn } from "@/lib/utils";
import { Button } from "./button";

interface DialogProps {
  open: boolean;
  onClose: () => void;
  title: string;
  description?: string;
  children?: React.ReactNode;
  confirmLabel?: string;
  confirmVariant?: "primary" | "danger";
  onConfirm?: () => void;
  loading?: boolean;
}

export function Dialog({
  open,
  onClose,
  title,
  description,
  children,
  confirmLabel = "Confirm",
  confirmVariant = "primary",
  onConfirm,
  loading,
}: DialogProps) {
  if (!open) return null;
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-black/40 backdrop-blur-sm" onClick={onClose} />
      <div
        className={cn(
          "relative w-full max-w-md rounded-[var(--radius)] bg-[var(--surface)] p-6 shadow-xl",
        )}
      >
        <h2 className="text-base font-semibold">{title}</h2>
        {description && <p className="mt-1.5 text-sm text-[var(--muted)]">{description}</p>}
        {children && <div className="mt-4">{children}</div>}
        {onConfirm && (
          <div className="mt-6 flex justify-end gap-2">
            <Button variant="outline" onClick={onClose} disabled={loading}>
              Cancel
            </Button>
            <Button variant={confirmVariant} onClick={onConfirm} disabled={loading}>
              {loading ? "…" : confirmLabel}
            </Button>
          </div>
        )}
      </div>
    </div>
  );
}
