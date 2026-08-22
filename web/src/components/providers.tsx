import { QueryClientProvider } from "@tanstack/react-query";
import { useEffect, useState } from "react";
import { Toaster } from "./ui/toast";
import { PwaRegister } from "./pwa-register";
import { I18nProvider } from "@/i18n/provider";
import { createQueryClient } from "@/lib/query-client";
import { useAuth } from "@/stores/auth";

export function Providers({ children }: { children: React.ReactNode }) {
  const [client] = useState(createQueryClient);
  const setHydrated = useAuth((s) => s.setHydrated);

  useEffect(() => {
    setHydrated();
  }, [setHydrated]);

  return (
    <QueryClientProvider client={client}>
      <I18nProvider>
        {children}
        <Toaster />
        <PwaRegister />
      </I18nProvider>
    </QueryClientProvider>
  );
}
