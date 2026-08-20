import type { Metadata } from "next";
import { pageMetadata } from "@/lib/seo";

// `noindex` is inherited from the (app) segment layout — this exists for the
// browser tab, the bookmark, and the history entry, not for a crawler.
export const metadata: Metadata = pageMetadata({
  title: "Email templates",
  description: "What the system sends, and how it reads.",
});

export default function Layout({ children }: { children: React.ReactNode }) {
  return children;
}
