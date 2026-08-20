import type { Metadata } from "next";
import { pageMetadata } from "@/lib/seo";

// Not indexed: a bare form is thin content, and the sitemap deliberately lists
// only the three pages worth ranking.
export const metadata: Metadata = pageMetadata({
  title: "Reset your password",
  description: "Request a password reset link for your SmartPOS Pro account.",
});

export default function Layout({ children }: { children: React.ReactNode }) {
  return children;
}
