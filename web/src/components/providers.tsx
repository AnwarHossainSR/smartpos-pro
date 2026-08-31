import { QueryClientProvider } from "@tanstack/react-query";
import { useEffect } from "react";
import { I18nProvider } from "@/i18n/provider";
import { queryClient } from "@/lib/query-client";
import { useAuth } from "@/stores/auth";
import { PwaRegister } from "./pwa-register";
import { Toaster } from "./ui/toast";

export function Providers({ children }: { children: React.ReactNode }) {
  const setHydrated = useAuth((s) => s.setHydrated);

  useEffect(() => {
    setHydrated();
  }, [setHydrated]);

  return (
    <QueryClientProvider client={queryClient}>
      <I18nProvider>
        {children}
        <Toaster />
        <PwaRegister />
      </I18nProvider>
    </QueryClientProvider>
  );
}
