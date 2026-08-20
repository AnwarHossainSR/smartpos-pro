import type { Metadata } from "next";
import { pageMetadata } from "@/lib/seo";

// As with the reset page, the URL carries a single-use token.
export const metadata: Metadata = pageMetadata({
  title: "Verify your email",
});

export default function Layout({ children }: { children: React.ReactNode }) {
  return children;
}
