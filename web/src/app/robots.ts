import type { MetadataRoute } from "next";

/** Everything under `(app)` is an authenticated dashboard — it carries its own `noindex` via
 *  that segment's layout metadata (see `src/app/(app)/layout.tsx`) as a defense-in-depth
 *  belt-and-braces measure, but disallowing the whole tree here keeps crawlers out even before
 *  they'd read that far. */
export default function robots(): MetadataRoute.Robots {
  const base = process.env.NEXT_PUBLIC_SITE_URL ?? "https://app.smartpospro.com";

  return {
    rules: [
      {
        userAgent: "*",
        allow: "/",
        disallow: ["/dashboard", "/pos", "/products", "/sales", "/purchases", "/settings", "/reports", "/inventory", "/customers", "/suppliers", "/expenses", "/accounting", "/cash-register", "/team", "/loyalty", "/subscription", "/notifications", "/audit-log"],
      },
    ],
    sitemap: `${base}/sitemap.xml`,
  };
}
