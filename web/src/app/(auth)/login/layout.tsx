import type { Metadata } from "next";
import { pageMetadata } from "@/lib/seo";

export const metadata: Metadata = pageMetadata({
  title: "Sign in",
  description:
    "Sign in to your SmartPOS Pro workspace to open the till, check stock, or run today's numbers.",
  path: "/login",
  index: true,
});

export default function Layout({ children }: { children: React.ReactNode }) {
  return children;
}
