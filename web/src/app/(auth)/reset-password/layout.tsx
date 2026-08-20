import type { Metadata } from "next";
import { pageMetadata } from "@/lib/seo";

// The URL carries a single-use reset token. Indexing one would publish a working
// credential, so the noindex here is load-bearing, not tidiness.
export const metadata: Metadata = pageMetadata({
  title: "Choose a new password",
});

export default function Layout({ children }: { children: React.ReactNode }) {
  return children;
}
