"use client";

import { useEffect, useState } from "react";
import { CheckCircle, XCircle, X } from "lucide-react";
import { cn } from "@/lib/utils";

type ToastType = "success" | "error";

interface ToastMsg {
  id: number;
  type: ToastType;
  message: string;
}

let listeners: ((t: ToastMsg) => void)[] = [];
let counter = 0;

export function toast(message: string, type: ToastType = "success") {
  const t: ToastMsg = { id: ++counter, type, message };
  listeners.forEach((fn) => fn(t));
}

export function Toaster() {
  const [toasts, setToasts] = useState<ToastMsg[]>([]);

  useEffect(() => {
    const handler = (t: ToastMsg) => {
      setToasts((prev) => [...prev, t]);
      setTimeout(() => setToasts((prev) => prev.filter((x) => x.id !== t.id)), 4000);
    };
    listeners.push(handler);
    return () => { listeners = listeners.filter((l) => l !== handler); };
  }, []);

  return (
    <div className="fixed bottom-4 end-4 z-[100] flex flex-col gap-2">
      {toasts.map((t) => (
        <div
          key={t.id}
          className={cn(
            "flex items-center gap-3 rounded-[var(--radius)] border px-4 py-3 text-sm shadow-lg",
            t.type === "success"
              ? "border-green-200 bg-green-50 text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200"
              : "border-red-200 bg-red-50 text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200",
          )}
        >
          {t.type === "success" ? (
            <CheckCircle className="h-4 w-4 shrink-0" />
          ) : (
            <XCircle className="h-4 w-4 shrink-0" />
          )}
          <span className="flex-1">{t.message}</span>
          <button onClick={() => setToasts((p) => p.filter((x) => x.id !== t.id))}>
            <X className="h-3.5 w-3.5 opacity-50 hover:opacity-100" />
          </button>
        </div>
      ))}
    </div>
  );
}
