import type { Metadata } from "next";

/**
 * Auth screens. Indexable by default so `/login` and `/register` can be found and
 * linked — the individual token-carrying pages (`/reset-password`, `/verify-email`)
 * override this with their own `noindex`.
 */
export const metadata: Metadata = {
  robots: { index: true, follow: true },
};

export default function AuthLayout({ children }: { children: React.ReactNode }) {
  return children;
}
