import { clsx, type ClassValue } from "clsx";
import { twMerge } from "tailwind-merge";
import { useLocaleStore } from "@/stores/locale";
import { useBusinessStore } from "@/stores/business";

/** Merge conditional class names, resolving Tailwind conflicts. */
export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

/** UI language (nav, buttons, …) and the number/date locale are the same store — but stay
 *  independent of `currency`, which is a tenant setting (see docs/REDESIGN_I18N_PLAN.md). */
function activeLocale(): string {
  return useLocaleStore.getState().locale;
}

/** Locale-aware grouping/decimal separators, but always Latin (0-9) digits — some locales
 *  (bn, ar, …) default to native digit glyphs, which we don't want here. */
function numericLocale(): string {
  return `${activeLocale()}-u-nu-latn`;
}

/** `currency` overrides the tenant default — pass it explicitly for a sale/purchase that was
 *  recorded in a different currency than the tenant's current setting. */
export function formatCurrency(value: number, currency?: string) {
  const cur = currency ?? useBusinessStore.getState().currency;
  return new Intl.NumberFormat(numericLocale(), { style: "currency", currency: cur }).format(value ?? 0);
}

export function formatNumber(value: number) {
  return new Intl.NumberFormat(numericLocale()).format(value ?? 0);
}

export function formatDate(value: string | number | Date | null | undefined) {
  if (value == null) return "—";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "—";
  return new Intl.DateTimeFormat(numericLocale(), { dateStyle: "medium" }).format(date);
}

export function formatDateTime(value: string | number | Date | null | undefined) {
  if (value == null) return "—";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "—";
  return new Intl.DateTimeFormat(numericLocale(), { dateStyle: "medium", timeStyle: "short" }).format(date);
}

/** Opens a fetched PDF blob in a new tab — the browser's built-in viewer handles print/save. */
export function openPdfBlob(blob: Blob) {
  const url = URL.createObjectURL(blob);
  window.open(url, "_blank");
  setTimeout(() => URL.revokeObjectURL(url), 60_000);
}

/** Client-side CSV export — no backend endpoint required for a plain list dump. */
export function exportToCsv(filename: string, rows: Record<string, unknown>[]) {
  if (rows.length === 0) return;
  const headers = Object.keys(rows[0]);
  const escape = (v: unknown) => {
    const s = String(v ?? "");
    return /[",\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
  };
  const csv = [headers.join(","), ...rows.map((r) => headers.map((h) => escape(r[h])).join(","))].join("\n");
  const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = filename;
  a.click();
  URL.revokeObjectURL(url);
}
