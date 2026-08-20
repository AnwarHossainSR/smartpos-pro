import type { Metadata } from "next";
import { pageMetadata } from "@/lib/seo";

// `noindex` is inherited from the (app) segment layout — this exists for the
// browser tab, the bookmark, and the history entry, not for a crawler.
export const metadata: Metadata = pageMetadata({
  title: "Inventory",
  description: "Stock on hand, movements, and adjustments.",
});

export default function Layout({ children }: { children: React.ReactNode }) {
  return children;
}
