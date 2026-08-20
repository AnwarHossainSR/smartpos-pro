import type { Metadata } from "next";
import { pageMetadata } from "@/lib/seo";

// `noindex` is inherited from the (app) segment layout — this exists for the
// browser tab, the bookmark, and the history entry, not for a crawler.
export const metadata: Metadata = pageMetadata({
  title: "Tax settings",
  description: "Tax profiles, rates, groups, and zones.",
});

export default function Layout({ children }: { children: React.ReactNode }) {
  return children;
}
