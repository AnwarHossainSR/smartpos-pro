import type { Metadata } from "next";
import { pageMetadata } from "@/lib/seo";

export const metadata: Metadata = pageMetadata({
  title: "Create your shop",
  description:
    "Start a free SmartPOS Pro workspace. Import your products, set up a branch, and start selling the same day. No card required.",
  path: "/register",
  index: true,
});

export default function Layout({ children }: { children: React.ReactNode }) {
  return children;
}
