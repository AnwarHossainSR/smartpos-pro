import type { Metadata } from "next";
import { AppShell } from "@/components/layout/app-shell";

// Every route under (app) is an authenticated, per-tenant dashboard — never something a search
// engine should index or a shared link preview should crawl. A Server Component is required to
// export `metadata` (the actual shell below is a client component for hooks/state), so this
// file stays a thin wrapper — see src/components/layout/app-shell.tsx for the real layout.
export const metadata: Metadata = {
  robots: { index: false, follow: false, nocache: true },
};

export default function AppLayout({ children }: { children: React.ReactNode }) {
  return <AppShell>{children}</AppShell>;
}
