"use client";

import { Check } from "lucide-react";
import { LOCALES, LOCALE_LABELS } from "@/i18n/config";
import { useLocaleStore } from "@/stores/locale";
import { cn } from "@/lib/utils";

export function LocalePicker({ className }: { className?: string }) {
  const locale = useLocaleStore((s) => s.locale);
  const setLocale = useLocaleStore((s) => s.setLocale);

  return (
    <div className={cn("flex flex-col", className)}>
      {LOCALES.map((code) => {
        const active = locale === code;
        return (
          <button
            key={code}
            type="button"
            aria-pressed={active}
            onClick={() => setLocale(code)}
            className={cn(
              "flex w-full items-center justify-between gap-2 rounded-[var(--radius)] px-3 py-2 text-sm transition-colors hover:bg-[var(--surface-2)]",
              active ? "font-medium text-[var(--primary)]" : "text-[var(--fg)]",
            )}
          >
            {LOCALE_LABELS[code]}
            {active && <Check className="h-3.5 w-3.5" />}
          </button>
        );
      })}
    </div>
  );
}
