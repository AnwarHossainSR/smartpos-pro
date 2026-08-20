import type { Metadata } from "next";
import { pageMetadata } from "@/lib/seo";

/**
 * Order status, addressed by a per-sale token.
 *
 * The page shows what a named customer bought and what they still owe. Anyone with
 * the link can read it, which is the point — so the one thing that must not happen
 * is a search engine making those links findable without one.
 */
export const metadata: Metadata = pageMetadata({
  title: "Order status",
});

export default function OrderLayout({ children }: { children: React.ReactNode }) {
  return children;
}
