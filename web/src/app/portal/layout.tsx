import type { Metadata } from "next";
import { pageMetadata } from "@/lib/seo";

/**
 * The customer self-service portal.
 *
 * Public in the sense that it needs no staff login, but every page under it renders
 * one named customer's invoices and is reached through a emailed magic link. It is
 * excluded in robots.txt *and* noindex'd here, because robots.txt only stops a
 * crawler that chooses to obey it and does nothing about a URL pasted somewhere
 * crawlable.
 */
export const metadata: Metadata = pageMetadata({
  title: "Customer portal",
});

export default function PortalLayout({ children }: { children: React.ReactNode }) {
  return children;
}
