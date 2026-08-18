"use client";

import { Keyboard } from "lucide-react";
import { Dialog } from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { useTranslation } from "@/i18n/provider";

interface Shortcut {
  key: string;
  labelKey: string;
}

const SHORTCUTS: Shortcut[] = [
  { key: "F2", labelKey: "pos.shortcuts.focusSearch" },
  { key: "F4", labelKey: "pos.shortcuts.holdSale" },
  { key: "F6", labelKey: "pos.shortcuts.openPayment" },
  { key: "F8", labelKey: "pos.shortcuts.voidLastLine" },
  { key: "Esc", labelKey: "pos.shortcuts.closeOrClear" },
  { key: "Ctrl + Enter", labelKey: "pos.shortcuts.confirmPayment" },
  { key: "`", labelKey: "pos.shortcuts.toggleHelp" },
];

export function ShortcutsOverlay({
  open,
  onClose,
}: {
  open: boolean;
  onClose: () => void;
}) {
  const t = useTranslation();

  return (
    <Dialog open={open} onClose={onClose} title={t("pos.shortcuts.title")}>
      <div className="space-y-3">
        {SHORTCUTS.map((s) => (
          <div
            key={s.key}
            className="flex items-center justify-between gap-4 rounded-[var(--radius)] border border-[var(--border)] px-3 py-2"
          >
            <span className="text-sm text-[var(--muted)]">{t(s.labelKey)}</span>
            <kbd className="inline-flex items-center gap-1 rounded bg-[var(--surface-2)] px-2 py-0.5 text-xs font-medium">
              <Keyboard className="h-3 w-3" />
              {s.key}
            </kbd>
          </div>
        ))}
      </div>
      <div className="mt-4 flex justify-end">
        <Button variant="outline" onClick={onClose}>
          {t("common.close")}
        </Button>
      </div>
    </Dialog>
  );
}
